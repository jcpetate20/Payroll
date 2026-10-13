<?php

session_start();

require_once "db.php";
require_once "csrf.php";
require_once "session_security.php";
require_once "audit_log.php";

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
| Administrator Only
|--------------------------------------------------------------------------
*/
if ($_SESSION["Role"] !== "Administrator") {
    http_response_code(403);
    die("
        <!DOCTYPE html>
        <html>
        <head>
            <title>Access Denied</title>
            <style>
                body {
                    margin: 0;
                    min-height: 100vh;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    background: #090b0d;
                    color: #F4EFE8;
                    font-family: Arial, sans-serif;
                }

                .denied-box {
                    width: 90%;
                    max-width: 500px;
                    padding: 40px;
                    text-align: center;
                    background: #111417;
                    border: 1px solid #282d31;
                    border-radius: 16px;
                }

                h2 {
                    color: #D47D85;
                    margin-top: 0;
                }

                p {
                    color: #9CA3A8;
                }

                a {
                    display: inline-block;
                    margin-top: 15px;
                    padding: 11px 18px;
                    border-radius: 8px;
                    background: #641C28;
                    color: #F4EFE8;
                    text-decoration: none;
                }

                a:hover {
                    background: #7A2635;
                }
            </style>
        </head>
        <body>
            <div class='denied-box'>
                <h2>Access Denied</h2>
                <p>
                    Only administrators can create system users.
                </p>
                <a href='dashboard.php'>
                    Return to Dashboard
                </a>
            </div>
        </body>
        </html>
    ");
}

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/
$error = "";
$username = "";
$full_name = "";
$role = "";
$emp_id = "";
$status = "Active";

/*
|--------------------------------------------------------------------------
| Load Employees
|--------------------------------------------------------------------------
*/
$employees = [];

$employee_sql = "
    SELECT
        Emp_ID,
        First_Name,
        Last_Name
    FROM employee
    WHERE Status = 'Active'
    ORDER BY Last_Name ASC, First_Name ASC
";

$employee_stmt = $conn->prepare($employee_sql);

if ($employee_stmt) {

    $employee_stmt->execute();

    $employee_result = $employee_stmt->get_result();

    while ($employee = $employee_result->fetch_assoc()) {
        $employees[] = $employee;
    }

    $employee_stmt->close();
}

/*
|--------------------------------------------------------------------------
| Process Form
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
    |--------------------------------------------------------------------------
    | CSRF Validation
    |--------------------------------------------------------------------------
    */
    if (
        !isset($_POST["csrf_token"]) ||
        !verify_csrf_token($_POST["csrf_token"])
    ) {

        $error =
            "Invalid security token. Please refresh the page and try again.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Get Form Values
        |--------------------------------------------------------------------------
        */
        $username = trim($_POST["username"] ?? "");
        $full_name = trim($_POST["full_name"] ?? "");
        $password = $_POST["password"] ?? "";
        $confirm_password = $_POST["confirm_password"] ?? "";
        $role = trim($_POST["role"] ?? "");
        $emp_id = trim($_POST["emp_id"] ?? "");
        $status = trim($_POST["status"] ?? "Active");

        /*
        |--------------------------------------------------------------------------
        | Validate Username
        |--------------------------------------------------------------------------
        */
        if ($username === "") {

            $error = "Username is required.";

        } elseif (strlen($username) < 4) {

            $error = "Username must be at least 4 characters.";

        } elseif (strlen($username) > 50) {

            $error = "Username cannot exceed 50 characters.";

        } elseif (
            !preg_match(
                "/^[A-Za-z0-9._-]+$/",
                $username
            )
        ) {

            $error =
                "Username can only contain letters, numbers, dot, underscore, and hyphen.";
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Full Name
        |--------------------------------------------------------------------------
        */
        if ($error === "" && $full_name === "") {

            $error = "Full name is required.";

        } elseif (
            $error === "" &&
            !preg_match(
                "/^[a-zA-ZÀ-ÿ .'-]+$/",
                $full_name
            )
        ) {

            $error = "Full name contains invalid characters.";

        } elseif (
            $error === "" &&
            strlen($full_name) > 150
        ) {

            $error = "Full name cannot exceed 150 characters.";
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Password
        |--------------------------------------------------------------------------
        */
        if ($error === "" && $password === "") {

            $error = "Password is required.";

        } elseif (
            $error === "" &&
            strlen($password) < 8
        ) {

            $error = "Password must be at least 8 characters.";

        } elseif (
            $error === "" &&
            $password !== $confirm_password
        ) {

            $error = "Passwords do not match.";
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Role
        |--------------------------------------------------------------------------
        */
        $allowed_roles = [
            "Administrator",
            "HR Staff",
            "Payroll Staff",
            "Employee"
        ];

        if (
            $error === "" &&
            !in_array($role, $allowed_roles, true)
        ) {

            $error = "Invalid user role.";
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Status
        |--------------------------------------------------------------------------
        */
        if (
            $error === "" &&
            !in_array(
                $status,
                ["Active", "Inactive"],
                true
            )
        ) {

            $error = "Invalid account status.";
        }

        /*
        |--------------------------------------------------------------------------
        | Check Username Already Exists
        |--------------------------------------------------------------------------
        */
        if ($error === "") {

            $check_sql = "
                SELECT User_ID
                FROM users
                WHERE Username = ?
                LIMIT 1
            ";

            $check_stmt = $conn->prepare($check_sql);

            if (!$check_stmt) {

                $error = "Unable to validate username.";

            } else {

                $check_stmt->bind_param(
                    "s",
                    $username
                );

                $check_stmt->execute();

                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows > 0) {
                    $error = "Username already exists.";
                }

                $check_stmt->close();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Employee Link
        |--------------------------------------------------------------------------
        */
        $employee_value = null;

        if (
            $error === "" &&
            $emp_id !== ""
        ) {

            if (!ctype_digit($emp_id)) {

                $error = "Invalid employee selected.";

            } else {

                $employee_id = (int)$emp_id;

                $employee_check_sql = "
                    SELECT Emp_ID
                    FROM employee
                    WHERE Emp_ID = ?
                    LIMIT 1
                ";

                $employee_check_stmt =
                    $conn->prepare(
                        $employee_check_sql
                    );

                if (!$employee_check_stmt) {

                    $error = "Unable to validate employee.";

                } else {

                    $employee_check_stmt->bind_param(
                        "i",
                        $employee_id
                    );

                    $employee_check_stmt->execute();

                    $employee_check_result =
                        $employee_check_stmt->get_result();

                    if (
                        $employee_check_result->num_rows === 0
                    ) {

                        $error =
                            "Selected employee does not exist.";

                    } else {

                        $employee_value = $employee_id;
                    }

                    $employee_check_stmt->close();
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent Duplicate Employee Account
        |--------------------------------------------------------------------------
        */
        if (
            $error === "" &&
            $employee_value !== null
        ) {

            $employee_user_sql = "
                SELECT User_ID
                FROM users
                WHERE Emp_ID = ?
                LIMIT 1
            ";

            $employee_user_stmt =
                $conn->prepare(
                    $employee_user_sql
                );

            if (!$employee_user_stmt) {

                $error =
                    "Unable to validate employee account.";

            } else {

                $employee_user_stmt->bind_param(
                    "i",
                    $employee_value
                );

                $employee_user_stmt->execute();

                $employee_user_result =
                    $employee_user_stmt->get_result();

                if (
                    $employee_user_result->num_rows > 0
                ) {

                    $error =
                        "This employee already has a system account.";
                }

                $employee_user_stmt->close();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Create User
        |--------------------------------------------------------------------------
        */
        if ($error === "") {

            $password_hash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            if ($password_hash === false) {

                $error =
                    "Unable to secure the password.";

            } else {

                $insert_sql = "
                    INSERT INTO users
                    (
                        Username,
                        Password_Hash,
                        Full_Name,
                        Role,
                        Emp_ID,
                        Status
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ";

                $insert_stmt =
                    $conn->prepare($insert_sql);

                if (!$insert_stmt) {

                    $error =
                        "Unable to create user account.";

                } else {

                    $insert_stmt->bind_param(
                        "ssssis",
                        $username,
                        $password_hash,
                        $full_name,
                        $role,
                        $employee_value,
                        $status
                    );

                    if ($insert_stmt->execute()) {

                        $new_user_id =
                            $insert_stmt->insert_id;

                        /*
                        |--------------------------------------------------------------------------
                        | Audit Log
                        |--------------------------------------------------------------------------
                        */
                        write_audit_log(
                            $conn,
                            $_SESSION["User_ID"],
                            "CREATE_USER",
                            "Created system user: " . $username .
                            " (User ID: " . $new_user_id . ")"
                        );

                        $insert_stmt->close();

                        header(
                            "Location: user_management.php?added=1"
                        );

                        exit;

                    } else {

                        $error =
                            "Failed to create user account.";

                        $insert_stmt->close();
                    }
                }
            }
        }
    }
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

    <title>Add User | Payroll System</title>

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
            --maroon-dark: #4A0E18;
            --maroon-light: #7A2635;

            --gold: #C9A66B;
            --gold-soft: rgba(201, 166, 107, 0.12);
            --gold-border: rgba(201, 166, 107, 0.25);

            --text: #F4EFE8;
            --muted: #9CA3A8;
            --muted-light: #C4C8CA;

            --green: #79B89A;
            --red: #D47D85;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        /* =========================
           HEADER
        ========================= */

        .header {
            min-height: 76px;
            background: #0c0f11;
            border-bottom: 1px solid var(--border);

            padding: 16px 40px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 20px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .brand-icon {
            width: 43px;
            height: 43px;

            border-radius: 13px;

            background: var(--gold-soft);
            color: var(--gold);

            display: grid;
            place-items: center;

            border: 1px solid var(--gold-border);
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
            gap: 2px;
        }

        .brand-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
        }

        .brand-subtitle {
            font-size: 12px;
            color: var(--muted);
        }

        .header-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            color: var(--text);
            text-decoration: none;

            background: var(--maroon);
            border: 1px solid rgba(255,255,255,0.05);

            padding: 11px 16px;
            border-radius: 8px;

            font-size: 14px;
            font-weight: 600;

            transition:
                background 0.2s ease,
                border-color 0.2s ease;
        }

        .header-button:hover {
            background: var(--maroon-light);
        }

        .header-button svg {
            width: 17px;
            height: 17px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* =========================
           MAIN
        ========================= */

        .container {
            width: 92%;
            max-width: 850px;
            margin: 42px auto 60px;
        }

        .page-heading {
            margin-bottom: 22px;
        }

        .page-heading h1 {
            margin: 0 0 7px;
            font-size: 28px;
            color: var(--text);
        }

        .page-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        /* =========================
           FORM CARD
        ========================= */

        .form-card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 16px;

            padding: 30px;

            box-shadow:
                0 18px 45px rgba(0,0,0,0.22);
        }

        .form-card-header {
            display: flex;
            align-items: center;
            gap: 14px;

            padding-bottom: 22px;
            margin-bottom: 25px;

            border-bottom: 1px solid var(--border);
        }

        .form-icon {
            width: 44px;
            height: 44px;

            border-radius: 12px;

            display: grid;
            place-items: center;

            background: var(--gold-soft);
            border: 1px solid var(--gold-border);

            color: var(--gold);
        }

        .form-icon svg {
            width: 23px;
            height: 23px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .form-title {
            margin: 0;
            font-size: 19px;
            color: var(--text);
        }

        .form-subtitle {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        /* =========================
           ERROR
        ========================= */

        .error {
            display: flex;
            align-items: flex-start;
            gap: 11px;

            background: rgba(212,125,133,0.10);
            border: 1px solid rgba(212,125,133,0.30);

            color: #e7a4aa;

            padding: 13px 15px;
            border-radius: 9px;

            margin-bottom: 22px;

            font-size: 14px;
            line-height: 1.5;
        }

        .error svg {
            width: 19px;
            height: 19px;

            min-width: 19px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* =========================
           FORM
        ========================= */

        .form-group {
            margin-bottom: 21px;
        }

        label {
            display: block;

            margin-bottom: 8px;

            color: var(--muted-light);

            font-size: 13px;
            font-weight: 600;
        }

        .required {
            color: var(--gold);
            margin-left: 2px;
        }

        input,
        select {
            width: 100%;

            padding: 12px 13px;

            border: 1px solid var(--border);
            border-radius: 8px;

            background: #0d1012;
            color: var(--text);

            font-family: inherit;
            font-size: 14px;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }

        input::placeholder {
            color: #697177;
        }

        input:hover,
        select:hover {
            border-color: #3a4146;
        }

        input:focus,
        select:focus {
            outline: none;

            border-color: var(--gold);

            box-shadow:
                0 0 0 3px rgba(201,166,107,0.10);

            background: #101416;
        }

        select {
            cursor: pointer;
        }

        select option {
            background: #111417;
            color: var(--text);
        }

        .help {
            display: block;

            margin-top: 7px;

            color: var(--muted);

            font-size: 12px;
            line-height: 1.5;
        }

        /* =========================
           FORM SECTIONS
        ========================= */

        .section-label {
            display: flex;
            align-items: center;
            gap: 9px;

            margin: 27px 0 18px;

            color: var(--gold);

            font-size: 13px;
            font-weight: 700;

            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .section-label::after {
            content: "";
            flex: 1;

            height: 1px;

            background: var(--border);
        }

        /* =========================
           BUTTONS
        ========================= */

        .buttons {
            display: flex;
            gap: 11px;

            margin-top: 28px;
            padding-top: 23px;

            border-top: 1px solid var(--border);
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            min-height: 43px;

            padding: 11px 18px;

            border-radius: 8px;

            border: 1px solid transparent;

            text-decoration: none;

            cursor: pointer;

            font-family: inherit;
            font-size: 14px;
            font-weight: 600;

            transition:
                background 0.2s ease,
                border-color 0.2s ease,
                transform 0.2s ease;
        }

        .button svg {
            width: 17px;
            height: 17px;

            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .save {
            background: var(--maroon);
            color: var(--text);
        }

        .save:hover {
            background: var(--maroon-light);
        }

        .cancel {
            background: #15191c;
            color: var(--muted-light);

            border-color: var(--border);
        }

        .cancel:hover {
            background: #1b2024;
            border-color: #3a4146;
            color: var(--text);
        }

        .button:active {
            transform: translateY(1px);
        }

        /* =========================
           PASSWORD NOTE
        ========================= */

        .security-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;

            margin-top: 5px;
            margin-bottom: 23px;

            padding: 12px 13px;

            border-radius: 8px;

            background: rgba(201,166,107,0.07);
            border: 1px solid rgba(201,166,107,0.15);

            color: var(--muted);

            font-size: 12px;
            line-height: 1.5;
        }

        .security-note svg {
            width: 18px;
            height: 18px;

            min-width: 18px;

            color: var(--gold);

            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 700px) {

            .header {
                padding: 15px 20px;

                flex-direction: column;
                align-items: stretch;
            }

            .header-button {
                justify-content: center;
            }

            .container {
                width: 94%;
                margin-top: 28px;
            }

            .page-heading h1 {
                font-size: 24px;
            }

            .form-card {
                padding: 21px;
                border-radius: 13px;
            }

            .buttons {
                flex-direction: column;
            }

            .button {
                width: 100%;
            }

        }

    </style>

</head>

<body>

<!-- HEADER -->

<header class="header">

    <div class="brand">

        <div class="brand-icon">

            <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-3 4v2"/>
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
                Payroll System
            </div>

            <div class="brand-subtitle">
                User Administration
            </div>

        </div>

    </div>

    <a
        href="user_management.php"
        class="header-button"
    >

        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path d="M19 12H5"/>
            <path d="M12 19l-7-7 7-7"/>
        </svg>

        User Management

    </a>

</header>


<main class="container">

    <div class="page-heading">

        <h1>
            Add New User
        </h1>

        <p>
            Create a secure system account and assign the appropriate access role.
        </p>

    </div>


    <div class="form-card">

        <div class="form-card-header">

            <div class="form-icon">

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path d="M15 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/>
                    <circle
                        cx="9"
                        cy="7"
                        r="4"
                    />
                    <path d="M19 8v6"/>
                    <path d="M22 11h-6"/>
                </svg>

            </div>

            <div>

                <h2 class="form-title">
                    Create New User
                </h2>

                <p class="form-subtitle">
                    Enter the account information below.
                </p>

            </div>

        </div>


        <?php if ($error !== ""): ?>

            <div class="error">

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle
                        cx="12"
                        cy="12"
                        r="9"
                    />
                    <path d="M12 8v4"/>
                    <path d="M12 16h.01"/>
                </svg>

                <span>
                    <?php echo htmlspecialchars($error); ?>
                </span>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action="add_user.php"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars(csrf_token()); ?>"
            >


            <div class="section-label">
                Account Information
            </div>


            <div class="form-group">

                <label for="username">

                    Username

                    <span class="required">
                        *
                    </span>

                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    maxlength="50"
                    value="<?php echo htmlspecialchars($username); ?>"
                    placeholder="Enter username"
                    autocomplete="username"
                    required
                >

                <span class="help">
                    Use 4-50 characters. Letters, numbers, dot, underscore, and hyphen are allowed.
                </span>

            </div>


            <div class="form-group">

                <label for="full_name">

                    Full Name

                    <span class="required">
                        *
                    </span>

                </label>

                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    maxlength="150"
                    value="<?php echo htmlspecialchars($full_name); ?>"
                    placeholder="Enter full name"
                    autocomplete="name"
                    required
                >

            </div>


            <div class="form-group">

                <label for="password">

                    Password

                    <span class="required">
                        *
                    </span>

                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="8"
                    placeholder="Enter password"
                    autocomplete="new-password"
                    required
                >

                <span class="help">
                    Password must contain at least 8 characters.
                </span>

            </div>


            <div class="form-group">

                <label for="confirm_password">

                    Confirm Password

                    <span class="required">
                        *
                    </span>

                </label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="8"
                    placeholder="Confirm password"
                    autocomplete="new-password"
                    required
                >

            </div>


            <div class="security-note">

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
                </svg>

                <span>
                    The password will be securely hashed before being stored in the database.
                </span>

            </div>


            <div class="section-label">
                Access & Employee Assignment
            </div>


            <div class="form-group">

                <label for="role">

                    Role

                    <span class="required">
                        *
                    </span>

                </label>

                <select
                    id="role"
                    name="role"
                    required
                >

                    <option value="">
                        -- Select Role --
                    </option>

                    <option
                        value="Administrator"
                        <?php
                        echo $role === "Administrator"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Administrator
                    </option>

                    <option
                        value="HR Staff"
                        <?php
                        echo $role === "HR Staff"
                            ? "selected"
                            : "";
                        ?>
                    >
                        HR Staff
                    </option>

                    <option
                        value="Payroll Staff"
                        <?php
                        echo $role === "Payroll Staff"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Payroll Staff
                    </option>

                    <option
                        value="Employee"
                        <?php
                        echo $role === "Employee"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Employee
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label for="emp_id">
                    Link to Employee
                </label>

                <select
                    id="emp_id"
                    name="emp_id"
                >

                    <option value="">
                        -- No Employee Link --
                    </option>

                    <?php foreach ($employees as $employee): ?>

                        <option
                            value="<?php echo htmlspecialchars($employee["Emp_ID"]); ?>"
                            <?php
                            echo $emp_id == $employee["Emp_ID"]
                                ? "selected"
                                : "";
                            ?>
                        >

                            <?php

                            echo htmlspecialchars(
                                $employee["First_Name"] .
                                " " .
                                $employee["Last_Name"] .
                                " (ID: " .
                                $employee["Emp_ID"] .
                                ")"
                            );

                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <span class="help">
                    Employees can be linked to their own system account.
                </span>

            </div>


            <div class="form-group">

                <label for="status">

                    Account Status

                    <span class="required">
                        *
                    </span>

                </label>

                <select
                    id="status"
                    name="status"
                    required
                >

                    <option
                        value="Active"
                        <?php
                        echo $status === "Active"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Active
                    </option>

                    <option
                        value="Inactive"
                        <?php
                        echo $status === "Inactive"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Inactive
                    </option>

                </select>

            </div>


            <div class="buttons">

                <button
                    type="submit"
                    class="button save"
                >

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path d="M12 5v14"/>
                        <path d="M5 12h14"/>
                    </svg>

                    Create User

                </button>


                <a
                    href="user_management.php"
                    class="button cancel"
                >

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path d="M19 12H5"/>
                        <path d="M12 19l-7-7 7-7"/>
                    </svg>

                    Cancel

                </a>

            </div>

        </form>

    </div>

</main>

</body>
</html>

<?php

$conn->close();

?>