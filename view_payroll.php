<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";
require_once "session_security.php";
require_once "encryption.php";

/*
|--------------------------------------------------------------------------
| Check login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION["Role"] ?? "";

$user_emp_id = isset($_SESSION["Emp_ID"])
    ? (int) $_SESSION["Emp_ID"]
    : 0;


/*
|--------------------------------------------------------------------------
| Get payroll ID
|--------------------------------------------------------------------------
*/

if (!isset($_GET["id"]) || !ctype_digit($_GET["id"])) {
    die("Invalid payroll ID.");
}

$payroll_id = (int) $_GET["id"];

if ($payroll_id <= 0) {
    die("Invalid payroll ID.");
}


/*
|--------------------------------------------------------------------------
| GET PAYROLL RECORD
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        p.Payroll_ID,
        p.Emp_ID,
        p.Pay_Period,
        p.Pay_Date,
        p.Basic_Salary,
        p.Allowance,
        p.Overtime,
        p.Total_Gross,
        p.Total_Deductions,
        p.Net_Pay,
        p.Created_At,
        e.First_Name,
        e.Last_Name,
        e.Email
    FROM payroll p
    INNER JOIN employee e
        ON p.Emp_ID = e.Emp_ID
    WHERE p.Payroll_ID = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error.");
}

$stmt->bind_param("i", $payroll_id);

$stmt->execute();

$result = $stmt->get_result();

$payroll = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Payroll does not exist
|--------------------------------------------------------------------------
*/

if (!$payroll) {
    die("Payroll record not found.");
}


/*
|--------------------------------------------------------------------------
| ROLE-BASED ACCESS
|--------------------------------------------------------------------------
|
| Administrator and Payroll Staff
| can view any payroll record.
|
*/

if ($role === "Administrator" || $role === "Payroll Staff") {

    // Allowed

}


/*
|--------------------------------------------------------------------------
| Employee
|--------------------------------------------------------------------------
|
| Employee can ONLY view their own payroll.
|
*/

elseif ($role === "Employee") {

    if ($user_emp_id <= 0) {
        die("Your account is not linked to an employee record.");
    }

    if ((int)$payroll["Emp_ID"] !== $user_emp_id) {
        die("Access denied. You can only view your own payroll records.");
    }

}


/*
|--------------------------------------------------------------------------
| All other roles are denied
|--------------------------------------------------------------------------
*/

else {

    die(
        "Access denied. You do not have permission to view payroll records."
    );

}


/*
|--------------------------------------------------------------------------
| GET PAYROLL DETAILS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        pd.Detail_ID,
        pd.Description,
        pd.Earnings,
        pd.Deduction_Amount,
        pd.Amount,
        pd.Type,
        pd.Deduction_ID,
        dt.Deduction_Name,
        dt.Deduction_Category
    FROM payroll_details pd
    LEFT JOIN deduction_types dt
        ON pd.Deduction_ID = dt.Deduction_ID
    WHERE pd.Payroll_ID = ?
    ORDER BY pd.Detail_ID ASC
");

if (!$stmt) {
    die("Database error.");
}

$stmt->bind_param("i", $payroll_id);

$stmt->execute();

$details_result = $stmt->get_result();

$earnings = [];

$deductions = [];

while ($detail = $details_result->fetch_assoc()) {

    if ($detail["Type"] === "Earning") {

        $earnings[] = $detail;

    } elseif ($detail["Type"] === "Deduction") {

        $deductions[] = $detail;

    }

}

$stmt->close();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>View Payroll</title>


    <style>

        /* =========================================================
           GLOBAL
        ========================================================= */

        * {
            box-sizing: border-box;
        }

        :root {

            --bg: #090b0d;

            --panel: #111417;

            --panel-light: #15191c;

            --border: #282d31;

            --maroon: #641C28;

            --maroon-dark: #3A0B13;

            --maroon-hover: #7A2635;

            --gold: #C9A66B;

            --gold-light: #D8BA83;

            --text: #F4EFE8;

            --muted: #9CA3A8;

            --muted-light: #C4C8CA;

            --green: #79B89A;

            --red: #D47D85;
        }


        body {

            margin: 0;

            min-height: 100vh;

            font-family:
                "Segoe UI",
                Arial,
                sans-serif;

            background: var(--bg);

            color: var(--text);
        }


        a {
            text-decoration: none;
        }


        /* =========================================================
           MAIN CONTAINER
        ========================================================= */

        .container {

            width: 94%;

            max-width: 1150px;

            margin: 35px auto 55px;
        }


        /* =========================================================
           HEADER
        ========================================================= */

        .header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

            margin-bottom: 24px;

            flex-wrap: wrap;
        }


        .title-area {

            display: flex;

            align-items: center;

            gap: 15px;
        }


        .title-icon {

            width: 50px;

            height: 50px;

            border-radius: 14px;

            background:
                rgba(100, 28, 40, 0.20);

            border:
                1px solid
                rgba(201, 166, 107, 0.22);

            color: var(--gold);

            display: grid;

            place-items: center;

            flex-shrink: 0;
        }


        .title-icon svg {

            width: 27px;

            height: 27px;

            fill: none;

            stroke: currentColor;

            stroke-width: 1.7;

            stroke-linecap: round;

            stroke-linejoin: round;
        }


        .title-text h1 {

            margin: 0;

            color: var(--text);

            font-size: 23px;

            font-weight: 600;
        }


        .title-text p {

            margin: 4px 0 0;

            color: var(--muted);

            font-size: 13px;
        }


        /* =========================================================
           ACTION BUTTONS
        ========================================================= */

        .actions {

            display: flex;

            gap: 9px;

            flex-wrap: wrap;
        }


        .back-button,
        .print-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            border: 1px solid transparent;

            padding: 10px 15px;

            border-radius: 9px;

            font-size: 13px;

            font-weight: 600;

            cursor: pointer;

            transition:
                background 0.2s ease,
                border-color 0.2s ease,
                color 0.2s ease,
                transform 0.2s ease;
        }


        .back-button {

            background:
                rgba(100, 28, 40, 0.85);

            border-color:
                rgba(122, 38, 53, 0.7);

            color: var(--text);
        }


        .print-button {

            background:
                #15191c;

            border-color:
                var(--border);

            color: var(--muted-light);
        }


        .back-button svg,
        .print-button svg {

            width: 16px;

            height: 16px;

            fill: none;

            stroke: currentColor;

            stroke-width: 1.8;

            stroke-linecap: round;

            stroke-linejoin: round;
        }


        .back-button:hover {

            background: var(--maroon-hover);

            border-color:
                rgba(201, 166, 107, 0.32);

            transform: translateY(-1px);
        }


        .print-button:hover {

            background:
                rgba(201, 166, 107, 0.08);

            border-color:
                rgba(201, 166, 107, 0.25);

            color: var(--gold);

            transform: translateY(-1px);
        }


        /* =========================================================
           CARDS
        ========================================================= */

        .card {

            background:
                linear-gradient(
                    145deg,
                    var(--panel),
                    #0e1113
                );

            border:
                1px solid var(--border);

            border-radius: 16px;

            padding: 25px;

            margin-bottom: 20px;

            position: relative;

            overflow: hidden;
        }


        .card::before {

            content: "";

            position: absolute;

            top: 0;

            left: 0;

            width: 100%;

            height: 2px;

            background:
                linear-gradient(
                    90deg,
                    var(--maroon),
                    rgba(201, 166, 107, 0.65),
                    transparent
                );
        }


        .card h2 {

            margin: 0 0 18px;

            color: var(--text);

            font-size: 17px;

            font-weight: 600;

            display: flex;

            align-items: center;

            gap: 9px;
        }


        .section-icon {

            width: 31px;

            height: 31px;

            border-radius: 9px;

            background:
                rgba(100, 28, 40, 0.20);

            color: var(--gold);

            display: grid;

            place-items: center;
        }


        .section-icon svg {

            width: 17px;

            height: 17px;

            fill: none;

            stroke: currentColor;

            stroke-width: 1.7;

            stroke-linecap: round;

            stroke-linejoin: round;
        }


        /* =========================================================
           EMPLOYEE INFORMATION
        ========================================================= */

        .employee-info {

            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 12px;
        }


        .info-box {

            background:
                rgba(21, 25, 28, 0.82);

            border:
                1px solid
                rgba(40, 45, 49, 0.9);

            padding: 15px;

            border-radius: 11px;

            min-width: 0;
        }


        .info-box:hover {

            border-color:
                rgba(201, 166, 107, 0.20);
        }


        .label {

            font-size: 11px;

            color: var(--muted);

            margin-bottom: 7px;

            text-transform: uppercase;

            letter-spacing: 0.5px;
        }


        .value {

            color: var(--text);

            font-size: 14px;

            font-weight: 600;

            overflow-wrap: anywhere;
        }


        .employee-id {

            color: var(--gold);
        }


        .payroll-id {

            color: var(--gold);
        }


        /* =========================================================
           TABLE WRAPPER
        ========================================================= */

        .table-wrapper {

            overflow-x: auto;

            border:
                1px solid
                var(--border);

            border-radius: 11px;
        }


        /* =========================================================
           TABLE
        ========================================================= */

        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 600px;
        }


        th {

            background:
                linear-gradient(
                    135deg,
                    var(--maroon-dark),
                    var(--maroon)
                );

            color: #F8F2EA;

            padding: 13px 14px;

            text-align: left;

            font-size: 12px;

            font-weight: 600;

            letter-spacing: 0.25px;

            white-space: nowrap;
        }


        td {

            padding: 13px 14px;

            border-bottom:
                1px solid
                rgba(40, 45, 49, 0.75);

            color: var(--muted-light);

            font-size: 13px;

            background: transparent;
        }


        tbody tr {

            transition:
                background 0.18s ease;
        }


        tbody tr:hover {

            background:
                rgba(100, 28, 40, 0.10);
        }


        tbody tr:last-child td {

            border-bottom: none;
        }


        .amount {

            text-align: right;

            font-variant-numeric:
                tabular-nums;

            color: var(--muted-light);

            white-space: nowrap;
        }


        /* =========================================================
           EARNING COLORS
        ========================================================= */

        .earnings-table td:last-child {

            color: var(--green);

            font-weight: 600;
        }


        .gross-row td {

            background:
                rgba(121, 184, 154, 0.05);

            color: var(--text);

            font-weight: 700;
        }


        .gross-row td:last-child {

            color: var(--green);
        }


        /* =========================================================
           DEDUCTION COLORS
        ========================================================= */

        .deduction-row td:last-child {

            color: var(--red);

            font-weight: 600;
        }


        .deduction-total td {

            background:
                rgba(212, 125, 133, 0.05);

            color: var(--text);

            font-weight: 700;
        }


        .deduction-total td:last-child {

            color: var(--red);
        }


        .no-deduction {

            color: var(--muted);

            text-align: center;

            padding: 22px;
        }


        /* =========================================================
           PAYROLL SUMMARY
        ========================================================= */

        .summary {

            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 13px;
        }


        .summary-box {

            padding: 20px;

            border-radius: 12px;

            background:
                rgba(21, 25, 28, 0.85);

            border:
                1px solid var(--border);
        }


        .summary-box.gross {

            border-color:
                rgba(121, 184, 154, 0.20);
        }


        .summary-box.deduction {

            border-color:
                rgba(212, 125, 133, 0.20);
        }


        .summary-box.net {

            background:
                linear-gradient(
                    145deg,
                    rgba(100, 28, 40, 0.32),
                    rgba(201, 166, 107, 0.07)
                );

            border-color:
                rgba(201, 166, 107, 0.32);
        }


        .summary-label {

            color: var(--muted);

            font-size: 12px;

            margin-bottom: 8px;

            text-transform: uppercase;

            letter-spacing: 0.5px;
        }


        .summary-value {

            font-size: 22px;

            font-weight: 700;

            color: var(--text);

            font-variant-numeric:
                tabular-nums;
        }


        .summary-box.gross .summary-value {

            color: var(--green);
        }


        .summary-box.deduction .summary-value {

            color: var(--red);
        }


        .summary-box.net .summary-value {

            color: var(--gold);
        }


        /* =========================================================
           FOOTER INFORMATION
        ========================================================= */

        .created-info {

            margin-top: 8px;

            color: var(--muted);

            font-size: 12px;

            text-align: right;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 850px) {

            .employee-info {

                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .summary {

                grid-template-columns: 1fr;
            }

        }


        @media (max-width: 650px) {

            .container {

                width: 94%;

                margin-top: 25px;
            }


            .header {

                align-items: stretch;

                flex-direction: column;
            }


            .title-area {

                align-items: flex-start;
            }


            .title-text h1 {

                font-size: 20px;
            }


            .actions {

                width: 100%;
            }


            .back-button,
            .print-button {

                flex: 1;

                min-width: 0;
            }


            .employee-info {

                grid-template-columns: 1fr;
            }


            .card {

                padding: 20px;

                border-radius: 14px;
            }


            .summary-value {

                font-size: 20px;
            }

        }


        /* =========================================================
           PRINT
        ========================================================= */

        @media print {

            body {

                background: white;

                color: #111;
            }


            .container {

                width: 100%;

                max-width: none;

                margin: 0;
            }


            .back-button,
            .print-button {

                display: none;
            }


            .card {

                background: white;

                border: 1px solid #ddd;

                box-shadow: none;

                break-inside: avoid;
            }


            .card::before {

                display: none;
            }


            .card h2 {

                color: #222;
            }


            .info-box,
            .summary-box {

                background: white;

                border: 1px solid #ddd;
            }


            .label,
            .summary-label {

                color: #666;
            }


            .value,
            .summary-value {

                color: #111 !important;
            }


            th {

                background: #641C28 !important;

                color: white !important;

                print-color-adjust: exact;

                -webkit-print-color-adjust: exact;
            }


            td {

                color: #222 !important;

                border-color: #ddd;
            }


            .earnings-table td:last-child,
            .gross-row td:last-child,
            .deduction-row td:last-child,
            .deduction-total td:last-child {

                color: #222 !important;
            }

        }

    </style>

</head>


<body>


<div class="container">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="header">


        <div class="title-area">

            <div class="title-icon">

                <!-- Payroll document icon -->

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >

                    <rect
                        x="4"
                        y="3"
                        width="16"
                        height="18"
                        rx="2"
                    />

                    <path d="M8 7h8"/>

                    <path d="M8 11h8"/>

                    <path d="M8 15h5"/>

                    <path d="M8 18h4"/>

                </svg>

            </div>


            <div class="title-text">

                <h1>
                    Payroll Details
                </h1>

                <p>
                    Detailed payroll information and payment breakdown
                </p>

            </div>

        </div>


        <div class="actions">


            <?php if ($role === "Employee"): ?>

                <a
                    href="employee_payroll.php"
                    class="back-button"
                >

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >

                        <path d="M19 12H5"/>

                        <path d="M12 19l-7-7 7-7"/>

                    </svg>

                    My Payroll

                </a>

            <?php else: ?>

                <a
                    href="payroll_records.php"
                    class="back-button"
                >

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >

                        <path d="M19 12H5"/>

                        <path d="M12 19l-7-7 7-7"/>

                    </svg>

                    Payroll Records

                </a>

            <?php endif; ?>


            <button
                type="button"
                onclick="window.print()"
                class="print-button"
            >

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >

                    <path
                        d="M6 9V3h12v6"
                    />

                    <path
                        d="M6 18H4a2 2 0 0 1-2-2v-5
                        a2 2 0 0 1 2-2h16
                        a2 2 0 0 1 2 2v5
                        a2 2 0 0 1-2 2h-2"
                    />

                    <rect
                        x="6"
                        y="14"
                        width="12"
                        height="7"
                    />

                    <path d="M17 12h.01"/>

                </svg>

                Print

            </button>


        </div>

    </div>


    <!-- =====================================================
         EMPLOYEE INFORMATION
    ====================================================== -->

    <div class="card">


        <h2>

            <span class="section-icon">

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >

                    <circle
                        cx="12"
                        cy="8"
                        r="4"
                    />

                    <path
                        d="M4 21a8 8 0 0 1 16 0"
                    />

                </svg>

            </span>

            Employee Information

        </h2>


        <div class="employee-info">


            <!-- Employee ID -->

            <div class="info-box">

                <div class="label">
                    Employee ID
                </div>

                <div class="value employee-id">

                    <?php

                    echo (int)$payroll["Emp_ID"];

                    ?>

                </div>

            </div>


            <!-- Full Name -->

            <div class="info-box">

                <div class="label">
                    Full Name
                </div>

                <div class="value">

                    <?php

                    echo htmlspecialchars(
                        $payroll["First_Name"] .
                        " " .
                        $payroll["Last_Name"]
                    );

                    ?>

                </div>

            </div>


            <!-- Email -->

            <div class="info-box">

                <div class="label">

                    Email
                    
                </div>

                <div class="value">

                    <?php

                    $email = $payroll["Email"] ?? "";

                    if (!empty($email)) {
                        $email = decrypt_data($email);
                    }

                    echo htmlspecialchars(
                        $email ?: "Not provided"
                    );

                    ?>

                </div>

            </div>


            <!-- Pay Period -->

            <div class="info-box">

                <div class="label">
                    Pay Period
                </div>

                <div class="value">

                    <?php

                    echo htmlspecialchars(
                        $payroll["Pay_Period"]
                    );

                    ?>

                </div>

            </div>


            <!-- Pay Date -->

            <div class="info-box">

                <div class="label">
                    Pay Date
                </div>

                <div class="value">

                    <?php

                    echo htmlspecialchars(
                        $payroll["Pay_Date"]
                    );

                    ?>

                </div>

            </div>


            <!-- Payroll ID -->

            <div class="info-box">

                <div class="label">
                    Payroll ID
                </div>

                <div class="value payroll-id">

                    #<?php

                    echo (int)$payroll["Payroll_ID"];

                    ?>

                </div>

            </div>


        </div>

    </div>


    <!-- =====================================================
         EARNINGS
    ====================================================== -->

    <div class="card">


        <h2>

            <span class="section-icon">

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >

                    <path
                        d="M12 2v20"
                    />

                    <path
                        d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14.5
                        a3.5 3.5 0 0 1 0 7H6"
                    />

                </svg>

            </span>

            Earnings

        </h2>


        <div class="table-wrapper">

            <table class="earnings-table">

                <thead>

                    <tr>

                        <th>
                            Description
                        </th>

                        <th class="amount">
                            Amount
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <!-- BASIC SALARY -->

                    <tr>

                        <td>
                            Basic Salary
                        </td>

                        <td class="amount">

                            ₱<?php

                            echo number_format(
                                (float)$payroll["Basic_Salary"],
                                2
                            );

                            ?>

                        </td>

                    </tr>


                    <!-- ALLOWANCE -->

                    <?php if ((float)$payroll["Allowance"] > 0): ?>

                        <tr>

                            <td>
                                Allowance
                            </td>

                            <td class="amount">

                                ₱<?php

                                echo number_format(
                                    (float)$payroll["Allowance"],
                                    2
                                );

                                ?>

                            </td>

                        </tr>

                    <?php endif; ?>


                    <!-- OVERTIME -->

                    <?php if ((float)$payroll["Overtime"] > 0): ?>

                        <tr>

                            <td>
                                Overtime
                            </td>

                            <td class="amount">

                                ₱<?php

                                echo number_format(
                                    (float)$payroll["Overtime"],
                                    2
                                );

                                ?>

                            </td>

                        </tr>

                    <?php endif; ?>


                    <!-- ADDITIONAL EARNINGS -->

                    <?php foreach ($earnings as $earning): ?>

                        <tr>

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $earning["Description"]
                                    ?? "Additional Earning"
                                );

                                ?>

                            </td>

                            <td class="amount">

                                ₱<?php

                                echo number_format(
                                    (float)$earning["Amount"],
                                    2
                                );

                                ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>


                    <!-- TOTAL GROSS -->

                    <tr class="gross-row">

                        <td>
                            Total Gross
                        </td>

                        <td class="amount">

                            ₱<?php

                            echo number_format(
                                (float)$payroll["Total_Gross"],
                                2
                            );

                            ?>

                        </td>

                    </tr>


                </tbody>

            </table>

        </div>

    </div>


    <!-- =====================================================
         DEDUCTIONS
    ====================================================== -->

    <div class="card">


        <h2>

            <span class="section-icon">

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >

                    <path d="M5 12h14"/>

                    <path d="M12 5v14"/>

                </svg>

            </span>

            Deductions

        </h2>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Description
                        </th>

                        <th class="amount">
                            Amount
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php if (count($deductions) > 0): ?>


                        <?php foreach ($deductions as $deduction): ?>


                            <tr class="deduction-row">


                                <td>

                                    <?php

                                    if (
                                        !empty(
                                            $deduction["Deduction_Name"]
                                        )
                                    ) {

                                        echo htmlspecialchars(
                                            $deduction[
                                                "Deduction_Name"
                                            ]
                                        );

                                    } elseif (
                                        !empty(
                                            $deduction["Description"]
                                        )
                                    ) {

                                        echo htmlspecialchars(
                                            $deduction[
                                                "Description"
                                            ]
                                        );

                                    } else {

                                        echo "Deduction";

                                    }

                                    ?>

                                </td>


                                <td class="amount">

                                    ₱<?php

                                    echo number_format(
                                        (float)$deduction["Amount"],
                                        2
                                    );

                                    ?>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="2"
                                class="no-deduction"
                            >

                                No deductions recorded.

                            </td>

                        </tr>


                    <?php endif; ?>


                    <!-- TOTAL DEDUCTIONS -->

                    <tr class="deduction-total">

                        <td>
                            Total Deductions
                        </td>

                        <td class="amount">

                            ₱<?php

                            echo number_format(
                                (float)$payroll[
                                    "Total_Deductions"
                                ],
                                2
                            );

                            ?>

                        </td>

                    </tr>


                </tbody>

            </table>

        </div>

    </div>


    <!-- =====================================================
         PAYROLL SUMMARY
    ====================================================== -->

    <div class="card">


        <h2>

            <span class="section-icon">

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >

                    <path
                        d="M4 19V5"
                    />

                    <path
                        d="M4 19h16"
                    />

                    <path
                        d="M7 15l4-5 3 3 5-7"
                    />

                </svg>

            </span>

            Payroll Summary

        </h2>


        <div class="summary">


            <!-- TOTAL GROSS -->

            <div class="summary-box gross">

                <div class="summary-label">
                    Total Gross
                </div>

                <div class="summary-value">

                    ₱<?php

                    echo number_format(
                        (float)$payroll["Total_Gross"],
                        2
                    );

                    ?>

                </div>

            </div>


            <!-- TOTAL DEDUCTIONS -->

            <div class="summary-box deduction">

                <div class="summary-label">
                    Total Deductions
                </div>

                <div class="summary-value">

                    ₱<?php

                    echo number_format(
                        (float)$payroll["Total_Deductions"],
                        2
                    );

                    ?>

                </div>

            </div>


            <!-- NET PAY -->

            <div class="summary-box net">

                <div class="summary-label">
                    Net Pay
                </div>

                <div class="summary-value">

                    ₱<?php

                    echo number_format(
                        (float)$payroll["Net_Pay"],
                        2
                    );

                    ?>

                </div>

            </div>


        </div>


        <div class="created-info">

            Payroll ID #<?php echo (int)$payroll["Payroll_ID"]; ?>

            <?php if (!empty($payroll["Created_At"])): ?>

                &nbsp; • &nbsp;

                Created
                <?php

                echo htmlspecialchars(
                    date(
                        "M d, Y h:i A",
                        strtotime(
                            $payroll["Created_At"]
                        )
                    )
                );

                ?>

            <?php endif; ?>

        </div>


    </div>


</div>


</body>

</html>