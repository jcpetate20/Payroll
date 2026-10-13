<?php

session_start();

require_once "db.php";
require_once "session_security.php";
require_once "encryption.php";
require_once "csrf.php";
require_once "audit_log.php";


// --------------------------------------------------------------------------
// Check if user is logged in
// --------------------------------------------------------------------------

if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}


// --------------------------------------------------------------------------
// Get logged-in user's role
// --------------------------------------------------------------------------

$role = $_SESSION["Role"];


// --------------------------------------------------------------------------
// Only Administrator and HR Staff can access Employee Management
// --------------------------------------------------------------------------

if ($role !== "Administrator" && $role !== "HR Staff") {

    http_response_code(403);

    echo "<!DOCTYPE html>";
    echo "<html lang='en'>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
    echo "<title>Access Denied</title>";

    echo "
    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: Arial, sans-serif;
            background: #090b0d;
            color: #f5f1ea;
        }

        .box {
            width: 450px;
            max-width: 90%;
            background: #111417;
            padding: 40px;
            border-radius: 18px;
            text-align: center;
            border: 1px solid #282d31;
            box-shadow: 0 20px 50px rgba(0,0,0,.35);
        }

        .icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            border-radius: 18px;
            background: rgba(201,166,107,.10);
            border: 1px solid rgba(201,166,107,.22);
            color: #C9A66B;
            display: grid;
            place-items: center;
        }

        .icon svg {
            width: 31px;
            height: 31px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        h1 {
            margin: 0 0 12px;
            color: #f5f1ea;
            font-size: 27px;
        }

        p {
            color: #9ca3a8;
            line-height: 1.6;
        }

        a {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 20px;
            background: #641C28;
            color: white;
            text-decoration: none;
            border-radius: 9px;
            font-weight: 600;
        }

        a:hover {
            background: #7A2635;
        }

    </style>";

    echo "</head>";
    echo "<body>";

    echo "<div class='box'>";

    echo "
        <div class='icon'>
            <svg viewBox='0 0 24 24' aria-hidden='true'>
                <path d='M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2'/>
                <circle cx='9.5' cy='7' r='4'/>
                <path d='M17 11a4 4 0 1 0 0-8'/>
                <path d='M21 21v-2a4 4 0 0 0-3-3.87'/>
            </svg>
        </div>
    ";

    echo "<h1>Access Denied</h1>";
    echo "<p>You do not have permission to access Employee Management.</p>";
    echo "<a href='dashboard.php'>Return to Dashboard</a>";

    echo "</div>";
    echo "</body>";
    echo "</html>";

    exit;
}


// --------------------------------------------------------------------------
// Get employees
//
// Employees with Status = Removed are intentionally hidden.
// Their database records, payroll history, and audit logs remain.
// --------------------------------------------------------------------------

$sql = "
    SELECT
        e.Emp_ID,
        e.First_Name,
        e.Last_Name,
        e.Email,
        e.Phone,
        e.Hire_Date,
        e.Status,
        d.Department_Name,
        p.Position_Title
    FROM employee e
    LEFT JOIN department d
        ON e.Department_ID = d.Department_ID
    LEFT JOIN positions p
        ON e.Position_ID = p.Position_ID
    WHERE e.Status <> 'Removed'
       OR e.Status IS NULL
    ORDER BY e.Emp_ID ASC
";

$result = $conn->query($sql);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Employee Management</title>

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
            --maroon-light: #7A2635;

            --gold: #C9A66B;
            --gold-soft: rgba(201,166,107,.12);

            --text: #f4efe8;
            --muted: #9ca3a8;
            --muted-light: #c4c8ca;

            --green: #79b89a;
        }


        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }


        /* =========================================================
           TOP HEADER
        ========================================================= */

        .header {
            min-height: 78px;

            background:
                linear-gradient(
                    135deg,
                    #101215,
                    #0c0e10
                );

            border-bottom: 1px solid var(--border);

            padding: 0 40px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            position: relative;
        }


        .header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }


        /* Dashboard-style SVG brand icon */

        .brand-icon {
            width: 43px;
            height: 43px;

            border-radius: 13px;

            background: var(--gold-soft);

            color: var(--gold);

            display: grid;
            place-items: center;

            border: 1px solid rgba(201,166,107,.22);

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
            display: flex;
            flex-direction: column;
            gap: 3px;
        }


        .brand-title {
            font-size: 15px;
            font-weight: 700;

            letter-spacing: .3px;

            color: var(--text);
        }


        .brand-subtitle {
            font-size: 11px;

            color: var(--muted);

            letter-spacing: .4px;

            text-transform: uppercase;
        }


        /* =========================================================
           USER INFO
        ========================================================= */

        .user-info {
            text-align: right;
        }


        .user-info strong {
            display: block;

            font-size: 14px;

            color: var(--text);

            margin-bottom: 4px;
        }


        .user-info span {
            font-size: 12px;

            color: var(--gold);
        }


        /* =========================================================
           MAIN CONTENT
        ========================================================= */

        .container {
            width: 94%;

            max-width: 1450px;

            margin: 34px auto 50px;
        }


        /* =========================================================
           PAGE HEADER
        ========================================================= */

        .page-header {
            display: flex;

            justify-content: space-between;

            align-items: flex-end;

            gap: 20px;

            margin-bottom: 24px;
        }


        .page-title {
            margin: 0;

            font-size: 28px;

            font-weight: 700;

            color: var(--text);

            letter-spacing: -.4px;
        }


        .page-description {
            margin: 7px 0 0;

            font-size: 13px;

            color: var(--muted);

            line-height: 1.5;
        }


        /* =========================================================
           ADD EMPLOYEE BUTTON
        ========================================================= */

        .add-button {
            display: inline-flex;

            align-items: center;

            gap: 9px;

            background: var(--maroon);

            color: #fff;

            padding: 11px 16px;

            text-decoration: none;

            border-radius: 9px;

            font-size: 13px;

            font-weight: 600;

            border: 1px solid rgba(255,255,255,.06);

            transition:
                background .2s ease,
                transform .2s ease;
        }


        .add-button:hover {
            background: var(--maroon-light);

            transform: translateY(-1px);
        }


        .add-icon {
            width: 17px;
            height: 17px;

            fill: none;

            stroke: currentColor;

            stroke-width: 1.8;

            stroke-linecap: round;
            stroke-linejoin: round;
        }


        /* =========================================================
           EMPLOYEE TABLE CARD
        ========================================================= */

        .table-container {
            background: var(--panel);

            border: 1px solid var(--border);

            border-radius: 15px;

            overflow-x: auto;

            box-shadow:
                0 18px 45px rgba(0,0,0,.22);
        }


        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 1250px;
        }


        /* =========================================================
           TABLE HEADER
        ========================================================= */

        th {
            background: #171b1e;

            color: #b9bec1;

            padding: 15px 16px;

            text-align: left;

            font-size: 11px;

            font-weight: 700;

            letter-spacing: .6px;

            text-transform: uppercase;

            border-bottom: 1px solid var(--border);

            white-space: nowrap;
        }


        /* =========================================================
           TABLE BODY
        ========================================================= */

        td {
            padding: 15px 16px;

            border-bottom: 1px solid #202529;

            color: var(--muted-light);

            font-size: 13px;

            vertical-align: middle;
        }


        tbody tr {
            transition: background .15s ease;
        }


        tbody tr:hover {
            background: #15191c;
        }


        tbody tr:last-child td {
            border-bottom: none;
        }


        .employee-id {
            color: var(--gold);

            font-weight: 700;

            font-size: 12px;
        }


        .employee-name {
            color: var(--text);

            font-weight: 600;
        }


        .secondary-text {
            color: var(--muted);
        }


        /* =========================================================
           STATUS
        ========================================================= */

        .status {
            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 700;

            border: 1px solid transparent;
        }


        .status::before {
            content: "";

            width: 6px;
            height: 6px;

            border-radius: 50%;

            background: currentColor;
        }


        .active {
            background: rgba(121,184,154,.09);

            color: var(--green);

            border-color: rgba(121,184,154,.18);
        }


        .inactive {
            background: rgba(210,100,110,.09);

            color: #d47d85;

            border-color: rgba(210,100,110,.18);
        }


        /* =========================================================
           ACTION BUTTON
        ========================================================= */

        .action-button {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-width: 54px;

            padding: 7px 11px;

            border-radius: 7px;

            background: #1a1f22;

            border: 1px solid #30363a;

            color: #d9dde0;

            text-decoration: none;

            font-size: 12px;

            font-weight: 600;

            transition:
                background .2s ease,
                border-color .2s ease,
                color .2s ease;
        }


        .action-button:hover {
            background: var(--maroon);

            border-color: var(--maroon-light);

            color: #fff;
        }


        /* =========================================================
           DEACTIVATE BUTTON
        ========================================================= */

        .deactivate-button {
            background: rgba(210,100,110,.08);

            border: 1px solid rgba(210,100,110,.20);

            color: #d47d85;

            cursor: pointer;

            margin-left: 5px;
        }


        .deactivate-button:hover {
            background: var(--maroon);

            border-color: var(--maroon-light);

            color: #fff;
        }


        /* =========================================================
           REACTIVATE BUTTON
        ========================================================= */

        .reactivate-button {
            background: rgba(121,184,154,.08);

            border: 1px solid rgba(121,184,154,.20);

            color: var(--green);

            cursor: pointer;

            margin-left: 5px;
        }


        .reactivate-button:hover {
            background: rgba(121,184,154,.18);

            border-color: var(--green);

            color: #fff;
        }


        /* =========================================================
           REMOVE BUTTON
        ========================================================= */

        .remove-button {
            background: rgba(201,166,107,.08);

            border: 1px solid rgba(201,166,107,.28);

            color: var(--gold);

            cursor: pointer;

            margin-left: 5px;
        }


        .remove-button:hover {
            background: var(--gold);

            border-color: var(--gold);

            color: #090b0d;
        }


        /* =========================================================
           EMPTY STATE
        ========================================================= */

        .empty {
            text-align: center;

            padding: 50px 30px;

            color: var(--muted);

            font-size: 13px;
        }


        /* =========================================================
           BACK LINK
        ========================================================= */

        .back {
            display: inline-flex;

            align-items: center;

            margin-top: 20px;

            color: var(--muted);

            text-decoration: none;

            font-size: 13px;

            transition: color .2s ease;
        }


        .back:hover {
            color: var(--gold);
        }


        /* =========================================================
           MOBILE
        ========================================================= */

        @media (max-width: 800px) {

            .header {
                padding: 18px 20px;

                flex-direction: column;

                align-items: flex-start;

                gap: 15px;
            }


            .user-info {
                text-align: left;
            }


            .container {
                width: calc(100% - 30px);

                margin-top: 25px;
            }


            .page-header {
                align-items: flex-start;

                flex-direction: column;
            }


            .page-title {
                font-size: 24px;
            }


            .add-button {
                width: 100%;

                justify-content: center;
            }

        }


        @media (max-width: 480px) {

            .brand-title {
                font-size: 14px;
            }


            .brand-subtitle {
                font-size: 10px;
            }


            .page-title {
                font-size: 22px;
            }


            .page-description {
                font-size: 12px;
            }

        }

    </style>

</head>


<body>


    <!-- =========================================================
         HEADER
    ========================================================= -->

    <div class="header">

        <div class="header-left">

            <div class="brand-icon">

                <!-- Clean dashboard-style users SVG -->

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >

                    <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/>

                    <circle
                        cx="9.5"
                        cy="7"
                        r="4"
                    />

                    <path d="M17 11a4 4 0 1 0 0-8"/>

                    <path d="M21 21v-2a4 4 0 0 0-3-3.87"/>

                </svg>

            </div>


            <div class="brand-text">

                <div class="brand-title">
                    Management System
                </div>

                <div class="brand-subtitle">
                    Payroll & Benefits
                </div>

            </div>

        </div>


        <div class="user-info">

            <strong>
                <?php
                echo htmlspecialchars(
                    $_SESSION["Full_Name"] ?? ""
                );
                ?>
            </strong>

            <span>
                <?php
                echo htmlspecialchars($role);
                ?>
            </span>

        </div>

    </div>


    <!-- =========================================================
         MAIN CONTENT
    ========================================================= -->

    <div class="container">


        <div class="page-header">

            <div>

                <h1 class="page-title">
                    Employee Management
                </h1>

                <p class="page-description">
                    Manage employee information, assignments, contact details, and employment status.
                </p>

            </div>


            <a
                href="add_employee.php"
                class="add-button"
            >

                <svg
                    class="add-icon"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >

                    <path d="M12 5v14"/>

                    <path d="M5 12h14"/>

                </svg>

                Add Employee

            </a>

        </div>


        <!-- =====================================================
             EMPLOYEE TABLE
        ===================================================== -->

        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Employee Name</th>

                        <th>Email</th>

                        <th>Phone</th>

                        <th>Department</th>

                        <th>Position</th>

                        <th>Hire Date</th>

                        <th>Status</th>

                        <th>Action</th>

                    </tr>

                </thead>


                <tbody>

                    <?php if ($result && $result->num_rows > 0): ?>


                        <?php while ($employee = $result->fetch_assoc()): ?>


                            <?php

                            // --------------------------------------------------
                            // Decrypt Employee Email
                            // --------------------------------------------------

                            $display_email = "";

                            if (!empty($employee["Email"])) {

                                $decrypted_email = decrypt_data(
                                    $employee["Email"]
                                );

                                if ($decrypted_email !== false) {

                                    $display_email = $decrypted_email;

                                } else {

                                    $display_email = $employee["Email"];

                                }
                            }


                            // --------------------------------------------------
                            // Decrypt Employee Phone
                            // --------------------------------------------------

                            $display_phone = "";

                            if (!empty($employee["Phone"])) {

                                $decrypted_phone = decrypt_data(
                                    $employee["Phone"]
                                );

                                if ($decrypted_phone !== false) {

                                    $display_phone = $decrypted_phone;

                                } else {

                                    $display_phone = $employee["Phone"];

                                }
                            }


                            // --------------------------------------------------
                            // Employee Status
                            // --------------------------------------------------

                            $status =
                                $employee["Status"]
                                ?? "Inactive";


                            $status_class =
                                strtolower($status) === "active"
                                ? "active"
                                : "inactive";

                            ?>


                            <tr>


                                <!-- ID -->

                                <td>

                                    <span class="employee-id">

                                        <?php
                                        echo htmlspecialchars(
                                            $employee["Emp_ID"]
                                        );
                                        ?>

                                    </span>

                                </td>


                                <!-- Employee Name -->

                                <td>

                                    <span class="employee-name">

                                        <?php

                                        echo htmlspecialchars(
                                            $employee["First_Name"]
                                            . " "
                                            . $employee["Last_Name"]
                                        );

                                        ?>

                                    </span>

                                </td>


                                <!-- Email -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $display_email
                                    );

                                    ?>

                                </td>


                                <!-- Phone -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $display_phone
                                    );

                                    ?>

                                </td>


                                <!-- Department -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $employee["Department_Name"]
                                        ?? "Not Assigned"
                                    );

                                    ?>

                                </td>


                                <!-- Position -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $employee["Position_Title"]
                                        ?? "Not Assigned"
                                    );

                                    ?>

                                </td>


                                <!-- Hire Date -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $employee["Hire_Date"]
                                        ?? ""
                                    );

                                    ?>

                                </td>


                                <!-- Status -->

                                <td>

                                    <span
                                        class="status <?php echo $status_class; ?>"
                                    >

                                        <?php

                                        echo htmlspecialchars(
                                            $status
                                        );

                                        ?>

                                    </span>

                                </td>


                                <!-- =================================================
                                     ACTIONS
                                ================================================== -->

                                <td>


                                    <!-- EDIT -->

                                    <a
                                        href="edit_employee.php?id=<?php echo (int) $employee["Emp_ID"]; ?>"
                                        class="action-button"
                                    >
                                        Edit
                                    </a>


                                    <?php if (strtolower($status) === "active"): ?>


                                        <!-- =================================================
                                             DEACTIVATE EMPLOYEE
                                        ================================================== -->

                                        <form
                                            action="deactivate_employee.php"
                                            method="POST"
                                            style="display:inline;"
                                            onsubmit="return confirm('Are you sure you want to deactivate <?php echo htmlspecialchars($employee["First_Name"] . " " . $employee["Last_Name"], ENT_QUOTES, "UTF-8"); ?>?\n\nThe employee will be marked as Inactive, but their payroll history will remain in the system.');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo htmlspecialchars(
                                                    csrf_token(),
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="emp_id"
                                                value="<?php echo (int) $employee["Emp_ID"]; ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="deactivate"
                                            >

                                            <button
                                                type="submit"
                                                class="action-button deactivate-button"
                                            >
                                                Deactivate
                                            </button>

                                        </form>


                                    <?php else: ?>


                                        <!-- =================================================
                                             REACTIVATE EMPLOYEE
                                        ================================================== -->

                                        <form
                                            action="deactivate_employee.php"
                                            method="POST"
                                            style="display:inline;"
                                            onsubmit="return confirm('Are you sure you want to reactivate <?php echo htmlspecialchars($employee["First_Name"] . " " . $employee["Last_Name"], ENT_QUOTES, "UTF-8"); ?>?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo htmlspecialchars(
                                                    csrf_token(),
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="emp_id"
                                                value="<?php echo (int) $employee["Emp_ID"]; ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="reactivate"
                                            >

                                            <button
                                                type="submit"
                                                class="action-button reactivate-button"
                                            >
                                                Reactivate
                                            </button>

                                        </form>


                                    <?php endif; ?>


                                    <!-- =================================================
                                         REMOVE EMPLOYEE
                                    ================================================== -->

                                    <form
                                        action="remove_employee.php"
                                        method="POST"
                                        style="display:inline;"
                                        onsubmit="return confirm('Are you sure you want to REMOVE <?php echo htmlspecialchars($employee["First_Name"] . " " . $employee["Last_Name"], ENT_QUOTES, "UTF-8"); ?>?\n\nThis employee will be removed from the active Employee Management list.\n\nTheir employee record, payroll history, and audit logs will be PRESERVED.\n\nThis action is for employees who are no longer employed by the company.');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo htmlspecialchars(
                                                csrf_token(),
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="emp_id"
                                            value="<?php echo (int) $employee["Emp_ID"]; ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="action-button remove-button"
                                        >
                                            Remove
                                        </button>

                                    </form>


                                </td>


                            </tr>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="9"
                                class="empty"
                            >
                                No employees found.
                            </td>

                        </tr>


                    <?php endif; ?>

                </tbody>

            </table>

        </div>


        <!-- =====================================================
             BACK TO DASHBOARD
        ===================================================== -->

        <a
            href="dashboard.php"
            class="back"
        >
            ← Back to Dashboard
        </a>


    </div>


</body>

</html>