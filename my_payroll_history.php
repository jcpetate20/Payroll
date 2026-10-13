<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";
require_once "session_security.php";
require_once "encryption.php";

// Check login
if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}

// Employee only
if (!isset($_SESSION["Role"]) || $_SESSION["Role"] !== "Employee") {
    die("Access denied.");
}

// Ensure employee account is linked
if (empty($_SESSION["Emp_ID"])) {
    die("Your account is not linked to an employee record.");
}

$emp_id = (int) $_SESSION["Emp_ID"];

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// Get employee information
$stmt = $conn->prepare("
    SELECT Emp_ID, First_Name, Last_Name, Email
    FROM employee
    WHERE Emp_ID = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error.");
}

$stmt->bind_param("i", $emp_id);
$stmt->execute();

$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

if (!$employee) {
    die("Employee record not found.");
}

// Decrypt employee email
$decrypted_email = decrypt_data($employee["Email"]);

if ($decrypted_email === false || $decrypted_email === null || $decrypted_email === "") {
    $decrypted_email = "Email unavailable";
}

// Get only this employee's payroll records
$stmt = $conn->prepare("
    SELECT
        Payroll_ID,
        Pay_Period,
        Pay_Date,
        Basic_Salary,
        Total_Gross,
        Total_Deductions,
        Net_Pay
    FROM payroll
    WHERE Emp_ID = ?
    ORDER BY Pay_Date DESC, Payroll_ID DESC
");

if (!$stmt) {
    die("Database error.");
}

$stmt->bind_param("i", $emp_id);
$stmt->execute();

$payroll_result = $stmt->get_result();

$total_records = $payroll_result->num_rows;
$total_gross = 0;
$total_net = 0;
$payroll_rows = [];

while ($row = $payroll_result->fetch_assoc()) {
    $payroll_rows[] = $row;
    $total_gross += (float) $row["Total_Gross"];
    $total_net += (float) $row["Net_Pay"];
}

$stmt->close();

$full_name = trim(
    $employee["First_Name"] . " " . $employee["Last_Name"]
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Payroll History | Payroll and Benefits System</title>

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
            --success: #58c994;
            --success-bg: rgba(40, 167, 100, 0.12);
            --danger: #f08080;
            --danger-bg: rgba(220, 70, 70, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        a {
            color: inherit;
        }

        .container {
            width: 94%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 35px 0 45px;
        }

        /* HEADER */

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 28px;
            padding-bottom: 22px;
            border-bottom: 1px solid var(--border);
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .title-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            border: 1px solid rgba(208, 173, 112, 0.3);
            border-radius: 14px;
            background: rgba(100, 28, 56, 0.25);
            color: var(--gold);
        }

        .title-icon svg {
            width: 26px;
            height: 26px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        h1 {
            margin: 0;
            color: var(--text);
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .subtitle {
            margin: 7px 0 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 18px;
            border: 1px solid rgba(208, 173, 112, 0.18);
            border-radius: 9px;
            background: var(--maroon);
            color: var(--text);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .back-button:hover {
            background: var(--maroon-hover);
            transform: translateY(-1px);
        }

        /* PAGE INTRO */

        .page-intro {
            margin-bottom: 24px;
            padding: 24px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background:
                radial-gradient(
                    ellipse at 90% 50%,
                    rgba(100, 28, 56, 0.25),
                    transparent 45%
                ),
                var(--panel);
        }

        .eyebrow {
            margin-bottom: 10px;
            color: var(--gold);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .page-intro h2 {
            margin: 0;
            color: var(--text);
            font-size: 25px;
        }

        .heading-accent {
            color: var(--gold);
        }

        .page-intro p {
            max-width: 700px;
            margin: 10px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
        }

        /* SUMMARY CARDS */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            min-width: 0;
            padding: 21px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--panel);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            width: 3px;
            background: var(--gold);
        }

        .stat-card.rose::before {
            background: #c98793;
        }

        .stat-card.green::before {
            background: var(--success);
        }

        .stat-label {
            margin-bottom: 10px;
            color: var(--muted);
            font-size: 12px;
        }

        .stat-value {
            color: var(--text);
            font-size: clamp(18px, 2vw, 25px);
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .stat-foot {
            margin-top: 8px;
            color: var(--muted);
            font-size: 11px;
        }

        /* INFORMATION AND PAYROLL CARDS */

        .employee-card,
        .payroll-card {
            margin-bottom: 24px;
            padding: 25px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: var(--panel);
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 22px;
        }

        .section-title-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-icon {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            border: 1px solid rgba(208, 173, 112, 0.25);
            border-radius: 11px;
            background: rgba(100, 28, 56, 0.25);
            color: var(--gold);
            font-size: 20px;
        }

        h3 {
            margin: 0;
            color: var(--text);
            font-size: 19px;
        }

        .section-description {
            margin: 7px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        /* EMPLOYEE INFORMATION */

        .employee-info {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .info-box {
            min-width: 0;
            padding: 19px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--panel-light);
        }

        .label {
            margin-bottom: 9px;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .value {
            color: var(--text);
            font-size: 15px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .employee-id {
            color: var(--gold);
        }

        /* PAYROLL TABLE */

        .table-container {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        .table-scroll {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
        }

        thead {
            background: #21151b;
        }

        th {
            padding: 16px 15px;
            border-bottom: 1px solid var(--border);
            color: var(--gold);
            text-align: left;
            white-space: nowrap;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        td {
            padding: 16px 15px;
            border-bottom: 1px solid var(--border);
            color: var(--text);
            font-size: 13px;
            line-height: 1.6;
            vertical-align: middle;
            white-space: nowrap;
        }

        tbody tr {
            background: var(--panel);
            transition: background 0.2s ease;
        }

        tbody tr:hover {
            background: var(--panel-light);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .amount {
            text-align: right;
            white-space: nowrap;
        }

        .payroll-id {
            color: var(--gold);
            font-weight: 700;
        }

        .net-pay {
            color: var(--gold);
            font-weight: 700;
        }

        .view-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 9px 13px;
            border: 1px solid rgba(208, 173, 112, 0.2);
            border-radius: 8px;
            background: var(--maroon);
            color: var(--text);
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: background 0.2s ease;
        }

        .view-btn:hover {
            background: var(--maroon-hover);
        }

        /* EMPTY STATE */

        .no-records {
            padding: 42px 20px;
            border: 1px dashed #353c46;
            border-radius: 10px;
            background: var(--panel-light);
            color: var(--muted);
            text-align: center;
        }

        .empty-icon {
            display: block;
            margin-bottom: 12px;
            color: var(--gold);
            font-size: 30px;
        }

        .empty-message {
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
        }

        .empty-description {
            margin-top: 7px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        /* FOOTER */

        .footer-note {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 20px;
            padding: 18px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--panel);
        }

        .footer-icon {
            display: grid;
            place-items: center;
            width: 35px;
            height: 35px;
            flex-shrink: 0;
            border: 1px solid rgba(88, 201, 148, 0.2);
            border-radius: 9px;
            background: var(--success-bg);
            color: var(--success);
            font-size: 17px;
        }

        .footer-title {
            color: var(--text);
            font-size: 12px;
            font-weight: 600;
        }

        .footer-text {
            margin-top: 6px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        /* RESPONSIVE */

        @media (max-width: 800px) {
            .container {
                width: 92%;
                padding-top: 24px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .employee-info {
                grid-template-columns: 1fr;
            }

            .employee-card,
            .payroll-card {
                padding: 19px;
            }

            h1 {
                font-size: 23px;
            }

            .back-button {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .header-title {
                align-items: flex-start;
            }

            .title-icon {
                width: 44px;
                height: 44px;
            }

            h1 {
                font-size: 21px;
            }

            .section-heading {
                align-items: flex-start;
            }

            .page-intro {
                padding: 20px;
            }

            .page-intro h2 {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <!-- HEADER -->
    <header class="header">
        <div class="header-title">

            <div class="title-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="15" rx="2"/>
                    <path d="M7 9h10M7 13h4M7 16h7"/>
                    <path d="M7 2v3M17 2v3"/>
                </svg>
            </div>

            <div>
                <h1>My Payroll History</h1>
                <p class="subtitle">
                    Review your previous payroll records and earnings.
                </p>
            </div>
        </div>

        <a href="dashboard.php" class="back-button">
            <span aria-hidden="true">←</span>
            Back to Dashboard
        </a>
    </header>

    <!-- INTRODUCTION -->
    <section class="page-intro">
        <div class="eyebrow">Employee Portal</div>

        <h2>
            My Payroll <span class="heading-accent">History</span>
        </h2>

        <p>
            Review your previous payroll records, track your earnings and
            deductions, and open individual payslips. Only payroll records
            linked to the logged-in employee account are displayed.
        </p>
    </section>

    <!-- SUMMARY CARDS -->
    <section class="stats-grid">

        <div class="stat-card rose">
            <div class="stat-label">My Payroll Records</div>
            <div class="stat-value">
                <?= number_format($total_records) ?>
            </div>
            <div class="stat-foot">Available payroll entries</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Total Gross Pay</div>
            <div class="stat-value">
                ₱<?= number_format($total_gross, 2) ?>
            </div>
            <div class="stat-foot">Across your recorded payrolls</div>
        </div>

        <div class="stat-card green">
            <div class="stat-label">Total Net Pay</div>
            <div class="stat-value">
                ₱<?= number_format($total_net, 2) ?>
            </div>
            <div class="stat-foot">After recorded deductions</div>
        </div>

    </section>

    <!-- EMPLOYEE INFORMATION -->
    <section class="employee-card">

        <div class="section-heading">
            <div class="section-title-group">
                <div class="section-icon" aria-hidden="true">♙</div>

                <div>
                    <h3>
                        Employee <span class="heading-accent">Information</span>
                    </h3>
                    <p class="section-description">
                        Your linked employee account information.
                    </p>
                </div>
            </div>
        </div>

        <div class="employee-info">

            <div class="info-box">
                <div class="label">Employee ID</div>
                <div class="value employee-id">
                    <?= (int) $employee["Emp_ID"] ?>
                </div>
            </div>

            <div class="info-box">
                <div class="label">Full Name</div>
                <div class="value">
                    <?= e($full_name) ?>
                </div>
            </div>

            <div class="info-box">
                <div class="label">Email Address</div>
                <div class="value">
                    <?= e($decrypted_email) ?>
                </div>
            </div>

        </div>
    </section>

    <!-- PAYROLL HISTORY -->
    <section class="payroll-card">

        <div class="section-heading">
            <div class="section-title-group">
                <div class="section-icon" aria-hidden="true">₱</div>

                <div>
                    <h3>
                        Payroll <span class="heading-accent">Records</span>
                    </h3>
                    <p class="section-description">
                        View your payroll totals and individual payslips.
                    </p>
                </div>
            </div>
        </div>

        <?php if (count($payroll_rows) === 0): ?>

            <div class="no-records">
                <span class="empty-icon" aria-hidden="true">—</span>

                <div class="empty-message">
                    No payroll history is currently available.
                </div>

                <div class="empty-description">
                    Your payroll records will appear here when they are
                    linked to your employee account.
                </div>
            </div>

        <?php else: ?>

            <div class="table-container">
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Payroll ID</th>
                                <th>Pay Period</th>
                                <th>Pay Date</th>
                                <th class="amount">Basic Salary</th>
                                <th class="amount">Gross Pay</th>
                                <th class="amount">Deductions</th>
                                <th class="amount">Net Pay</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($payroll_rows as $row): ?>
                                <tr>
                                    <td class="payroll-id">
                                        #<?= (int) $row["Payroll_ID"] ?>
                                    </td>

                                    <td><?= e($row["Pay_Period"]) ?></td>

                                    <td><?= e($row["Pay_Date"]) ?></td>

                                    <td class="amount">
                                        ₱<?= number_format((float) $row["Basic_Salary"], 2) ?>
                                    </td>

                                    <td class="amount">
                                        ₱<?= number_format((float) $row["Total_Gross"], 2) ?>
                                    </td>

                                    <td class="amount">
                                        ₱<?= number_format((float) $row["Total_Deductions"], 2) ?>
                                    </td>

                                    <td class="amount net-pay">
                                        ₱<?= number_format((float) $row["Net_Pay"], 2) ?>
                                    </td>

                                    <td>
                                        <a
                                            href="view_payroll.php?id=<?= (int) $row["Payroll_ID"] ?>"
                                            class="view-btn"
                                        >
                                            View Payslip <span aria-hidden="true">→</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>

    </section>

    <!-- BACK BUTTON -->
    <a href="dashboard.php" class="back-button">
        <span aria-hidden="true">←</span>
        Back to Dashboard
    </a>

    <!-- FOOTER NOTE -->
    <footer class="footer-note">
        <div class="footer-icon" aria-hidden="true">✓</div>

        <div>
            <div class="footer-title">
                Your payroll information is account-specific.
            </div>

            <div class="footer-text">
                This page displays payroll records linked to your employee
                account. For questions about payroll amounts or missing records,
                contact the Administrator or HR Department.
            </div>
        </div>
    </footer>

</div>

</body>
</html>

<?php
$conn->close();
?>