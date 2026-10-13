<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";
require_once "session_security.php";

// Check login
if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}

// Administrator only
if ($_SESSION["Role"] !== "Administrator") {
    die("Access denied. Administrator access required.");
}

// Get audit logs
$sql = "
    SELECT
        a.Log_ID,
        a.User_ID,
        a.Action,
        a.Description,
        a.IP_Address,
        a.Created_At,
        u.Username,
        u.Full_Name
    FROM audit_logs a
    LEFT JOIN users u
        ON a.User_ID = u.User_ID
    ORDER BY a.Created_At DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Failed to load audit logs: " . $conn->error);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Audit Logs | Payroll System</title>

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
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        .container {
            width: 94%;
            max-width: 1450px;
            margin: 35px auto;
        }

        /* =========================
           HEADER
        ========================= */

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;

            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .title-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .title-icon {
            width: 48px;
            height: 48px;

            display: grid;
            place-items: center;

            background: rgba(201, 166, 107, 0.10);
            border: 1px solid rgba(201, 166, 107, 0.22);

            border-radius: 14px;

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

            font-size: 28px;
            font-weight: 700;

            color: var(--text);
        }

        .subtitle {
            margin-top: 5px;

            color: var(--muted);
            font-size: 14px;
        }

        /* =========================
           BACK BUTTON
        ========================= */

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            text-decoration: none;

            background: var(--maroon);
            color: white;

            padding: 11px 18px;

            border-radius: 9px;

            font-size: 14px;
            font-weight: 600;

            border: 1px solid rgba(255,255,255,0.05);

            transition: 0.2s ease;
        }

        .back-button:hover {
            background: var(--maroon-hover);
            transform: translateY(-1px);
        }

        /* =========================
           LOG SUMMARY
        ========================= */

        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;

            margin-bottom: 20px;
        }

        .summary-card {
            background: var(--panel);
            border: 1px solid var(--border);

            border-radius: 12px;

            padding: 18px 20px;
        }

        .summary-label {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 7px;
        }

        .summary-value {
            color: var(--text);
            font-size: 23px;
            font-weight: 700;
        }

        .summary-value.gold {
            color: var(--gold);
        }

        /* =========================
           TABLE CONTAINER
        ========================= */

        .table-container {
            background: var(--panel);

            border: 1px solid var(--border);

            border-radius: 14px;

            overflow: hidden;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.22);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;

            padding: 20px 22px;

            border-bottom: 1px solid var(--border);

            background: var(--panel-light);
        }

        .table-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
        }

        .table-description {
            margin-top: 4px;

            font-size: 13px;
            color: var(--muted);
        }

        .log-count {
            color: var(--gold);

            font-size: 13px;
            font-weight: 600;

            background: rgba(201, 166, 107, 0.08);

            border: 1px solid rgba(201, 166, 107, 0.18);

            padding: 7px 11px;

            border-radius: 7px;
        }

        .table-scroll {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;

            min-width: 1050px;
        }

        /* =========================
           TABLE HEAD
        ========================= */

        th {
            background: #0d1012;

            color: var(--muted-light);

            padding: 14px 16px;

            text-align: left;

            font-size: 12px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 0.5px;

            white-space: nowrap;

            border-bottom: 1px solid var(--border);
        }

        /* =========================
           TABLE BODY
        ========================= */

        td {
            padding: 15px 16px;

            border-bottom: 1px solid #202428;

            vertical-align: top;

            color: var(--muted-light);

            font-size: 14px;
        }

        tbody tr {
            transition: background 0.18s ease;
        }

        tbody tr:hover {
            background: #15191c;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* =========================
           LOG ID
        ========================= */

        .log-id {
            color: var(--gold);

            font-weight: 700;

            white-space: nowrap;
        }

        /* =========================
           USER
        ========================= */

        .user-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .username {
            color: var(--text);

            font-weight: 600;
        }

        .fullname {
            color: var(--muted);

            font-size: 12px;
        }

        .system-user {
            color: var(--muted);

            font-style: italic;
        }

        /* =========================
           ACTION
        ========================= */

        .action {
            display: inline-block;

            color: #e5b7bd;

            background: rgba(100, 28, 40, 0.22);

            border: 1px solid rgba(100, 28, 40, 0.45);

            padding: 6px 9px;

            border-radius: 6px;

            font-size: 12px;

            font-weight: 700;

            white-space: nowrap;
        }

        /* =========================
           DESCRIPTION
        ========================= */

        .description {
            max-width: 430px;

            word-wrap: break-word;

            line-height: 1.5;

            color: var(--muted-light);
        }

        /* =========================
           IP ADDRESS
        ========================= */

        .ip-address {
            font-family: "Courier New", monospace;

            color: #B8BEC2;

            font-size: 13px;

            white-space: nowrap;
        }

        /* =========================
           DATE
        ========================= */

        .date-time {
            color: var(--muted-light);

            font-size: 13px;

            white-space: nowrap;
        }

        /* =========================
           EMPTY STATE
        ========================= */

        .empty {
            text-align: center;

            padding: 55px 20px;

            color: var(--muted);

            font-size: 14px;
        }

        .empty-icon {
            width: 45px;
            height: 45px;

            margin: 0 auto 12px;

            display: grid;
            place-items: center;

            border-radius: 12px;

            background: #15191c;

            border: 1px solid var(--border);

            color: var(--gold);
        }

        .empty-icon svg {
            width: 22px;
            height: 22px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 850px) {

            .container {
                width: 94%;
                margin: 25px auto;
            }

            .summary {
                grid-template-columns: 1fr;
            }

            .header {
                align-items: flex-start;
            }

            .title-section {
                align-items: flex-start;
            }

            h1 {
                font-size: 24px;
            }

            .back-button {
                width: 100%;
                justify-content: center;
            }

        }

        @media (max-width: 500px) {

            .container {
                width: 92%;
            }

            .title-icon {
                width: 43px;
                height: 43px;
            }

            .title-icon svg {
                width: 23px;
                height: 23px;
            }

            h1 {
                font-size: 21px;
            }

            .subtitle {
                font-size: 12px;
            }

            .table-header {
                padding: 17px;
            }

        }

    </style>
</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <div class="title-section">

            <div class="title-icon">

                <svg viewBox="0 0 24 24" aria-hidden="true">

                    <rect x="4" y="3" width="16" height="18" rx="2"></rect>

                    <line x1="8" y1="8" x2="16" y2="8"></line>

                    <line x1="8" y1="12" x2="16" y2="12"></line>

                    <line x1="8" y1="16" x2="13" y2="16"></line>

                </svg>

            </div>

            <div>

                <h1>Audit Logs</h1>

                <div class="subtitle">
                    Monitor system activity and administrator actions.
                </div>

            </div>

        </div>

        <a href="dashboard.php" class="back-button">

            <svg
                width="17"
                height="17"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M19 12H5"></path>
                <path d="M12 19l-7-7 7-7"></path>
            </svg>

            Back to Dashboard

        </a>

    </div>


    <!-- SUMMARY -->

    <div class="summary">

        <div class="summary-card">

            <div class="summary-label">
                Total Audit Logs
            </div>

            <div class="summary-value">
                <?php echo (int)$result->num_rows; ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-label">
                System Activity
            </div>

            <div class="summary-value gold">
                <?php echo $result->num_rows > 0 ? "Recorded" : "None"; ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-label">
                Access Level
            </div>

            <div class="summary-value">
                Administrator
            </div>

        </div>

    </div>


    <!-- TABLE -->

    <div class="table-container">

        <div class="table-header">

            <div>

                <div class="table-title">
                    System Activity Records
                </div>

                <div class="table-description">
                    Detailed record of actions performed within the payroll system.
                </div>

            </div>

            <div class="log-count">
                <?php echo (int)$result->num_rows; ?> Records
            </div>

        </div>


        <div class="table-scroll">

            <table>

                <thead>

                    <tr>

                        <th>Log ID</th>

                        <th>User</th>

                        <th>Action</th>

                        <th>Description</th>

                        <th>IP Address</th>

                        <th>Date &amp; Time</th>

                    </tr>

                </thead>


                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>

                            <td>

                                <span class="log-id">

                                    #<?php echo (int)$row["Log_ID"]; ?>

                                </span>

                            </td>


                            <td>

                                <?php if (!empty($row["Username"])): ?>

                                    <div class="user-info">

                                        <span class="username">

                                            <?php echo htmlspecialchars($row["Username"]); ?>

                                        </span>

                                        <span class="fullname">

                                            <?php echo htmlspecialchars($row["Full_Name"]); ?>

                                        </span>

                                    </div>

                                <?php else: ?>

                                    <span class="system-user">
                                        System / Unknown User
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <span class="action">

                                    <?php echo htmlspecialchars($row["Action"]); ?>

                                </span>

                            </td>


                            <td class="description">

                                <?php echo htmlspecialchars($row["Description"]); ?>

                            </td>


                            <td>

                                <span class="ip-address">

                                    <?php echo htmlspecialchars($row["IP_Address"]); ?>

                                </span>

                            </td>


                            <td>

                                <span class="date-time">

                                    <?php echo htmlspecialchars($row["Created_At"]); ?>

                                </span>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="6" class="empty">

                            <div class="empty-icon">

                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <circle cx="12" cy="12" r="9"></circle>
                                    <path d="M8 12h8"></path>
                                </svg>

                            </div>

                            No audit logs found.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>