<?php

session_start();

require_once "db.php";
require_once "csrf.php";
require_once "session_security.php";
require_once "audit_log.php";

/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| ROLE CHECK
|--------------------------------------------------------------------------
*/
$allowed_roles = [
    "Administrator",
    "HR Staff",
    "Payroll Staff"
];

if (
    !isset($_SESSION["Role"]) ||
    !in_array($_SESSION["Role"], $allowed_roles, true)
) {
    http_response_code(403);
    exit("Access denied. You do not have permission to save payroll.");
}

/*
|--------------------------------------------------------------------------
| ONLY ALLOW POST REQUEST
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: payroll.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF SECURITY CHECK
|--------------------------------------------------------------------------
*/
$csrf_token = $_POST["csrf_token"] ?? "";

if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    exit("Invalid security token. Please return to the payroll page and try again.");
}

/*
|--------------------------------------------------------------------------
| GET FORM DATA
|--------------------------------------------------------------------------
*/
$employee_id = filter_var(
    $_POST["employee_id"] ?? 0,
    FILTER_VALIDATE_INT
);

$employee_id = $employee_id ?: 0;

$pay_period = trim($_POST["pay_period"] ?? "");
$pay_date = trim($_POST["pay_date"] ?? "");
$basic_salary_input = trim($_POST["basic_salary"] ?? "");

$earning_descriptions = $_POST["earning_description"] ?? [];
$earning_amounts = $_POST["earning_amount"] ?? [];

$deduction_descriptions = $_POST["deduction_description"] ?? [];
$deduction_amounts = $_POST["deduction_amount"] ?? [];

/*
|--------------------------------------------------------------------------
| BASIC VALIDATION
|--------------------------------------------------------------------------
*/
if ($employee_id <= 0) {
    exit("Please select a valid employee.");
}

if ($pay_period === "" || strlen($pay_period) > 100) {
    exit("Please enter a valid pay period (maximum 100 characters).");
}

if ($pay_date === "") {
    exit("Please enter the pay date.");
}

/*
|--------------------------------------------------------------------------
| VALIDATE PAY DATE
|--------------------------------------------------------------------------
*/
$date_object = DateTime::createFromFormat("!Y-m-d", $pay_date);
$date_errors = DateTime::getLastErrors();

if (
    !$date_object ||
    (
        $date_errors !== false &&
        (
            $date_errors["warning_count"] > 0 ||
            $date_errors["error_count"] > 0
        )
    ) ||
    $date_object->format("Y-m-d") !== $pay_date
) {
    exit("Please enter a valid pay date.");
}

/*
|--------------------------------------------------------------------------
| VALIDATE BASIC SALARY
|--------------------------------------------------------------------------
*/
if (
    $basic_salary_input === "" ||
    !is_numeric($basic_salary_input)
) {
    exit("Please enter a valid basic salary.");
}

$basic_salary = (float) $basic_salary_input;

if (!is_finite($basic_salary) || $basic_salary < 0) {
    exit("Basic salary must be a valid non-negative amount.");
}

/*
|--------------------------------------------------------------------------
| VALIDATE EARNING AND DEDUCTION ARRAYS
|--------------------------------------------------------------------------
*/
if (
    !is_array($earning_descriptions) ||
    !is_array($earning_amounts) ||
    !is_array($deduction_descriptions) ||
    !is_array($deduction_amounts)
) {
    exit("Invalid earning or deduction information.");
}

/*
|--------------------------------------------------------------------------
| CHECK EMPLOYEE EXISTS
|--------------------------------------------------------------------------
*/
$employee_check = $conn->prepare("
    SELECT Emp_ID
    FROM employee
    WHERE Emp_ID = ?
    LIMIT 1
");

if (!$employee_check) {
    exit("Unable to validate the selected employee.");
}

$employee_check->bind_param("i", $employee_id);
$employee_check->execute();

$employee_result = $employee_check->get_result();

if ($employee_result->num_rows !== 1) {
    $employee_check->close();
    exit("The selected employee does not exist.");
}

$employee_check->close();

/*
|--------------------------------------------------------------------------
| CALCULATE EARNINGS
|--------------------------------------------------------------------------
*/
$total_earnings = 0;
$earnings = [];

$earning_count = max(
    count($earning_amounts),
    count($earning_descriptions)
);

for ($i = 0; $i < $earning_count; $i++) {

    $description = trim((string) ($earning_descriptions[$i] ?? ""));
    $amount_input = trim((string) ($earning_amounts[$i] ?? ""));

    if ($description === "" && $amount_input === "") {
        continue;
    }

    if ($description === "" || strlen($description) > 255) {
        exit("Every earning must have a description of no more than 255 characters.");
    }

    if ($amount_input === "" || !is_numeric($amount_input)) {
        exit("Please enter a valid amount for every earning.");
    }

    $amount = (float) $amount_input;

    if (!is_finite($amount) || $amount < 0) {
        exit("Earning amounts must be valid non-negative numbers.");
    }

    if ($amount == 0) {
        continue;
    }

    $earnings[] = [
        "description" => $description,
        "amount" => $amount
    ];

    $total_earnings += $amount;
}

/*
|--------------------------------------------------------------------------
| CALCULATE DEDUCTIONS
|--------------------------------------------------------------------------
*/
$total_deductions = 0;
$deductions = [];

$deduction_count = max(
    count($deduction_amounts),
    count($deduction_descriptions)
);

for ($i = 0; $i < $deduction_count; $i++) {

    $description = trim((string) ($deduction_descriptions[$i] ?? ""));
    $amount_input = trim((string) ($deduction_amounts[$i] ?? ""));

    if ($description === "" && $amount_input === "") {
        continue;
    }

    if ($description === "" || strlen($description) > 255) {
        exit("Every deduction must have a description of no more than 255 characters.");
    }

    if ($amount_input === "" || !is_numeric($amount_input)) {
        exit("Please enter a valid amount for every deduction.");
    }

    $amount = (float) $amount_input;

    if (!is_finite($amount) || $amount < 0) {
        exit("Deduction amounts must be valid non-negative numbers.");
    }

    if ($amount == 0) {
        continue;
    }

    $deductions[] = [
        "description" => $description,
        "amount" => $amount
    ];

    $total_deductions += $amount;
}

/*
|--------------------------------------------------------------------------
| CALCULATE PAYROLL TOTALS
|--------------------------------------------------------------------------
*/
$total_gross = $basic_salary + $total_earnings;
$net_pay = $total_gross - $total_deductions;

if ($net_pay < 0) {
    exit("Total deductions cannot be greater than total gross pay.");
}

/*
|--------------------------------------------------------------------------
| SAVE PAYROLL USING A TRANSACTION
|--------------------------------------------------------------------------
*/
$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | INSERT PAYROLL
    |--------------------------------------------------------------------------
    */
    $payroll_stmt = $conn->prepare("
        INSERT INTO payroll
        (
            Emp_ID,
            Pay_Period,
            Pay_Date,
            Basic_Salary,
            Allowance,
            Overtime,
            Total_Gross,
            Total_Deductions,
            Net_Pay
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$payroll_stmt) {
        throw new Exception("Unable to prepare payroll record.");
    }

    // Dynamic earnings are stored in payroll_details.
    $allowance = 0;
    $overtime = 0;

    $payroll_stmt->bind_param(
        "issdddddd",
        $employee_id,
        $pay_period,
        $pay_date,
        $basic_salary,
        $allowance,
        $overtime,
        $total_gross,
        $total_deductions,
        $net_pay
    );

    if (!$payroll_stmt->execute()) {
        throw new Exception("Unable to save payroll record.");
    }

    $payroll_id = $conn->insert_id;
    $payroll_stmt->close();

    /*
    |--------------------------------------------------------------------------
    | INSERT EARNINGS
    |--------------------------------------------------------------------------
    */
    $earning_stmt = $conn->prepare("
        INSERT INTO payroll_details
        (
            Payroll_ID,
            Deduction_ID,
            Description,
            Earnings,
            Deduction_Amount,
            Amount,
            Type
        )
        VALUES (?, NULL, ?, ?, 0, ?, 'Earning')
    ");

    if (!$earning_stmt) {
        throw new Exception("Unable to prepare earning records.");
    }

    foreach ($earnings as $earning) {

        $description = $earning["description"];
        $amount = $earning["amount"];

        $earning_stmt->bind_param(
            "isdd",
            $payroll_id,
            $description,
            $amount,
            $amount
        );

        if (!$earning_stmt->execute()) {
            throw new Exception("Unable to save earning details.");
        }
    }

    $earning_stmt->close();

    /*
    |--------------------------------------------------------------------------
    | INSERT DEDUCTIONS
    |--------------------------------------------------------------------------
    */
    $deduction_stmt = $conn->prepare("
        INSERT INTO payroll_details
        (
            Payroll_ID,
            Deduction_ID,
            Description,
            Earnings,
            Deduction_Amount,
            Amount,
            Type
        )
        VALUES (?, NULL, ?, 0, ?, ?, 'Deduction')
    ");

    if (!$deduction_stmt) {
        throw new Exception("Unable to prepare deduction records.");
    }

    foreach ($deductions as $deduction) {

        $description = $deduction["description"];
        $amount = $deduction["amount"];

        $deduction_stmt->bind_param(
            "isdd",
            $payroll_id,
            $description,
            $amount,
            $amount
        );

        if (!$deduction_stmt->execute()) {
            throw new Exception("Unable to save deduction details.");
        }
    }

    $deduction_stmt->close();

    /*
    |--------------------------------------------------------------------------
    | COMMIT TRANSACTION
    |--------------------------------------------------------------------------
    */
    $conn->commit();

    /*
    |--------------------------------------------------------------------------
    | AUDIT LOG
    |--------------------------------------------------------------------------
    */
    write_audit_log(
        $conn,
        $_SESSION["User_ID"],
        "CREATE_PAYROLL",
        "Payroll ID " . $payroll_id .
        " created for Employee ID " . $employee_id
    );

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK ON DATABASE FAILURE
    |--------------------------------------------------------------------------
    */
    $conn->rollback();

    error_log("Payroll save failed: " . $e->getMessage());

    http_response_code(500);

    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Payroll Error</title>
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 20px;
                box-sizing: border-box;
                background: #0b0d10;
                color: #f4f0e9;
                font-family: Arial, sans-serif;
            }
            .error-box {
                max-width: 500px;
                padding: 36px;
                background: #15191e;
                border: 1px solid #292f37;
                border-top: 4px solid #641c38;
                border-radius: 16px;
                text-align: center;
            }
            h1 { color: #f4f0e9; }
            p { color: #9ca5b2; line-height: 1.6; }
            a {
                display: inline-block;
                margin-top: 12px;
                padding: 12px 18px;
                background: #641c38;
                color: #f4f0e9;
                border-radius: 8px;
                text-decoration: none;
            }
            a:hover { background: #84274c; }
        </style>
    </head>
    <body>
        <div class='error-box'>
            <h1>Payroll Could Not Be Saved</h1>
            <p>Please check the information and try again. If the problem continues, contact the system administrator.</p>
            <a href='payroll.php'>Return to Payroll</a>
        </div>
    </body>
    </html>";

    $conn->close();
    exit;
}

/*
|--------------------------------------------------------------------------
| SUCCESS PAGE
|--------------------------------------------------------------------------
*/
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Payroll Saved | Payroll and Benefits System</title>

    <style>
        :root {
            --bg: #0b0d10;
            --sidebar: #101317;
            --panel: #15191e;
            --panel-light: #1a1f25;
            --border: #292f37;
            --maroon: #641c38;
            --maroon-hover: #84274c;
            --gold: #d0ad70;
            --text: #f4f0e9;
            --muted: #9ca5b2;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            min-height: 100vh;
            font-family: "DM Sans", Arial, sans-serif;
            background:
                radial-gradient(
                    circle at top left,
                    rgba(100, 28, 56, 0.18),
                    transparent 38%
                ),
                var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .success-box {
            position: relative;
            overflow: hidden;
            width: 100%;
            max-width: 650px;
            padding: 42px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
        }

        .success-box::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(
                90deg,
                var(--maroon),
                var(--gold),
                var(--maroon)
            );
        }

        .success-icon {
            width: 66px;
            height: 66px;
            margin: 0 auto 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(208, 173, 112, 0.45);
            border-radius: 50%;
            background: rgba(100, 28, 56, 0.22);
            color: var(--gold);
            font-size: 34px;
            font-weight: bold;
        }

        .success-box h1 {
            margin: 8px 0 10px;
            color: var(--text);
            font-size: clamp(24px, 4vw, 32px);
            font-weight: 700;
            letter-spacing: -0.7px;
        }

        .subtitle {
            margin: 0 0 20px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .payroll-id {
            display: inline-block;
            margin-bottom: 24px;
            padding: 8px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--panel-light);
            color: var(--muted);
            font-size: 13px;
        }

        .payroll-id strong {
            color: var(--gold);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
            text-align: left;
        }

        .summary-card {
            padding: 20px;
            background: var(--panel-light);
            border: 1px solid var(--border);
            border-left: 3px solid var(--maroon);
            border-radius: 12px;
        }

        .summary-card.net-pay {
            border-left-color: var(--gold);
            background:
                linear-gradient(
                    110deg,
                    rgba(100, 28, 56, 0.2),
                    transparent 75%
                ),
                var(--panel-light);
        }

        .summary-label {
            margin-bottom: 9px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .summary-amount {
            color: var(--gold);
            font-size: clamp(23px, 4vw, 29px);
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            overflow-wrap: anywhere;
        }

        .net-pay .summary-amount {
            color: var(--text);
        }

        .actions {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 28px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 13px 20px;
            background: var(--maroon);
            border: 1px solid rgba(208, 173, 112, 0.22);
            border-radius: 9px;
            color: var(--text);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition:
                background 0.2s ease,
                border-color 0.2s ease,
                transform 0.2s ease;
        }

        .button:hover {
            background: var(--maroon-hover);
            border-color: var(--gold);
            transform: translateY(-2px);
        }

        .button.secondary {
            background: var(--panel-light);
            border-color: var(--border);
        }

        .button.secondary:hover {
            background: var(--maroon);
            border-color: var(--gold);
        }

        .button:focus-visible {
            outline: 2px solid var(--gold);
            outline-offset: 4px;
        }

        .footer-note {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        @media (min-width: 560px) {
            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }

            .summary-card.net-pay {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 520px) {
            body {
                padding: 16px;
            }

            .success-box {
                padding: 32px 20px;
                border-radius: 16px;
            }

            .actions {
                flex-direction: column;
            }

            .button {
                width: 100%;
            }

            .summary-card {
                padding: 17px;
            }
        }
    </style>
</head>

<body>

    <main class="success-box">

        <div class="success-icon" aria-hidden="true">
            &#10003;
        </div>

        <h1>Payroll Saved Successfully!</h1>

        <p class="subtitle">
            The payroll record has been saved successfully.
        </p>

        <div class="payroll-id">
            Payroll ID:
            <strong><?php echo (int) $payroll_id; ?></strong>
        </div>

        <section class="summary-grid" aria-label="Payroll summary">

            <div class="summary-card">
                <div class="summary-label">Total Gross</div>

                <div class="summary-amount">
                    &#8369;<?php echo number_format($total_gross, 2); ?>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-label">Total Deductions</div>

                <div class="summary-amount">
                    &#8369;<?php echo number_format($total_deductions, 2); ?>
                </div>
            </div>

            <div class="summary-card net-pay">
                <div class="summary-label">Net Pay</div>

                <div class="summary-amount">
                    &#8369;<?php echo number_format($net_pay, 2); ?>
                </div>
            </div>

        </section>

        <div class="actions">

            <a href="payroll.php" class="button">
                Create Another Payroll
            </a>

            <a href="dashboard.php" class="button secondary">
                Back to Dashboard
            </a>

        </div>

        <div class="footer-note">
            Payroll information has been processed by the Payroll and Benefits System.
            Please verify the saved record if needed.
        </div>

    </main>

</body>
</html>

<?php
$conn->close();
?>