<?php

require_once "db.php";
require_once "csrf.php";
require_once "session_security.php";
require_once "audit_log.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------------------------------------------------------
   LOGIN CHECK
--------------------------------------------------------- */

if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}

/* ---------------------------------------------------------
   ADMIN ONLY
--------------------------------------------------------- */

if ($_SESSION["Role"] !== "Administrator") {
    http_response_code(403);
    die("
        <div style='
            font-family: Arial, sans-serif;
            padding: 40px;
            text-align: center;
        '>
            <h2>Access Denied</h2>
            <p>Only administrators can edit user accounts.</p>
            <a href='dashboard.php'>Return to Dashboard</a>
        </div>
    ");
}

/* ---------------------------------------------------------
   GET USER ID
--------------------------------------------------------- */

$user_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($user_id <= 0) {
    die("Invalid user ID.");
}

/* ---------------------------------------------------------
   LOAD USER
--------------------------------------------------------- */

$stmt = $conn->prepare("
    SELECT
        User_ID,
        Username,
        Full_Name,
        Role,
        Emp_ID,
        Status
    FROM users
    WHERE User_ID = ?
");

if (!$stmt) {
    die("Failed to prepare user query.");
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

if (!$user) {
    die("User account not found.");
}

/* ---------------------------------------------------------
   LOAD ACTIVE EMPLOYEES
--------------------------------------------------------- */

$employees = [];

$result = $conn->query("
    SELECT
        Emp_ID,
        First_Name,
        Last_Name
    FROM employee
    WHERE Status = 'Active'
    ORDER BY Last_Name ASC, First_Name ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
}

/* ---------------------------------------------------------
   FORM PROCESSING
--------------------------------------------------------- */

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* CSRF CHECK */

    if (
        !isset($_POST["csrf_token"]) ||
        !verify_csrf_token($_POST["csrf_token"])
    ) {
        $error = "Invalid security token. Please try again.";
    }

    /* GET FORM VALUES */

    $username = trim($_POST["username"] ?? "");
    $full_name = trim($_POST["full_name"] ?? "");
    $role = trim($_POST["role"] ?? "");
    $employee_id = trim($_POST["employee_id"] ?? "");
    $status = trim($_POST["status"] ?? "");

    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    /* VALIDATION */

    if ($error === "") {

        if ($username === "") {

            $error = "Username is required.";

        } elseif (
            strlen($username) < 4 ||
            strlen($username) > 50
        ) {

            $error = "Username must be between 4 and 50 characters.";

        } elseif (
            !preg_match(
                '/^[a-zA-Z0-9.\_-]+$/',
                $username
            )
        ) {

            $error = "Username can only contain letters, numbers, dot, underscore, and hyphen.";

        } elseif ($full_name === "") {

            $error = "Full name is required.";

        } elseif (strlen($full_name) > 150) {

            $error = "Full name is too long.";

        } elseif (
            !in_array(
                $role,
                [
                    "Administrator",
                    "HR Staff",
                    "Payroll Staff",
                    "Employee"
                ],
                true
            )
        ) {

            $error = "Invalid role selected.";

        } elseif (
            !in_array(
                $status,
                [
                    "Active",
                    "Inactive"
                ],
                true
            )
        ) {

            $error = "Invalid status selected.";

        } elseif (
            $new_password !== "" &&
            strlen($new_password) < 8
        ) {

            $error = "New password must be at least 8 characters.";

        } elseif (
            $new_password !== "" &&
            $new_password !== $confirm_password
        ) {

            $error = "New password and confirmation password do not match.";
        }
    }

    /* ---------------------------------------------------------
       CHECK DUPLICATE USERNAME
    --------------------------------------------------------- */

    if ($error === "") {

        $stmt = $conn->prepare("
            SELECT User_ID
            FROM users
            WHERE Username = ?
            AND User_ID != ?
        ");

        if (!$stmt) {

            $error = "Unable to check username.";

        } else {

            $stmt->bind_param(
                "si",
                $username,
                $user_id
            );

            $stmt->execute();

            $duplicate = $stmt->get_result();

            if ($duplicate->num_rows > 0) {
                $error = "Username is already being used by another account.";
            }

            $stmt->close();
        }
    }

    /* ---------------------------------------------------------
       EMPLOYEE VALIDATION
    --------------------------------------------------------- */

    if (
        $error === "" &&
        $employee_id !== ""
    ) {

        if (
            !ctype_digit($employee_id) ||
            (int)$employee_id <= 0
        ) {

            $error = "Invalid employee selected.";

        } else {

            $employee_id_int = (int)$employee_id;

            /* CHECK EMPLOYEE EXISTS */

            $stmt = $conn->prepare("
                SELECT Emp_ID
                FROM employee
                WHERE Emp_ID = ?
                AND Status = 'Active'
            ");

            if (!$stmt) {

                $error = "Unable to verify employee.";

            } else {

                $stmt->bind_param(
                    "i",
                    $employee_id_int
                );

                $stmt->execute();

                $employee_result = $stmt->get_result();

                if ($employee_result->num_rows === 0) {

                    $error =
                        "Selected employee does not exist or is inactive.";
                }

                $stmt->close();
            }

            /* CHECK IF EMPLOYEE IS ALREADY LINKED */

            if ($error === "") {

                $stmt = $conn->prepare("
                    SELECT User_ID
                    FROM users
                    WHERE Emp_ID = ?
                    AND User_ID != ?
                ");

                if (!$stmt) {

                    $error =
                        "Unable to verify employee account.";

                } else {

                    $stmt->bind_param(
                        "ii",
                        $employee_id_int,
                        $user_id
                    );

                    $stmt->execute();

                    $employee_account =
                        $stmt->get_result();

                    if ($employee_account->num_rows > 0) {

                        $error =
                            "This employee is already linked to another user account.";
                    }

                    $stmt->close();
                }
            }
        }
    }

    /* ---------------------------------------------------------
       PREVENT ADMIN FROM DEACTIVATING OWN ACCOUNT
    --------------------------------------------------------- */

    if ($error === "") {

        if (
            $user_id === (int)$_SESSION["User_ID"] &&
            $status === "Inactive"
        ) {

            $error =
                "You cannot deactivate your own administrator account.";
        }
    }

    /* ---------------------------------------------------------
   PREVENT ADMIN FROM REMOVING OWN ADMINISTRATOR ROLE
--------------------------------------------------------- */

    if ($error === "") {
        if (
            $user_id === (int)$_SESSION["User_ID"] &&
            $role !== "Administrator"
        ) {
            $error =
                "You cannot remove your own administrator privileges.";
        }
    }

    /* ---------------------------------------------------------
       UPDATE USER
    --------------------------------------------------------- */

    if ($error === "") {

        $employee_value = null;

        if ($employee_id !== "") {
            $employee_value = (int)$employee_id;
        }

        $conn->begin_transaction();

        try {

            /* UPDATE WITH PASSWORD */

            if ($new_password !== "") {

                $password_hash = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );

                $stmt = $conn->prepare("
                    UPDATE users
                    SET
                        Username = ?,
                        Password_Hash = ?,
                        Full_Name = ?,
                        Role = ?,
                        Emp_ID = ?,
                        Status = ?
                    WHERE User_ID = ?
                ");

                if (!$stmt) {
                    throw new Exception(
                        "Failed to prepare user update."
                    );
                }

                $stmt->bind_param(
                    "ssssisi",
                    $username,
                    $password_hash,
                    $full_name,
                    $role,
                    $employee_value,
                    $status,
                    $user_id
                );

            } else {

                /* UPDATE WITHOUT PASSWORD */

                $stmt = $conn->prepare("
                    UPDATE users
                    SET
                        Username = ?,
                        Full_Name = ?,
                        Role = ?,
                        Emp_ID = ?,
                        Status = ?
                    WHERE User_ID = ?
                ");

                if (!$stmt) {
                    throw new Exception(
                        "Failed to prepare user update."
                    );
                }

                $stmt->bind_param(
                    "sssisi",
                    $username,
                    $full_name,
                    $role,
                    $employee_value,
                    $status,
                    $user_id
                );
            }

            /* EXECUTE UPDATE */

            if (!$stmt->execute()) {

                throw new Exception(
                    "Failed to update user."
                );
            }

            $stmt->close();

            /* AUDIT LOG */

            $description =
                "Updated user account ID " .
                $user_id .
                " (" .
                $username .
                ").";

            if ($new_password !== "") {

                $description .=
                    " Password was reset.";
            }

            write_audit_log(
                $conn,
                $_SESSION["User_ID"],
                "UPDATE_USER",
                $description
            );

            /* COMMIT */

            $conn->commit();

            header(
                "Location: user_management.php?updated=1"
            );

            exit;

        } catch (Exception $e) {

            $conn->rollback();

            $error =
                "Unable to update the user account. Please try again.";
        }
    }
}

/* ---------------------------------------------------------
   ESCAPE HELPER
--------------------------------------------------------- */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Edit User - Payroll System</title>

    <style>

        * {
            box-sizing: border-box;
        }

        :root {
            --bg: #090b0d;
            --panel: #111417;
            --panel-2: #15191c;

            --maroon: #641c28;
            --maroon-dark: #3a0b13;
            --maroon-light: #7a2635;

            --gold: #c9a66b;
            --gold-soft: rgba(201, 166, 107, 0.12);

            --text: #f4f0ea;
            --muted: #aaa3a0;

            --border: rgba(255,255,255,0.08);

            --danger: #e48787;
            --danger-bg: rgba(180, 55, 65, 0.14);

            --success: #8fd3a8;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(
                    circle at top right,
                    rgba(100, 28, 40, 0.18),
                    transparent 35%
                ),
                var(--bg);
            color: var(--text);
        }

        .page {
            width: 92%;
            max-width: 1050px;
            margin: 0 auto;
            padding: 34px 0 60px;
        }

        /* -------------------------------------------------
           TOPBAR
        ------------------------------------------------- */

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 28px;
        }

        .title-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .title-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;

            display: grid;
            place-items: center;

            background: var(--gold-soft);
            border: 1px solid rgba(201,166,107,0.22);

            color: var(--gold);
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

        .title-area h1 {
            margin: 0;
            font-size: 25px;
            font-weight: 700;
            color: var(--text);
        }

        .title-area p {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 9px;

            text-decoration: none;

            padding: 11px 16px;

            border-radius: 10px;

            background: var(--panel);
            border: 1px solid var(--border);

            color: var(--text);

            font-size: 14px;
            font-weight: 600;

            transition: 0.2s ease;
        }

        .back-btn svg {
            width: 17px;
            height: 17px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .back-btn:hover {
            background: var(--maroon);
            border-color: var(--maroon-light);
            color: white;
        }

        /* -------------------------------------------------
           MAIN CARD
        ------------------------------------------------- */

        .card {
            background:
                linear-gradient(
                    145deg,
                    rgba(255,255,255,0.025),
                    rgba(255,255,255,0.01)
                ),
                var(--panel);

            border: 1px solid var(--border);

            border-radius: 18px;

            padding: 30px;

            box-shadow:
                0 18px 45px rgba(0,0,0,0.25);
        }

        /* -------------------------------------------------
           ERROR
        ------------------------------------------------- */

        .error {
            display: flex;
            align-items: flex-start;
            gap: 12px;

            background: var(--danger-bg);
            border: 1px solid rgba(228,135,135,0.2);

            color: var(--danger);

            padding: 14px 16px;

            border-radius: 11px;

            margin-bottom: 24px;

            font-size: 14px;
            line-height: 1.5;
        }

        .error-icon {
            flex-shrink: 0;
            width: 19px;
            height: 19px;
            margin-top: 1px;
        }

        .error-icon svg {
            width: 19px;
            height: 19px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* -------------------------------------------------
           SECTION TITLE
        ------------------------------------------------- */

        .section {
            margin-bottom: 28px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;

            margin: 0 0 18px;

            padding-bottom: 13px;

            border-bottom: 1px solid var(--border);

            color: var(--text);

            font-size: 16px;
            font-weight: 700;
        }

        .section-title-icon {
            width: 20px;
            height: 20px;
            color: var(--gold);
        }

        .section-title-icon svg {
            width: 20px;
            height: 20px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* -------------------------------------------------
           FORM
        ------------------------------------------------- */

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 19px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .full {
            grid-column: 1 / -1;
        }

        label {
            margin-bottom: 8px;

            color: #e7e0da;

            font-size: 13px;
            font-weight: 600;
        }

        input,
        select {
            width: 100%;

            padding: 12px 13px;

            border-radius: 10px;

            border: 1px solid rgba(255,255,255,0.09);

            background: #0c0f11;

            color: var(--text);

            font-size: 14px;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }

        input::placeholder {
            color: #777;
        }

        input:hover,
        select:hover {
            border-color: rgba(201,166,107,0.28);
        }

        input:focus,
        select:focus {
            outline: none;

            border-color: var(--gold);

            box-shadow:
                0 0 0 3px rgba(201,166,107,0.09);
        }

        select {
            cursor: pointer;
        }

        select option {
            background: #111417;
            color: #f4f0ea;
        }

        .help {
            margin-top: 7px;

            color: var(--muted);

            font-size: 12px;
            line-height: 1.5;
        }

        /* -------------------------------------------------
           PASSWORD AREA
        ------------------------------------------------- */

        .password-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;

            padding: 12px 14px;

            margin-bottom: 18px;

            border-radius: 10px;

            background: rgba(201,166,107,0.07);

            border: 1px solid rgba(201,166,107,0.12);

            color: #cfc2af;

            font-size: 12px;
            line-height: 1.5;
        }

        .password-note svg {
            width: 18px;
            height: 18px;

            flex-shrink: 0;

            color: var(--gold);

            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* -------------------------------------------------
           BUTTONS
        ------------------------------------------------- */

        .actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;

            gap: 11px;

            margin-top: 10px;

            padding-top: 24px;

            border-top: 1px solid var(--border);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;

            min-height: 43px;

            padding: 11px 18px;

            border-radius: 10px;

            font-size: 14px;
            font-weight: 600;

            cursor: pointer;

            text-decoration: none;

            transition:
                background 0.2s ease,
                border-color 0.2s ease,
                transform 0.2s ease;
        }

        .btn svg {
            width: 17px;
            height: 17px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .btn-secondary {
            background: #171b1e;

            border: 1px solid var(--border);

            color: #ddd6cf;
        }

        .btn-secondary:hover {
            background: #20252a;
            border-color: rgba(255,255,255,0.14);
        }

        .btn-primary {
            background: var(--maroon);

            border: 1px solid var(--maroon-light);

            color: white;
        }

        .btn-primary:hover {
            background: var(--maroon-light);
            transform: translateY(-1px);
        }

        /* -------------------------------------------------
           RESPONSIVE
        ------------------------------------------------- */

        @media (max-width: 760px) {

            .page {
                width: 94%;
                padding-top: 24px;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .back-btn {
                width: 100%;
                justify-content: center;
            }

            .card {
                padding: 22px;
                border-radius: 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .full {
                grid-column: auto;
            }

            .actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {

            .title-area {
                align-items: flex-start;
            }

            .title-icon {
                width: 43px;
                height: 43px;
            }

            .title-area h1 {
                font-size: 21px;
            }

            .card {
                padding: 18px;
            }
        }

    </style>

</head>

<body>

<div class="page">

    <!-- TOPBAR -->

    <div class="topbar">

        <div class="title-area">

            <div class="title-icon">

                <!-- User edit icon -->

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path d="M15 20H6a3 3 0 0 1-3-3v-1a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5"/>
                    <circle cx="9" cy="6" r="3.5"/>
                    <path d="M16 13l5-5"/>
                    <path d="M19 7l2 2"/>
                    <path d="M15 16l-1 4 4-1 5-5-3-3-5 5z"/>
                </svg>

            </div>

            <div>

                <h1>Edit User Account</h1>

                <p>
                    Update account details, access role, employee link, or password.
                </p>

            </div>

        </div>

        <a
            href="user_management.php"
            class="back-btn"
        >

            <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path d="M19 12H5"/>
                <path d="M12 19l-7-7 7-7"/>
            </svg>

            Back to User Management

        </a>

    </div>


    <!-- MAIN CARD -->

    <div class="card">

        <?php if ($error !== ""): ?>

            <div class="error">

                <div class="error-icon">

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 8v5"/>
                        <path d="M12 16h.01"/>
                    </svg>

                </div>

                <div>
                    <?php echo e($error); ?>
                </div>

            </div>

        <?php endif; ?>


        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e(csrf_token()); ?>"
            >


            <!-- ACCOUNT INFORMATION -->

            <div class="section">

                <h2 class="section-title">

                    <span class="section-title-icon">

                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="8" r="3.5"/>
                            <path d="M5 20a7 7 0 0 1 14 0"/>
                        </svg>

                    </span>

                    Account Information

                </h2>


                <div class="form-grid">

                    <!-- USERNAME -->

                    <div class="form-group">

                        <label for="username">
                            Username
                        </label>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            maxlength="50"
                            required
                            value="<?php
                                echo e(
                                    $_POST["username"]
                                    ?? $user["Username"]
                                );
                            ?>"
                        >

                    </div>


                    <!-- FULL NAME -->

                    <div class="form-group">

                        <label for="full_name">
                            Full Name
                        </label>

                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            maxlength="150"
                            required
                            value="<?php
                                echo e(
                                    $_POST["full_name"]
                                    ?? $user["Full_Name"]
                                );
                            ?>"
                        >

                    </div>


                    <!-- ROLE -->

                    <div class="form-group">

                        <label for="role">
                            Role
                        </label>

                        <?php
                        $selected_role =
                            $_POST["role"]
                            ?? $user["Role"];
                        ?>

                        <select
                            id="role"
                            name="role"
                            required
                        >

                            <option
                                value="Administrator"
                                <?php
                                echo $selected_role === "Administrator"
                                    ? "selected"
                                    : "";
                                ?>
                            >
                                Administrator
                            </option>

                            <option
                                value="HR Staff"
                                <?php
                                echo $selected_role === "HR Staff"
                                    ? "selected"
                                    : "";
                                ?>
                            >
                                HR Staff
                            </option>

                            <option
                                value="Payroll Staff"
                                <?php
                                echo $selected_role === "Payroll Staff"
                                    ? "selected"
                                    : "";
                                ?>
                            >
                                Payroll Staff
                            </option>

                            <option
                                value="Employee"
                                <?php
                                echo $selected_role === "Employee"
                                    ? "selected"
                                    : "";
                                ?>
                            >
                                Employee
                            </option>

                        </select>

                    </div>


                    <!-- STATUS -->

                    <div class="form-group">

                        <label for="status">
                            Account Status
                        </label>

                        <?php
                        $selected_status =
                            $_POST["status"]
                            ?? $user["Status"];
                        ?>

                        <select
                            id="status"
                            name="status"
                            required
                        >

                            <option
                                value="Active"
                                <?php
                                echo $selected_status === "Active"
                                    ? "selected"
                                    : "";
                                ?>
                            >
                                Active
                            </option>

                            <option
                                value="Inactive"
                                <?php
                                echo $selected_status === "Inactive"
                                    ? "selected"
                                    : "";
                                ?>
                            >
                                Inactive
                            </option>

                        </select>

                    </div>


                    <!-- EMPLOYEE LINK -->

                    <div class="form-group full">

                        <label for="employee_id">
                            Link to Employee
                        </label>

                        <?php
                        $selected_employee =
                            $_POST["employee_id"]
                            ?? ($user["Emp_ID"] ?? "");
                        ?>

                        <select
                            id="employee_id"
                            name="employee_id"
                        >

                            <option value="">
                                -- No Employee Link --
                            </option>

                            <?php foreach ($employees as $employee): ?>

                                <option
                                    value="<?php
                                        echo e(
                                            $employee["Emp_ID"]
                                        );
                                    ?>"
                                    <?php
                                    echo
                                        (string)$selected_employee ===
                                        (string)$employee["Emp_ID"]
                                        ? "selected"
                                        : "";
                                    ?>
                                >

                                    <?php
                                    echo e(
                                        $employee["Last_Name"] .
                                        ", " .
                                        $employee["First_Name"] .
                                        " (ID: " .
                                        $employee["Emp_ID"] .
                                        ")"
                                    );
                                    ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <div class="help">
                            Employee accounts should be linked to the employee's employee record.
                        </div>

                    </div>

                </div>

            </div>


            <!-- PASSWORD RESET -->

            <div class="section">

                <h2 class="section-title">

                    <span class="section-title-icon">

                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <rect
                                x="5"
                                y="10"
                                width="14"
                                height="10"
                                rx="2"
                            />
                            <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                            <path d="M12 14v3"/>
                        </svg>

                    </span>

                    Password Reset

                </h2>


                <div class="password-note">

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 8v4"/>
                        <path d="M12 16h.01"/>
                    </svg>

                    <div>
                        Leave the password fields blank if the current password
                        should remain unchanged. Enter a new password only when
                        a password reset is required.
                    </div>

                </div>


                <div class="form-grid">

                    <!-- NEW PASSWORD -->

                    <div class="form-group">

                        <label for="new_password">
                            New Password
                        </label>

                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            minlength="8"
                        >

                        <div class="help">
                            Minimum of 8 characters.
                        </div>

                    </div>


                    <!-- CONFIRM PASSWORD -->

                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm New Password
                        </label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            minlength="8"
                        >

                        <div class="help">
                            Re-enter the new password to confirm the reset.
                        </div>

                    </div>

                </div>

            </div>


            <!-- ACTION BUTTONS -->

            <div class="actions">

                <a
                    href="user_management.php"
                    class="btn btn-secondary"
                >

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path d="M18 6L6 18"/>
                        <path d="M6 6l12 12"/>
                    </svg>

                    Cancel

                </a>


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path d="M5 12l4 4L19 6"/>
                    </svg>

                    Save Changes

                </button>

            </div>

        </form>

    </div>

</div>

</body>

</html>