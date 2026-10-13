<?php

session_start();

require_once "db.php";
require_once "session_security.php";

/*
|--------------------------------------------------------------------------
| Check if user is logged in
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Get logged-in user's role
|--------------------------------------------------------------------------
*/

$role = $_SESSION["Role"];

/*
|--------------------------------------------------------------------------
| Role-Based Access Control
|--------------------------------------------------------------------------
|
| Only Administrator and Payroll Staff can view payroll records.
|
*/

if (
    $role !== "Administrator" &&
    $role !== "Payroll Staff"
) {
    http_response_code(403);

    die("
        <div style='
            font-family: Arial, sans-serif;
            padding: 40px;
            text-align: center;
        '>
            <h2>Access Denied</h2>
            <p>
                You do not have permission to view payroll records.
            </p>
            <a href='dashboard.php'>
                Return to Dashboard
            </a>
        </div>
    ");
}

/*
|--------------------------------------------------------------------------
| Get payroll records
|--------------------------------------------------------------------------
|
| Employee name is retrieved using a JOIN with the employee table.
| Newest payroll records appear first.
|
*/

$sql = "
    SELECT
        p.Payroll_ID,
        p.Emp_ID,
        e.First_Name,
        e.Last_Name,
        p.Pay_Period,
        p.Pay_Date,
        p.Basic_Salary,
        p.Total_Gross,
        p.Total_Deductions,
        p.Net_Pay
    FROM payroll p
    INNER JOIN employee e
        ON p.Emp_ID = e.Emp_ID
    ORDER BY p.Payroll_ID DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Failed to prepare payroll records query.");
}

$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Payroll Records</title>

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
            font-family:
                "Segoe UI",
                Arial,
                sans-serif;

            background: var(--bg);
            color: var(--text);

            min-height: 100vh;
        }

        a {
            text-decoration: none;
        }


        /* =========================================================
           HEADER
        ========================================================= */

        .header {
            background:
                linear-gradient(
                    135deg,
                    #0b0d0f,
                    #111417
                );

            border-bottom: 1px solid var(--border);

            padding: 18px 40px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 20px;

            position: sticky;
            top: 0;

            z-index: 100;
        }

        .brand-area {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-icon {
            width: 45px;
            height: 45px;

            border-radius: 13px;

            background:
                rgba(201, 166, 107, 0.10);

            border:
                1px solid
                rgba(201, 166, 107, 0.25);

            color: var(--gold);

            display: grid;
            place-items: center;

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

        .brand-text h1 {
            margin: 0;

            font-size: 20px;
            font-weight: 600;

            letter-spacing: 0.2px;
        }

        .brand-text span {
            display: block;

            margin-top: 3px;

            color: var(--muted);

            font-size: 12px;
        }


        /* =========================================================
           HEADER BUTTON
        ========================================================= */

        .dashboard-button {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: 8px;

            padding: 10px 16px;

            border-radius: 9px;

            background:
                rgba(100, 28, 40, 0.85);

            border:
                1px solid
                rgba(122, 38, 53, 0.7);

            color: var(--text);

            font-size: 13px;
            font-weight: 600;

            transition:
                background 0.2s ease,
                border-color 0.2s ease,
                transform 0.2s ease;
        }

        .dashboard-button svg {
            width: 17px;
            height: 17px;

            fill: none;
            stroke: currentColor;

            stroke-width: 1.8;

            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .dashboard-button:hover {
            background: var(--maroon-hover);

            border-color:
                rgba(201, 166, 107, 0.35);

            transform: translateY(-1px);
        }


        /* =========================================================
           MAIN CONTAINER
        ========================================================= */

        .container {
            width: 94%;
            max-width: 1450px;

            margin: 35px auto 50px;
        }


        /* =========================================================
           PAGE TITLE
        ========================================================= */

        .page-title {
            background:
                linear-gradient(
                    145deg,
                    var(--panel),
                    #0f1215
                );

            border:
                1px solid var(--border);

            border-radius: 16px;

            padding: 25px 28px;

            margin-bottom: 22px;

            position: relative;

            overflow: hidden;
        }

        .page-title::after {
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
                    var(--gold),
                    transparent
                );
        }

        .title-content {
            display: flex;

            align-items: center;

            gap: 16px;
        }

        .title-icon {
            width: 48px;
            height: 48px;

            border-radius: 12px;

            background:
                rgba(100, 28, 40, 0.22);

            border:
                1px solid
                rgba(100, 28, 40, 0.6);

            color: var(--gold);

            display: grid;
            place-items: center;

            flex-shrink: 0;
        }

        .title-icon svg {
            width: 25px;
            height: 25px;

            fill: none;
            stroke: currentColor;

            stroke-width: 1.7;

            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .page-title h2 {
            margin: 0 0 5px;

            color: var(--text);

            font-size: 22px;
            font-weight: 600;
        }

        .page-title p {
            margin: 0;

            color: var(--muted);

            font-size: 13px;
        }


        /* =========================================================
           RECORD COUNT
        ========================================================= */

        .record-info {
            display: flex;

            justify-content: space-between;
            align-items: center;

            gap: 15px;

            margin-bottom: 14px;
        }

        .record-label {
            color: var(--muted);

            font-size: 13px;
        }

        .record-count {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            min-width: 34px;
            height: 27px;

            padding: 0 10px;

            margin-left: 6px;

            border-radius: 20px;

            background:
                rgba(201, 166, 107, 0.10);

            border:
                1px solid
                rgba(201, 166, 107, 0.22);

            color: var(--gold);

            font-size: 12px;
            font-weight: 600;
        }


        /* =========================================================
           TABLE CONTAINER
        ========================================================= */

        .table-container {
            background:
                linear-gradient(
                    145deg,
                    var(--panel),
                    #0e1113
                );

            border:
                1px solid var(--border);

            border-radius: 16px;

            padding: 5px;

            overflow-x: auto;

            box-shadow:
                0 18px 45px
                rgba(0, 0, 0, 0.22);
        }


        /* =========================================================
           TABLE
        ========================================================= */

        table {
            width: 100%;

            border-collapse: separate;
            border-spacing: 0;

            min-width: 1100px;
        }

        thead th {
            background:
                linear-gradient(
                    135deg,
                    #3A0B13,
                    #641C28
                );

            color: #F8F2EA;

            padding: 15px 13px;

            text-align: left;

            font-size: 12px;
            font-weight: 600;

            letter-spacing: 0.3px;

            white-space: nowrap;

            border-bottom:
                1px solid
                rgba(201, 166, 107, 0.18);
        }

        thead th:first-child {
            border-top-left-radius: 11px;
        }

        thead th:last-child {
            border-top-right-radius: 11px;
        }

        tbody td {
            padding: 14px 13px;

            border-bottom:
                1px solid
                rgba(40, 45, 49, 0.75);

            color: var(--muted-light);

            font-size: 13px;

            white-space: nowrap;

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


        /* =========================================================
           PAYROLL ID
        ========================================================= */

        .payroll-id {
            color: var(--gold);

            font-weight: 600;
        }


        /* =========================================================
           EMPLOYEE
        ========================================================= */

        .employee-name {
            color: var(--text);

            font-weight: 600;
        }


        /* =========================================================
           PAY PERIOD
        ========================================================= */

        .pay-period {
            color: var(--muted-light);
        }


        /* =========================================================
           DATE
        ========================================================= */

        .pay-date {
            color: var(--muted);
        }


        /* =========================================================
           AMOUNTS
        ========================================================= */

        .amount {
            text-align: right;

            color: var(--muted-light);

            font-variant-numeric:
                tabular-nums;
        }

        .basic-salary {
            color: var(--text);
        }

        .gross-pay {
            color: var(--green);

            font-weight: 600;
        }

        .deductions {
            color: var(--red);
        }

        .net-pay {
            color: var(--gold);

            font-weight: 700;

            font-size: 14px;
        }


        /* =========================================================
           VIEW BUTTON
        ========================================================= */

        .view-button {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: 7px;

            background:
                rgba(100, 28, 40, 0.78);

            border:
                1px solid
                rgba(122, 38, 53, 0.7);

            color: var(--text);

            padding: 8px 13px;

            border-radius: 8px;

            font-size: 12px;
            font-weight: 600;

            transition:
                background 0.2s ease,
                border-color 0.2s ease,
                transform 0.2s ease;
        }

        .view-button svg {
            width: 15px;
            height: 15px;

            fill: none;
            stroke: currentColor;

            stroke-width: 1.8;

            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .view-button:hover {
            background: var(--maroon-hover);

            border-color:
                rgba(201, 166, 107, 0.38);

            transform: translateY(-1px);
        }


        /* =========================================================
           EMPTY STATE
        ========================================================= */

        .empty {
            text-align: center;

            padding: 65px 30px;
        }

        .empty-icon {
            width: 58px;
            height: 58px;

            margin: 0 auto 18px;

            border-radius: 15px;

            display: grid;
            place-items: center;

            background:
                rgba(100, 28, 40, 0.15);

            border:
                1px solid
                rgba(100, 28, 40, 0.4);

            color: var(--gold);
        }

        .empty-icon svg {
            width: 28px;
            height: 28px;

            fill: none;
            stroke: currentColor;

            stroke-width: 1.6;

            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .empty h3 {
            margin: 0 0 8px;

            color: var(--text);

            font-size: 17px;
        }

        .empty p {
            margin: 0;

            color: var(--muted);

            font-size: 13px;
        }


        /* =========================================================
           BACK BUTTON
        ========================================================= */

        .back-button {
            display: inline-flex;

            align-items: center;

            gap: 8px;

            margin-top: 20px;

            padding: 10px 16px;

            border-radius: 9px;

            background:
                #15191c;

            border:
                1px solid var(--border);

            color: var(--muted-light);

            font-size: 13px;
            font-weight: 600;

            transition:
                background 0.2s ease,
                border-color 0.2s ease,
                color 0.2s ease;
        }

        .back-button:hover {
            background:
                rgba(100, 28, 40, 0.18);

            border-color:
                rgba(201, 166, 107, 0.28);

            color: var(--gold);
        }

        .back-button svg {
            width: 16px;
            height: 16px;

            fill: none;
            stroke: currentColor;

            stroke-width: 1.8;

            stroke-linecap: round;
            stroke-linejoin: round;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 800px) {

            .header {
                padding: 16px 20px;

                flex-direction: column;

                align-items: stretch;

                text-align: center;
            }

            .brand-area {
                justify-content: center;
            }

            .dashboard-button {
                width: 100%;
            }

            .container {
                width: 94%;

                margin-top: 25px;
            }

            .page-title {
                padding: 22px;
            }

            .title-content {
                align-items: flex-start;
            }

            .record-info {
                align-items: flex-start;
                flex-direction: column;
            }

            .table-container {
                padding: 4px;
            }

        }


        @media (max-width: 500px) {

            .brand-text h1 {
                font-size: 18px;
            }

            .brand-text span {
                font-size: 11px;
            }

            .page-title h2 {
                font-size: 19px;
            }

            .page-title p {
                font-size: 12px;
                line-height: 1.5;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

<div class="header">

    <div class="brand-area">

        <div class="brand-icon">

            <!-- Payroll / wallet icon -->

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

                <path d="M3 9h18"/>

                <path d="M7 14h4"/>

                <path d="M15 14h2"/>

            </svg>

        </div>


        <div class="brand-text">

            <h1>
                Payroll System
            </h1>

            <span>
                Payroll Records Management
            </span>

        </div>

    </div>


    <a
        href="dashboard.php"
        class="dashboard-button"
    >

        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
        >

            <path d="M3 10.5L12 3l9 7.5"/>

            <path d="M5 9.5V21h14V9.5"/>

            <path d="M9 21v-6h6v6"/>

        </svg>

        Dashboard

    </a>

</div>


<!-- =========================================================
     MAIN
========================================================= -->

<div class="container">


    <!-- PAGE TITLE -->

    <div class="page-title">

        <div class="title-content">

            <div class="title-icon">

                <!-- Payroll records icon -->

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


            <div>

                <h2>
                    Payroll Records
                </h2>

                <p>
                    View all saved payroll records in the system.
                </p>

            </div>

        </div>

    </div>


    <!-- RECORD INFORMATION -->

    <div class="record-info">

        <div class="record-label">

            Saved payroll records

            <span class="record-count">
                <?php echo $result->num_rows; ?>
            </span>

        </div>

    </div>


    <!-- PAYROLL TABLE -->

    <div class="table-container">

        <?php if ($result->num_rows > 0): ?>

            <table>

                <thead>

                    <tr>

                        <th>
                            Payroll ID
                        </th>

                        <th>
                            Employee
                        </th>

                        <th>
                            Pay Period
                        </th>

                        <th>
                            Pay Date
                        </th>

                        <th>
                            Basic Salary
                        </th>

                        <th>
                            Total Gross
                        </th>

                        <th>
                            Total Deductions
                        </th>

                        <th>
                            Net Pay
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>


                            <!-- PAYROLL ID -->

                            <td class="payroll-id">

                                #<?php

                                echo htmlspecialchars(
                                    $row["Payroll_ID"]
                                );

                                ?>

                            </td>


                            <!-- EMPLOYEE -->

                            <td class="employee-name">

                                <?php

                                echo htmlspecialchars(
                                    $row["First_Name"] .
                                    " " .
                                    $row["Last_Name"]
                                );

                                ?>

                            </td>


                            <!-- PAY PERIOD -->

                            <td class="pay-period">

                                <?php

                                echo htmlspecialchars(
                                    $row["Pay_Period"]
                                );

                                ?>

                            </td>


                            <!-- PAY DATE -->

                            <td class="pay-date">

                                <?php

                                echo htmlspecialchars(
                                    date(
                                        "M d, Y",
                                        strtotime(
                                            $row["Pay_Date"]
                                        )
                                    )
                                );

                                ?>

                            </td>


                            <!-- BASIC SALARY -->

                            <td class="amount basic-salary">

                                ₱<?php

                                echo number_format(
                                    (float)$row["Basic_Salary"],
                                    2
                                );

                                ?>

                            </td>


                            <!-- TOTAL GROSS -->

                            <td class="amount gross-pay">

                                ₱<?php

                                echo number_format(
                                    (float)$row["Total_Gross"],
                                    2
                                );

                                ?>

                            </td>


                            <!-- TOTAL DEDUCTIONS -->

                            <td class="amount deductions">

                                ₱<?php

                                echo number_format(
                                    (float)$row["Total_Deductions"],
                                    2
                                );

                                ?>

                            </td>


                            <!-- NET PAY -->

                            <td class="amount net-pay">

                                ₱<?php

                                echo number_format(
                                    (float)$row["Net_Pay"],
                                    2
                                );

                                ?>

                            </td>


                            <!-- ACTION -->

                            <td>

                                <a
                                    href="view_payroll.php?id=<?php echo urlencode($row["Payroll_ID"]); ?>"
                                    class="view-button"
                                >

                                    <svg
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >

                                        <path
                                            d="M2.5 12s3.5-6 9.5-6
                                            9.5 6 9.5 6-3.5 6-9.5 6
                                            -9.5-6-9.5-6z"
                                        />

                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="2.5"
                                        />

                                    </svg>

                                    View

                                </a>

                            </td>


                        </tr>

                    <?php endwhile; ?>

                </tbody>

            </table>

        <?php else: ?>


            <!-- EMPTY STATE -->

            <div class="empty">

                <div class="empty-icon">

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

                        <path d="M8 8h8"/>

                        <path d="M8 12h8"/>

                        <path d="M8 16h5"/>

                    </svg>

                </div>

                <h3>
                    No Payroll Records Found
                </h3>

                <p>
                    There are currently no payroll records in the system.
                </p>

            </div>


        <?php endif; ?>

    </div>


    <!-- BACK BUTTON -->

    <a
        href="dashboard.php"
        class="back-button"
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


</div>


</body>

</html>


<?php

$stmt->close();

$conn->close();

?>