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
if ($_SESSION["Role"] !== "Employee") {
    die("Access denied. This page is for employees only.");
}

// Make sure employee account is linked
if (empty($_SESSION["Emp_ID"])) {
    die("Your account is not linked to an employee record. Please contact the Administrator.");
}

$emp_id = (int) $_SESSION["Emp_ID"];

// Get employee information
$stmt = $conn->prepare("
    SELECT
        Emp_ID,
        First_Name,
        Last_Name,
        Email
    FROM employee
    WHERE Emp_ID = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error.");
}

$stmt->bind_param("i", $emp_id);
$stmt->execute();

$employee_result = $stmt->get_result();
$employee = $employee_result->fetch_assoc();

$stmt->close();

if (!$employee) {
    die("Employee record not found.");
}

// Decrypt employee email
$decrypted_email = decrypt_data($employee["Email"]);

if ($decrypted_email === false || $decrypted_email === "") {
    $decrypted_email = "Email unavailable";
}

// Get ONLY this employee's benefits
$stmt = $conn->prepare("
    SELECT
        Benefit_ID,
        Benefit_Name,
        Benefit_Type,
        Description,
        Amount,
        Status,
        Effective_Date,
        Created_At
    FROM benefits
    WHERE Emp_ID = ?
    ORDER BY
        CASE
            WHEN Status = 'Active' THEN 1
            ELSE 2
        END,
        Effective_Date DESC,
        Benefit_ID DESC
");

if (!$stmt) {
    die("Database error.");
}

$stmt->bind_param("i", $emp_id);
$stmt->execute();

$benefits_result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Benefits | Payroll and Benefits System</title>

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
            border: 1px solid rgba(201, 166, 107, 0.22);
            border-radius: 14px;
            background: rgba(201, 166, 107, 0.12);
            color: #C9A66B;
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
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            background: var(--maroon);
            color: var(--text);
            padding: 12px 18px;
            border: 1px solid rgba(208, 173, 112, 0.18);
            border-radius: 9px;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .back-button:hover {
            background: var(--maroon-hover);
            transform: translateY(-1px);
        }

        .employee-card,
        .benefits-card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 25px;
            margin-bottom: 24px;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 22px;
        }

        h2 {
            margin: 0;
            color: var(--text);
            font-size: 19px;
            font-weight: 700;
        }

        .heading-accent {
            color: var(--gold);
        }

        .section-description {
            margin: 8px 0 0;
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
            background: var(--panel-light);
            padding: 19px;
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        .label {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 9px;
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

        /* BENEFITS TABLE */

        .table-container {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        thead {
            background: #21151b;
        }

        th {
            color: var(--gold);
            padding: 16px 15px;
            text-align: left;
            white-space: nowrap;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 16px 15px;
            border-bottom: 1px solid var(--border);
            color: var(--text);
            font-size: 13px;
            line-height: 1.6;
            vertical-align: top;
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

        .benefit-id {
            color: var(--muted);
            font-weight: 600;
        }

        .benefit-name {
            color: var(--text);
            font-weight: 600;
        }

        .benefit-type {
            color: var(--gold);
        }

        .amount {
            text-align: right;
            white-space: nowrap;
            color: var(--gold);
            font-weight: 700;
        }

        /* STATUS */

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .active {
            color: var(--success);
            background: var(--success-bg);
            border: 1px solid rgba(88, 201, 148, 0.2);
        }

        .inactive {
            color: var(--danger);
            background: var(--danger-bg);
            border: 1px solid rgba(240, 128, 128, 0.2);
        }

        /* EMPTY STATE */

        .empty {
            text-align: center;
            padding: 42px 20px;
            color: var(--muted);
        }

        .empty-icon {
            display: block;
            margin-bottom: 12px;
            font-size: 32px;
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
        }

        .footer-note {
            margin-top: 17px;
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

            .employee-info {
                grid-template-columns: 1fr;
            }

            .employee-card,
            .benefits-card {
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
        }
    </style>
</head>

<body>

<div class="container">

    <!-- HEADER -->
    <div class="header">

        <div class="header-title">

            <div class="title-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M20 12v8a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-8"/>
                    <path d="M2 7h20v5H2z"/>
                    <path d="M12 7v14"/>
                    <path d="M12 7H7.5a2.5 2.5 0 1 1 0-5C10 2 12 7 12 7Z"/>
                    <path d="M12 7h4.5a2.5 2.5 0 1 0 0-5C14 2 12 7 12 7Z"/>
                </svg>
            </div>

            <div>
                <h1>My Benefits</h1>
                <p class="subtitle">
                    View your assigned employee benefits and their current status.
                </p>
            </div>

        </div>

        <a href="dashboard.php" class="back-button">
            <span aria-hidden="true">←</span>
            Back to Dashboard
        </a>

    </div>

    <!-- EMPLOYEE INFORMATION -->
    <div class="employee-card">

        <div class="section-heading">
            <div>
                <h2>Employee <span class="heading-accent">Information</span></h2>
                <p class="section-description">
                    Your employee account details.
                </p>
            </div>
        </div>

        <div class="employee-info">

            <div class="info-box">
                <div class="label">Employee ID</div>

                <div class="value employee-id">
                    <?php echo (int) $employee["Emp_ID"]; ?>
                </div>
            </div>

            <div class="info-box">
                <div class="label">Full Name</div>

                <div class="value">
                    <?php
                    echo htmlspecialchars(
                        $employee["First_Name"] . " " .
                        $employee["Last_Name"],
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>
                </div>
            </div>

            <div class="info-box">
                <div class="label">Email Address</div>

                <div class="value">
                    <?php
                    echo htmlspecialchars(
                        $decrypted_email,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>
                </div>
            </div>

        </div>
    </div>

    <!-- BENEFITS -->
    <div class="benefits-card">

        <div class="section-heading">
            <div>
                <h2>My <span class="heading-accent">Benefits</span></h2>
                <p class="section-description">
                    Benefits assigned to your employee account.
                </p>
            </div>
        </div>

        <div class="table-container">

            <table>
                <thead>
                    <tr>
                        <th>Benefit ID</th>
                        <th>Benefit Name</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th class="amount">Amount</th>
                        <th>Status</th>
                        <th>Effective Date</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($benefits_result->num_rows > 0): ?>

                    <?php while ($benefit = $benefits_result->fetch_assoc()): ?>

                        <tr>

                            <td class="benefit-id">
                                <?php echo (int) $benefit["Benefit_ID"]; ?>
                            </td>

                            <td class="benefit-name">
                                <?php
                                echo htmlspecialchars(
                                    $benefit["Benefit_Name"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>
                            </td>

                            <td class="benefit-type">
                                <?php
                                echo htmlspecialchars(
                                    $benefit["Benefit_Type"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $benefit["Description"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>
                            </td>

                            <td class="amount">
                                ₱<?php
                                echo number_format(
                                    (float) $benefit["Amount"],
                                    2
                                );
                                ?>
                            </td>

                            <td>

                                <?php if ($benefit["Status"] === "Active"): ?>

                                    <span class="status active">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="status inactive">
                                        <?php
                                        echo htmlspecialchars(
                                            $benefit["Status"] ?? "Inactive",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                        ?>
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $benefit["Effective_Date"] ?? "Not specified",
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="empty">

                            <span class="empty-icon">—</span>

                            <div class="empty-message">
                                No benefits are currently assigned to you.
                            </div>

                            <div class="empty-description">
                                Please contact the Administrator if you believe this is incorrect.
                            </div>

                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>

        </div>

        <p class="footer-note">
            For questions about benefit amounts, eligibility, or status,
            please contact the Administrator or HR Department.
        </p>

    </div>

</div>

</body>
</html>

<?php

$stmt->close();
$conn->close();

?>