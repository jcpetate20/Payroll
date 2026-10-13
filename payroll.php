<?php

session_start();

require_once "db.php";
require_once "csrf.php";
require_once "session_security.php";

if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}

$allowed_roles = [
    "Administrator",
    "HR Staff",
    "Payroll Staff"
];

if (!in_array($_SESSION["Role"], $allowed_roles)) {

    http_response_code(403);

    echo "<!DOCTYPE html>";
    echo "<html>";
    echo "<head>";
    echo "<title>Access Denied</title>";
    echo "<style>";

    echo "
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #090b0d;
            color: #F4EFE8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .box {
            width: 100%;
            max-width: 500px;
            background: #111417;
            border: 1px solid #282d31;
            padding: 40px;
            border-radius: 16px;
            text-align: center;
        }

        h1 {
            color: #D47D85;
            margin: 0 0 12px;
        }

        p {
            color: #9CA3A8;
        }

        a {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            background: #641C28;
            color: #F4EFE8;
            padding: 12px 22px;
            border-radius: 8px;
        }

        a:hover {
            background: #7A2635;
        }
    ";

    echo "</style>";
    echo "</head>";
    echo "<body>";

    echo "<div class='box'>";
    echo "<h1>Access Denied</h1>";
    echo "<p>You do not have permission to access Payroll.</p>";
    echo "<a href='dashboard.php'>Back to Dashboard</a>";
    echo "</div>";

    echo "</body>";
    echo "</html>";

    exit;
}

$employees = $conn->query("
    SELECT Emp_ID, First_Name, Last_Name
    FROM employee
    ORDER BY Last_Name, First_Name
");

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Payroll | Payroll & Benefits</title>

    <style>

        * {
            box-sizing: border-box;
        }

        :root {
            --bg: #090b0d;
            --panel: #111417;
            --panel-light: #15191c;
            --border: #282d31;

            --maroon: #641C28;
            --maroon-hover: #7A2635;

            --gold: #C9A66B;

            --text: #F4EFE8;
            --muted: #9CA3A8;
            --muted-light: #C4C8CA;

            --green: #79B89A;
            --red: #D47D85;

            --blue: #7C9BC5;
            --pink: #B77D9B;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        /* =========================
           TOP HEADER
        ========================= */

        .top-header {
            min-height: 74px;
            background: #0d1013;
            border-bottom: 1px solid var(--border);

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 14px 32px;
            gap: 20px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .brand-icon {
            width: 43px;
            height: 43px;

            border-radius: 13px;

            background: rgba(201, 166, 107, 0.12);
            color: var(--gold);

            display: grid;
            place-items: center;

            border: 1px solid rgba(201, 166, 107, 0.22);

            flex-shrink: 0;
        }

        .brand-icon svg {
            width: 25px;
            height: 25px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .brand-text {
            min-width: 0;
        }

        .brand-text h1 {
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
        }

        .brand-text p {
            margin: 3px 0 0;
            font-size: 11px;
            color: var(--muted);
        }

        .user-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;

            border-radius: 50%;

            background: linear-gradient(
                135deg,
                #7A2635,
                #C9A66B
            );

            color: white;

            display: grid;
            place-items: center;

            font-size: 14px;
            font-weight: 600;
        }

        .user-info {
            line-height: 1.2;
        }

        .user-name {
            font-size: 12px;
            color: var(--text);
            font-weight: 500;
        }

        .user-role {
            margin-top: 4px;
            font-size: 10px;
            color: var(--muted);
        }

        /* =========================
           MAIN
        ========================= */

        .page-container {
            width: 94%;
            max-width: 1150px;
            margin: 35px auto 60px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            color: var(--muted-light);
            text-decoration: none;

            font-size: 12px;

            margin-bottom: 20px;

            transition: color 0.2s ease;
        }

        .back-link:hover {
            color: var(--gold);
        }

        .back-link svg {
            width: 16px;
            height: 16px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* =========================
           PAGE TITLE
        ========================= */

        .page-heading {
            margin-bottom: 25px;
        }

        .eyebrow {
            color: var(--gold);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .page-heading h2 {
            margin: 0;
            font-family: Georgia, serif;
            font-size: 32px;
            font-weight: 400;
            color: var(--text);
        }

        .page-heading p {
            margin: 9px 0 0;
            max-width: 700px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
        }

        /* =========================
           MAIN FORM CARD
        ========================= */

        .payroll-card {
            background:
                linear-gradient(
                    135deg,
                    rgba(21, 25, 28, 0.98),
                    rgba(17, 20, 23, 0.98)
                );

            border: 1px solid var(--border);
            border-radius: 16px;

            padding: 30px;

            box-shadow:
                0 18px 50px rgba(0, 0, 0, 0.25);
        }

        /* =========================
           SECTION
        ========================= */

        .section {
            margin-top: 30px;
            padding-top: 25px;

            border-top: 1px solid var(--border);
        }

        .section:first-child {
            margin-top: 0;
            padding-top: 0;
            border-top: none;
        }

        .section-heading {
            display: flex;
            align-items: center;
            gap: 12px;

            margin-bottom: 22px;
        }

        .section-icon {
            width: 38px;
            height: 38px;

            border-radius: 10px;

            background: rgba(100, 28, 40, 0.18);
            color: #C98793;

            display: grid;
            place-items: center;

            border: 1px solid rgba(100, 28, 40, 0.4);

            flex-shrink: 0;
        }

        .section-icon.gold {
            background: rgba(201, 166, 107, 0.12);
            color: var(--gold);
            border-color: rgba(201, 166, 107, 0.22);
        }

        .section-icon.green {
            background: rgba(121, 184, 154, 0.10);
            color: var(--green);
            border-color: rgba(121, 184, 154, 0.20);
        }

        .section-icon.blue {
            background: rgba(124, 155, 197, 0.10);
            color: var(--blue);
            border-color: rgba(124, 155, 197, 0.20);
        }

        .section-icon svg {
            width: 20px;
            height: 20px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .section-title h3 {
            margin: 0;

            font-size: 15px;
            font-weight: 600;

            color: var(--text);
        }

        .section-title p {
            margin: 4px 0 0;

            font-size: 11px;
            color: var(--muted);
        }

        /* =========================
           FORM
        ========================= */

        .form-group {
            margin-bottom: 20px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        label {
            display: block;

            margin-bottom: 8px;

            color: var(--muted-light);

            font-size: 12px;
            font-weight: 600;
        }

        .required {
            color: #C98793;
        }

        input,
        select {
            width: 100%;

            min-height: 45px;

            padding: 11px 13px;

            border: 1px solid var(--border);
            border-radius: 9px;

            background: #0d1013;
            color: var(--text);

            font-family: Arial, sans-serif;
            font-size: 13px;

            transition:
                border-color 0.2s ease,
                background 0.2s ease,
                box-shadow 0.2s ease;
        }

        input::placeholder {
            color: #666d72;
        }

        input:hover,
        select:hover {
            border-color: #383e43;
        }

        input:focus,
        select:focus {
            outline: none;

            border-color: var(--maroon-hover);

            background: #101316;

            box-shadow:
                0 0 0 3px rgba(100, 28, 40, 0.16);
        }

        select {
            cursor: pointer;
        }

        option {
            background: #111417;
            color: var(--text);
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* =========================
           EARNING / DEDUCTION ROWS
        ========================= */

        .item-row {
            display: grid;

            grid-template-columns: minmax(0, 1fr) 180px 45px;

            gap: 10px;

            margin-bottom: 10px;
        }

        .item-row input {
            margin: 0;
        }

        .remove-btn {
            width: 45px;
            height: 45px;

            border: 1px solid rgba(212, 125, 133, 0.25);
            border-radius: 9px;

            background: rgba(212, 125, 133, 0.08);
            color: var(--red);

            cursor: pointer;

            display: grid;
            place-items: center;

            transition:
                background 0.2s ease,
                border-color 0.2s ease;
        }

        .remove-btn:hover {
            background: rgba(212, 125, 133, 0.16);
            border-color: rgba(212, 125, 133, 0.4);
        }

        .remove-btn svg {
            width: 17px;
            height: 17px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
        }

        /* =========================
           ADD BUTTON
        ========================= */

        .add-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            margin-top: 5px;

            min-height: 40px;

            padding: 9px 14px;

            border: 1px solid rgba(100, 28, 40, 0.55);
            border-radius: 8px;

            background: rgba(100, 28, 40, 0.15);
            color: #D8A0A8;

            cursor: pointer;

            font-size: 12px;
            font-weight: 600;

            transition:
                background 0.2s ease,
                border-color 0.2s ease,
                color 0.2s ease;
        }

        .add-btn:hover {
            background: rgba(100, 28, 40, 0.28);
            border-color: var(--maroon-hover);
            color: var(--text);
        }

        .add-btn svg {
            width: 16px;
            height: 16px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
        }

        /* =========================
           SUMMARY
        ========================= */

        .summary {
            margin-top: 30px;

            padding: 22px;

            border-radius: 12px;

            background:
                linear-gradient(
                    135deg,
                    rgba(100, 28, 40, 0.12),
                    rgba(201, 166, 107, 0.05)
                );

            border: 1px solid var(--border);
        }

        .summary-heading {
            display: flex;
            align-items: center;
            gap: 10px;

            margin-bottom: 14px;
        }

        .summary-heading svg {
            width: 18px;
            height: 18px;

            fill: none;
            stroke: var(--gold);
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .summary-heading span {
            font-size: 12px;
            font-weight: 600;
            color: var(--muted-light);
        }

        .summary-row {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 20px;

            padding: 12px 0;

            border-bottom: 1px solid rgba(40, 45, 49, 0.65);

            font-size: 13px;
        }

        .summary-row span {
            color: var(--muted);
        }

        .summary-row strong {
            color: var(--text);
            font-weight: 600;
        }

        .summary-row.total {
            border-bottom: none;

            margin-top: 5px;
            padding-top: 18px;

            font-size: 16px;
        }

        .summary-row.total span {
            color: var(--text);
            font-weight: 600;
        }

        .summary-row.total strong {
            color: var(--gold);
            font-size: 23px;
        }

        /* =========================
           SAVE BUTTON
        ========================= */

        .save-btn {
            width: 100%;

            min-height: 50px;

            margin-top: 25px;

            border: 1px solid #7A2635;
            border-radius: 9px;

            background:
                linear-gradient(
                    135deg,
                    #641C28,
                    #7A2635
                );

            color: #fff;

            font-size: 13px;
            font-weight: 700;

            cursor: pointer;

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }

        .save-btn:hover {
            background:
                linear-gradient(
                    135deg,
                    #7A2635,
                    #8A3040
                );

            box-shadow:
                0 8px 25px rgba(100, 28, 40, 0.28);

            transform: translateY(-1px);
        }

        .save-btn:active {
            transform: translateY(0);
        }

        .save-content {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
        }

        .save-content svg {
            width: 18px;
            height: 18px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 800px) {

            .top-header {
                padding: 14px 20px;
            }

            .user-info {
                display: none;
            }

            .page-container {
                width: 92%;
                margin-top: 25px;
            }

            .payroll-card {
                padding: 22px;
            }

            .row {
                grid-template-columns: 1fr;
                gap: 0;
            }

        }

        @media (max-width: 600px) {

            .brand-text p {
                display: none;
            }

            .page-heading h2 {
                font-size: 27px;
            }

            .payroll-card {
                padding: 18px;
                border-radius: 13px;
            }

            .item-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .remove-btn {
                width: 100%;
                height: 40px;
            }

            .summary-row.total strong {
                font-size: 20px;
            }

        }

    </style>

</head>

<body>

    <!-- TOP HEADER -->

    <header class="top-header">

        <div class="header-left">

            <div class="brand-icon">

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-3 4v2"/>
                    <circle cx="9.5" cy="7" r="4"/>
                    <path d="M17 11a4 4 0 1 0 0-8"/>
                    <path d="M21 21v-2a4 4 0 0 0-3-3.87"/>
                </svg>

            </div>

            <div class="brand-text">

                <h1>Payroll &amp; Benefits</h1>

                <p>Management System</p>

            </div>

        </div>


        <div class="user-area">

            <div class="user-info">

                <div class="user-name">
                    <?php echo htmlspecialchars($_SESSION["Full_Name"] ?? "User"); ?>
                </div>

                <div class="user-role">
                    <?php echo htmlspecialchars($_SESSION["Role"] ?? ""); ?>
                </div>

            </div>

            <div class="user-avatar">

                <?php
                $full_name = $_SESSION["Full_Name"] ?? "U";
                $name_parts = preg_split('/\s+/', trim($full_name));

                $initials = "";

                if (!empty($name_parts[0])) {
                    $initials .= strtoupper(substr($name_parts[0], 0, 1));
                }

                if (count($name_parts) > 1) {
                    $last_index = count($name_parts) - 1;
                    $initials .= strtoupper(substr($name_parts[$last_index], 0, 1));
                }

                echo htmlspecialchars($initials);
                ?>

            </div>

        </div>

    </header>


    <!-- MAIN -->

    <main class="page-container">

        <a
            href="dashboard.php"
            class="back-link"
        >

            <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path d="M19 12H5"/>
                <path d="M12 19l-7-7 7-7"/>
            </svg>

            Back to Dashboard

        </a>


        <div class="page-heading">

            <div class="eyebrow">
                Payroll Management
            </div>

            <h2>
                Create Payroll
            </h2>

            <p>
                Create a payroll record with your own salary,
                earnings, benefits, and deductions.
                All payroll amounts can be customized.
            </p>

        </div>


        <!-- PAYROLL FORM -->

        <form
            action="save_payroll.php"
            method="POST"
            id="payrollForm"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars(csrf_token()); ?>"
            >


            <div class="payroll-card">


                <!-- EMPLOYEE INFORMATION -->

                <div class="section">

                    <div class="section-heading">

                        <div class="section-icon">

                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-3 4v2"/>
                                <circle cx="9.5" cy="7" r="4"/>
                                <path d="M17 11a4 4 0 1 0 0-8"/>
                                <path d="M21 21v-2a4 4 0 0 0-3-3.87"/>
                            </svg>

                        </div>

                        <div class="section-title">

                            <h3>
                                Employee Information
                            </h3>

                            <p>
                                Select the employee and payroll period.
                            </p>

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="employee">
                            Employee <span class="required">*</span>
                        </label>

                        <select
                            id="employee"
                            name="employee_id"
                            required
                        >

                            <option value="">
                                -- Select Employee --
                            </option>

                            <?php while ($employee = $employees->fetch_assoc()): ?>

                                <option
                                    value="<?php echo $employee['Emp_ID']; ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $employee['Last_Name'] .
                                        ", " .
                                        $employee['First_Name']
                                    );
                                    ?>

                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>


                    <div class="row">

                        <div class="form-group">

                            <label for="pay_period">
                                Pay Period <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                id="pay_period"
                                name="pay_period"
                                placeholder="Example: January 1-15, 2026"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="pay_date">
                                Pay Date <span class="required">*</span>
                            </label>

                            <input
                                type="date"
                                id="pay_date"
                                name="pay_date"
                                required
                            >

                        </div>

                    </div>

                </div>


                <!-- BASIC SALARY -->

                <div class="section">

                    <div class="section-heading">

                        <div class="section-icon gold">

                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <rect
                                    x="3"
                                    y="6"
                                    width="18"
                                    height="12"
                                    rx="2"
                                />
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="2.5"
                                />
                                <path d="M7 10h.01"/>
                                <path d="M17 14h.01"/>
                            </svg>

                        </div>

                        <div class="section-title">

                            <h3>
                                Basic Salary
                            </h3>

                            <p>
                                Enter the employee's base salary.
                            </p>

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="basic_salary">
                            Basic Salary <span class="required">*</span>
                        </label>

                        <input
                            type="number"
                            id="basic_salary"
                            name="basic_salary"
                            min="0"
                            step="0.01"
                            value="0"
                            oninput="calculatePayroll()"
                            required
                        >

                    </div>

                </div>


                <!-- ADDITIONAL EARNINGS -->

                <div class="section">

                    <div class="section-heading">

                        <div class="section-icon green">

                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="9"
                                />
                                <path d="M12 8v8"/>
                                <path d="M8 12h8"/>
                            </svg>

                        </div>

                        <div class="section-title">

                            <h3>
                                Additional Earnings / Benefits
                            </h3>

                            <p>
                                Add overtime, allowances, bonuses, or other earnings.
                            </p>

                        </div>

                    </div>


                    <div id="earningsContainer">

                        <div class="item-row earning-row">

                            <input
                                type="text"
                                class="earning-description"
                                name="earning_description[]"
                                placeholder="Example: Overtime, Allowance, Bonus"
                            >

                            <input
                                type="number"
                                class="earning-amount"
                                name="earning_amount[]"
                                placeholder="Amount"
                                min="0"
                                step="0.01"
                                value="0"
                            >

                            <button
                                type="button"
                                class="remove-btn"
                                onclick="removeRow(this)"
                                aria-label="Remove earning"
                            >

                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path d="M6 6l12 12"/>
                                    <path d="M18 6L6 18"/>
                                </svg>

                            </button>

                        </div>

                    </div>


                    <button
                        type="button"
                        class="add-btn"
                        onclick="addEarning()"
                    >

                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                cx="12"
                                cy="12"
                                r="9"
                            />
                            <path d="M12 8v8"/>
                            <path d="M8 12h8"/>
                        </svg>

                        Add Earning / Benefit

                    </button>

                </div>


                <!-- DEDUCTIONS -->

                <div class="section">

                    <div class="section-heading">

                        <div class="section-icon">

                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <rect
                                    x="3"
                                    y="5"
                                    width="18"
                                    height="14"
                                    rx="2"
                                />
                                <path d="M7 9h10"/>
                                <path d="M7 13h5"/>
                                <path d="M7 16h3"/>
                            </svg>

                        </div>

                        <div class="section-title">

                            <h3>
                                Deductions
                            </h3>

                            <p>
                                Add government contributions, loans, taxes, or other deductions.
                            </p>

                        </div>

                    </div>


                    <div id="deductionsContainer">

                        <div class="item-row deduction-row">

                            <input
                                type="text"
                                class="deduction-description"
                                name="deduction_description[]"
                                placeholder="Example: SSS, PhilHealth, Loan"
                            >

                            <input
                                type="number"
                                class="deduction-amount"
                                name="deduction_amount[]"
                                placeholder="Amount"
                                min="0"
                                step="0.01"
                                value="0"
                            >

                            <button
                                type="button"
                                class="remove-btn"
                                onclick="removeRow(this)"
                                aria-label="Remove deduction"
                            >

                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path d="M6 6l12 12"/>
                                    <path d="M18 6L6 18"/>
                                </svg>

                            </button>

                        </div>

                    </div>


                    <button
                        type="button"
                        class="add-btn"
                        onclick="addDeduction()"
                    >

                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                cx="12"
                                cy="12"
                                r="9"
                            />
                            <path d="M12 8v8"/>
                            <path d="M8 12h8"/>
                        </svg>

                        Add Deduction

                    </button>

                </div>


                <!-- PAYROLL SUMMARY -->

                <div class="summary">

                    <div class="summary-heading">

                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <rect
                                x="3"
                                y="5"
                                width="18"
                                height="14"
                                rx="2"
                            />
                            <path d="M7 9h10"/>
                            <path d="M7 13h4"/>
                            <path d="M7 16h7"/>
                        </svg>

                        <span>
                            Payroll Summary
                        </span>

                    </div>


                    <div class="summary-row">

                        <span>
                            Total Gross
                        </span>

                        <strong id="totalGross">
                            ₱0.00
                        </strong>

                    </div>


                    <div class="summary-row">

                        <span>
                            Total Deductions
                        </span>

                        <strong id="totalDeductions">
                            ₱0.00
                        </strong>

                    </div>


                    <div class="summary-row total">

                        <span>
                            Net Pay
                        </span>

                        <strong id="netPay">
                            ₱0.00
                        </strong>

                    </div>

                </div>


                <!-- SAVE -->

                <button
                    type="submit"
                    class="save-btn"
                >

                    <span class="save-content">

                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path d="M5 4h11l3 3v13H5z"/>
                            <path d="M8 4v6h8V4"/>
                            <path d="M8 20v-6h8v6"/>
                        </svg>

                        Save Payroll

                    </span>

                </button>


            </div>

        </form>

    </main>


<script>

function numberValue(value) {

    const number = parseFloat(value);

    if (isNaN(number)) {
        return 0;
    }

    return number;
}


function formatMoney(amount) {

    return "₱" + amount.toLocaleString("en-PH", {

        minimumFractionDigits: 2,

        maximumFractionDigits: 2

    });

}


function calculatePayroll() {

    const basicSalary = numberValue(
        document.getElementById("basic_salary").value
    );


    let additionalEarnings = 0;

    document
        .querySelectorAll(".earning-amount")
        .forEach(function(input) {

            additionalEarnings += numberValue(input.value);

        });


    let totalDeductions = 0;

    document
        .querySelectorAll(".deduction-amount")
        .forEach(function(input) {

            totalDeductions += numberValue(input.value);

        });


    const totalGross =
        basicSalary + additionalEarnings;


    const netPay =
        totalGross - totalDeductions;


    document.getElementById("totalGross").textContent =
        formatMoney(totalGross);


    document.getElementById("totalDeductions").textContent =
        formatMoney(totalDeductions);


    document.getElementById("netPay").textContent =
        formatMoney(netPay);

}


function addEarning() {

    const container =
        document.getElementById("earningsContainer");


    const row =
        document.createElement("div");


    row.className =
        "item-row earning-row";


    row.innerHTML = `

        <input
            type="text"
            class="earning-description"
            name="earning_description[]"
            placeholder="Example: Bonus, Allowance, Overtime"
        >

        <input
            type="number"
            class="earning-amount"
            name="earning_amount[]"
            placeholder="Amount"
            min="0"
            step="0.01"
            value="0"
        >

        <button
            type="button"
            class="remove-btn"
            onclick="removeRow(this)"
            aria-label="Remove earning"
        >

            <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path d="M6 6l12 12"/>
                <path d="M18 6L6 18"/>
            </svg>

        </button>

    `;


    container.appendChild(row);

    attachCalculationEvents();

}


function addDeduction() {

    const container =
        document.getElementById("deductionsContainer");


    const row =
        document.createElement("div");


    row.className =
        "item-row deduction-row";


    row.innerHTML = `

        <input
            type="text"
            class="deduction-description"
            name="deduction_description[]"
            placeholder="Example: SSS, Loan, Tax"
        >

        <input
            type="number"
            class="deduction-amount"
            name="deduction_amount[]"
            placeholder="Amount"
            min="0"
            step="0.01"
            value="0"
        >

        <button
            type="button"
            class="remove-btn"
            onclick="removeRow(this)"
            aria-label="Remove deduction"
        >

            <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path d="M6 6l12 12"/>
                <path d="M18 6L6 18"/>
            </svg>

        </button>

    `;


    container.appendChild(row);

    attachCalculationEvents();

}


function removeRow(button) {

    const row =
        button.parentElement;

    row.remove();

    calculatePayroll();

}


function attachCalculationEvents() {

    document
        .querySelectorAll(
            ".earning-amount, .deduction-amount"
        )
        .forEach(function(input) {

            input.oninput =
                calculatePayroll;

        });

}


attachCalculationEvents();

calculatePayroll();

</script>

</body>

</html>