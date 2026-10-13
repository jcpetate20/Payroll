<?php

session_start();

require_once "db.php";
require_once "csrf.php";
require_once "session_security.php";
require_once "audit_log.php";
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


/*
|--------------------------------------------------------------------------
| Only Administrator and HR Staff
|--------------------------------------------------------------------------
*/
if (
    $_SESSION["Role"] !== "Administrator" &&
    $_SESSION["Role"] !== "HR Staff"
) {
    http_response_code(403);
    die("Access Denied");
}


/*
|--------------------------------------------------------------------------
| Get employee ID
|--------------------------------------------------------------------------
*/
$employee_id = intval($_GET["id"] ?? 0);

if ($employee_id <= 0) {
    die("Invalid employee ID.");
}

$error = "";


/*
|--------------------------------------------------------------------------
| Get employee information
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        Emp_ID,
        First_Name,
        Last_Name,
        Email,
        Phone,
        Hire_Date,
        Status,
        Department_ID,
        Position_ID
    FROM employee
    WHERE Emp_ID = ?
    LIMIT 1
");

if (!$stmt) {
    die("Unable to load employee information.");
}

$stmt->bind_param("i", $employee_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();
    die("Employee not found.");
}

$employee = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Decrypt existing Email and Phone
|--------------------------------------------------------------------------
*/
$display_email = "";
$display_phone = "";

if (!empty($employee["Email"])) {

    $decrypted_email = decrypt_data($employee["Email"]);

    if ($decrypted_email !== false) {
        $display_email = $decrypted_email;
    } else {
        $display_email = $employee["Email"];
    }
}

if (!empty($employee["Phone"])) {

    $decrypted_phone = decrypt_data($employee["Phone"]);

    if ($decrypted_phone !== false) {
        $display_phone = $decrypted_phone;
    } else {
        $display_phone = $employee["Phone"];
    }
}


/*
|--------------------------------------------------------------------------
| Get departments
|--------------------------------------------------------------------------
*/
$departments = $conn->query("
    SELECT
        Department_ID,
        Department_Name
    FROM department
    ORDER BY Department_Name ASC
");


/*
|--------------------------------------------------------------------------
| Get positions
|--------------------------------------------------------------------------
*/
$positions = $conn->query("
    SELECT
        Position_ID,
        Position_Title
    FROM positions
    ORDER BY Position_Title ASC
");


/*
|--------------------------------------------------------------------------
| Update employee
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
    |--------------------------------------------------------------------------
    | CSRF protection
    |--------------------------------------------------------------------------
    */
    $csrf_token = $_POST["csrf_token"] ?? "";

    if (!verify_csrf_token($csrf_token)) {

        http_response_code(403);

        die(
            "Invalid security token. Please return to the Edit Employee page and try again."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Get submitted values
    |--------------------------------------------------------------------------
    */
    $first_name = trim($_POST["first_name"] ?? "");
    $last_name = trim($_POST["last_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $hire_date = trim($_POST["hire_date"] ?? "");

    $department_id = intval($_POST["department_id"] ?? 0);
    $position_id = intval($_POST["position_id"] ?? 0);

    $status = $_POST["status"] ?? "";


    /*
    |--------------------------------------------------------------------------
    | Basic validation
    |--------------------------------------------------------------------------
    */
    if ($first_name === "" || $last_name === "") {

        $error = "First name and last name are required.";

    } elseif (
        !preg_match("/^[a-zA-ZÀ-ÿ .'-]+$/", $first_name) ||
        !preg_match("/^[a-zA-ZÀ-ÿ .'-]+$/", $last_name)
    ) {

        $error =
            "First name and last name contain invalid characters.";

    } elseif (
        $email !== "" &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $error = "Please enter a valid email address.";

    } elseif (
        $phone !== "" &&
        !preg_match("/^[0-9+\\-\s()]+$/", $phone)
    ) {

        $error = "Please enter a valid phone number.";

    } elseif ($hire_date === "") {

        $error = "Please select a hire date.";

    } elseif (strtotime($hire_date) === false) {

        $error = "Please enter a valid hire date.";

    } elseif ($department_id <= 0) {

        $error = "Please select a department.";

    } elseif ($position_id <= 0) {

        $error = "Please select a position.";

    } elseif (
        $status !== "Active" &&
        $status !== "Inactive"
    ) {

        $error = "Invalid employee status.";
    }


    /*
    |--------------------------------------------------------------------------
    | Validate department exists
    |--------------------------------------------------------------------------
    */
    if ($error === "") {

        $department_check = $conn->prepare("
            SELECT Department_ID
            FROM department
            WHERE Department_ID = ?
        ");

        if (!$department_check) {

            $error =
                "Unable to validate the selected department.";

        } else {

            $department_check->bind_param(
                "i",
                $department_id
            );

            $department_check->execute();

            $department_result =
                $department_check->get_result();

            if ($department_result->num_rows === 0) {

                $error =
                    "The selected department does not exist.";
            }

            $department_check->close();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Validate position belongs to department
    |--------------------------------------------------------------------------
    */
    if ($error === "") {

        $position_check = $conn->prepare("
            SELECT Position_ID
            FROM positions
            WHERE Position_ID = ?
              AND Department_ID = ?
        ");

        if (!$position_check) {

            $error =
                "Unable to validate the selected position.";

        } else {

            $position_check->bind_param(
                "ii",
                $position_id,
                $department_id
            );

            $position_check->execute();

            $position_result =
                $position_check->get_result();

            if ($position_result->num_rows === 0) {

                $error =
                    "The selected position does not belong to the selected department.";
            }

            $position_check->close();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Encrypt Email and Phone
    |--------------------------------------------------------------------------
    */
    if ($error === "") {

        $email_encrypted =
            $email === ""
            ? ""
            : encrypt_data($email);

        $phone_encrypted =
            $phone === ""
            ? ""
            : encrypt_data($phone);


        /*
        |--------------------------------------------------------------------------
        | Check encryption result
        |--------------------------------------------------------------------------
        */
        if (
            $email_encrypted === false ||
            $phone_encrypted === false
        ) {

            $error =
                "Unable to securely encrypt employee contact information.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Update only if all validation passes
    |--------------------------------------------------------------------------
    */
    if ($error === "") {

        $update = $conn->prepare("
            UPDATE employee
            SET
                First_Name = ?,
                Last_Name = ?,
                Email = ?,
                Phone = ?,
                Hire_Date = ?,
                Status = ?,
                Department_ID = ?,
                Position_ID = ?
            WHERE Emp_ID = ?
        ");

        if (!$update) {

            $error =
                "Unable to prepare the employee update.";

        } else {

            $update->bind_param(
                "ssssssiii",
                $first_name,
                $last_name,
                $email_encrypted,
                $phone_encrypted,
                $hire_date,
                $status,
                $department_id,
                $position_id,
                $employee_id
            );

            if ($update->execute()) {

                /*
                |--------------------------------------------------------------------------
                | Audit Log
                |--------------------------------------------------------------------------
                */
                write_audit_log(
                    $conn,
                    $_SESSION["User_ID"],
                    "UPDATE_EMPLOYEE",
                    "Employee ID " . $employee_id .
                    " information updated"
                );

                $update->close();

                header(
                    "Location: employee_management.php?updated=1"
                );

                exit;

            } else {

                $error =
                    "Failed to update employee: " .
                    $update->error;

                $update->close();
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

    <title>Edit Employee | Payroll & Benefits</title>

    <style>

        * {
            box-sizing: border-box;
        }

        :root {
            --bg: #090b0d;
            --header: #0d1013;
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
            min-height: 100vh;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: var(--bg);
            color: var(--text);
        }


        /* =========================================================
           HEADER
        ========================================================= */

        .header {
            min-height: 74px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 14px 35px;

            background: var(--header);

            border-bottom: 1px solid #24282c;
        }


        .brand-section {
            display: flex;
            align-items: center;
            gap: 13px;
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

            font-size: 16px;

            font-weight: 600;

            letter-spacing: -0.2px;
        }


        .brand-text p {
            margin: 3px 0 0;

            font-size: 11px;

            color: var(--muted);
        }


        .user-section {
            display: flex;
            align-items: center;

            gap: 11px;

            text-align: right;
        }


        .user-avatar {
            width: 38px;
            height: 38px;

            border-radius: 50%;

            background:
                linear-gradient(
                    135deg,
                    #C9A66B,
                    #8E6341
                );

            color: #fff;

            display: grid;
            place-items: center;
        }


        .user-avatar svg {
            width: 19px;
            height: 19px;

            fill: none;

            stroke: currentColor;

            stroke-width: 1.7;

            stroke-linecap: round;
            stroke-linejoin: round;
        }


        .user-name {
            font-size: 12px;

            color: var(--text);

            font-weight: 600;
        }


        .user-role {
            margin-top: 3px;

            font-size: 10px;

            color: var(--muted);
        }


        /* =========================================================
           MAIN
        ========================================================= */

        .container {
            width: 94%;

            max-width: 1100px;

            margin: 34px auto 55px;
        }


        .page-heading {
            margin-bottom: 23px;
        }


        .eyebrow {
            margin: 0 0 8px;

            font-size: 10px;

            font-weight: 700;

            letter-spacing: 1.8px;

            text-transform: uppercase;

            color: var(--gold);
        }


        .page-heading h2 {
            margin: 0;

            font-family: Georgia, "Times New Roman", serif;

            font-size: 32px;

            font-weight: 400;

            color: var(--text);

            letter-spacing: -0.5px;
        }


        .page-description {
            margin: 8px 0 0;

            max-width: 650px;

            font-size: 13px;

            line-height: 1.6;

            color: var(--muted);
        }


        /* =========================================================
           FORM CARD
        ========================================================= */

        .form-box {
            background:
                linear-gradient(
                    145deg,
                    #15191d 0%,
                    #111417 100%
                );

            border: 1px solid var(--border);

            border-radius: 16px;

            padding: 30px;

            box-shadow:
                0 18px 45px rgba(0, 0, 0, 0.20);
        }


        .form-header {
            display: flex;
            align-items: center;

            gap: 14px;

            padding-bottom: 24px;

            margin-bottom: 25px;

            border-bottom: 1px solid var(--border);
        }


        .form-icon {
            width: 45px;
            height: 45px;

            flex: 0 0 45px;

            border-radius: 13px;

            background: rgba(201, 166, 107, 0.10);

            color: var(--gold);

            display: grid;
            place-items: center;

            border: 1px solid rgba(201, 166, 107, 0.20);
        }


        .form-icon svg {
            width: 22px;
            height: 22px;

            fill: none;

            stroke: currentColor;

            stroke-width: 1.7;

            stroke-linecap: round;
            stroke-linejoin: round;
        }


        .form-header h3 {
            margin: 0;

            font-size: 15px;

            font-weight: 600;

            color: var(--text);
        }


        .form-header p {
            margin: 4px 0 0;

            font-size: 11px;

            color: var(--muted);
        }


        /* =========================================================
           EMPLOYEE ID
        ========================================================= */

        .employee-id {
            display: inline-flex;
            align-items: center;

            gap: 8px;

            margin-bottom: 23px;

            padding: 7px 11px;

            border-radius: 7px;

            background: rgba(201, 166, 107, 0.07);

            border: 1px solid rgba(201, 166, 107, 0.15);

            color: var(--muted-light);

            font-size: 11px;
        }


        .employee-id strong {
            color: var(--gold);

            font-weight: 600;
        }


        /* =========================================================
           ERROR
        ========================================================= */

        .error {
            display: flex;
            align-items: flex-start;

            gap: 10px;

            background: rgba(212, 125, 133, 0.09);

            color: #E5A0A6;

            border: 1px solid rgba(212, 125, 133, 0.22);

            padding: 13px 14px;

            border-radius: 9px;

            margin-bottom: 22px;

            font-size: 12px;

            line-height: 1.5;
        }


        .error-icon {
            flex: 0 0 auto;

            margin-top: 1px;
        }


        .error-icon svg {
            width: 17px;
            height: 17px;

            fill: none;

            stroke: currentColor;

            stroke-width: 1.8;

            stroke-linecap: round;
            stroke-linejoin: round;
        }


        /* =========================================================
           FORM GRID
        ========================================================= */

        .form-grid {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 20px;
        }


        .form-group {
            display: flex;

            flex-direction: column;
        }


        label {
            margin-bottom: 8px;

            font-size: 11px;

            font-weight: 600;

            color: var(--muted-light);

            letter-spacing: 0.1px;
        }


        .required {
            color: #D47D85;
        }


        input,
        select {
            width: 100%;

            min-height: 44px;

            padding: 11px 13px;

            border: 1px solid #30353a;

            border-radius: 8px;

            outline: none;

            background: #0d1013;

            color: var(--text);

            font-family: inherit;

            font-size: 13px;

            transition:
                border-color 0.18s ease,
                background 0.18s ease,
                box-shadow 0.18s ease;
        }


        input::placeholder {
            color: #656C72;
        }


        input:hover,
        select:hover {
            border-color: #3b4147;
        }


        input:focus,
        select:focus {
            border-color: var(--maroon-hover);

            background: #101316;

            box-shadow:
                0 0 0 3px rgba(100, 28, 40, 0.14);
        }


        select {
            cursor: pointer;
        }


        select option {
            background: #111417;

            color: var(--text);
        }


        input[type="date"] {
            color-scheme: dark;
        }


        /* =========================================================
           BUTTONS
        ========================================================= */

        .buttons {
            display: flex;

            align-items: center;

            gap: 10px;

            margin-top: 28px;

            padding-top: 23px;

            border-top: 1px solid var(--border);
        }


        .button,
        button {
            min-height: 43px;

            padding: 11px 18px;

            border-radius: 8px;

            border: 1px solid transparent;

            font-family: inherit;

            font-size: 12px;

            font-weight: 600;

            text-decoration: none;

            cursor: pointer;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            transition:
                background 0.18s ease,
                border-color 0.18s ease,
                transform 0.18s ease;
        }


        .save {
            background:
                linear-gradient(
                    135deg,
                    #641C28,
                    #7A2635
                );

            color: #fff;

            border-color: rgba(255,255,255,0.05);

            box-shadow:
                0 7px 18px rgba(100, 28, 40, 0.18);
        }


        .save:hover {
            background:
                linear-gradient(
                    135deg,
                    #7A2635,
                    #8A3040
                );

            transform: translateY(-1px);
        }


        .save svg {
            width: 17px;
            height: 17px;

            fill: none;

            stroke: currentColor;

            stroke-width: 1.8;

            stroke-linecap: round;
            stroke-linejoin: round;
        }


        .cancel {
            background: #171b1f;

            color: var(--muted-light);

            border-color: #30353a;
        }


        .cancel:hover {
            background: #1d2227;

            border-color: #41474d;

            color: var(--text);
        }


        .back-link {
            display: inline-flex;

            align-items: center;

            gap: 7px;

            margin-top: 20px;

            color: var(--muted);

            font-size: 11px;

            text-decoration: none;

            transition: color 0.18s ease;
        }


        .back-link:hover {
            color: var(--gold);
        }


        .back-link svg {
            width: 15px;
            height: 15px;

            fill: none;

            stroke: currentColor;

            stroke-width: 1.8;

            stroke-linecap: round;
            stroke-linejoin: round;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 750px) {

            .header {
                padding: 13px 20px;
            }

            .user-section {
                display: none;
            }

            .container {
                width: 92%;

                margin-top: 27px;
            }

            .page-heading h2 {
                font-size: 28px;
            }

            .form-box {
                padding: 23px;
            }

            .form-grid {
                grid-template-columns: 1fr;

                gap: 17px;
            }
        }


        @media (max-width: 500px) {

            .brand-text h1 {
                font-size: 14px;
            }

            .brand-text p {
                font-size: 10px;
            }

            .brand-icon {
                width: 40px;
                height: 40px;
            }

            .page-heading h2 {
                font-size: 25px;
            }

            .form-box {
                padding: 19px;
            }

            .form-header {
                align-items: flex-start;
            }

            .buttons {
                flex-direction: column;

                align-items: stretch;
            }

            .button,
            button {
                width: 100%;
            }
        }

    </style>

</head>


<body>


    <!-- =========================================================
         HEADER
    ========================================================== -->

    <header class="header">

        <div class="brand-section">

            <div class="brand-icon">

                <!-- Users SVG -->
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

                <h1>
                    Payroll &amp; Benefits
                </h1>

                <p>
                    Management System
                </p>

            </div>

        </div>


        <div class="user-section">

            <div>

                <div class="user-name">
                    <?php
                    echo htmlspecialchars(
                        $_SESSION["Full_Name"] ?? "User"
                    );
                    ?>
                </div>

                <div class="user-role">
                    <?php
                    echo htmlspecialchars(
                        $_SESSION["Role"] ?? ""
                    );
                    ?>
                </div>

            </div>


            <div class="user-avatar">

                <!-- User SVG -->
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M4 21a8 8 0 0 1 16 0"/>
                </svg>

            </div>

        </div>

    </header>


    <!-- =========================================================
         MAIN CONTENT
    ========================================================== -->

    <main class="container">


        <section class="page-heading">

            <p class="eyebrow">
                Employee Management
            </p>

            <h2>
                Edit Employee
            </h2>

            <p class="page-description">
                Update employee information, employment status,
                department, and position.
            </p>

        </section>


        <!-- =====================================================
             FORM CARD
        ====================================================== -->

        <section class="form-box">


            <div class="form-header">

                <div class="form-icon">

                    <!-- Edit Employee SVG -->
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path d="M12 20h9"/>
                        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"/>
                        <path d="m15 5 3 3"/>
                    </svg>

                </div>


                <div>

                    <h3>
                        Employee Information
                    </h3>

                    <p>
                        Modify the employee information below.
                    </p>

                </div>

            </div>


            <div class="employee-id">

                <span>
                    Employee ID
                </span>

                <strong>
                    #<?php echo $employee["Emp_ID"]; ?>
                </strong>

            </div>


            <?php if ($error !== ""): ?>

                <div class="error">

                    <div class="error-icon">

                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 8v4"/>
                            <path d="M12 16h.01"/>
                        </svg>

                    </div>

                    <div>
                        <?php
                        echo htmlspecialchars($error);
                        ?>
                    </div>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                action="edit_employee.php?id=<?php echo $employee_id; ?>"
            >


                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars(csrf_token()); ?>"
                >


                <div class="form-grid">


                    <!-- First Name -->

                    <div class="form-group">

                        <label for="first_name">
                            First Name
                            <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            id="first_name"
                            name="first_name"
                            maxlength="100"
                            required
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST["first_name"]
                                    ?? $employee["First_Name"]
                                );
                            ?>"
                        >

                    </div>


                    <!-- Last Name -->

                    <div class="form-group">

                        <label for="last_name">
                            Last Name
                            <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            id="last_name"
                            name="last_name"
                            maxlength="100"
                            required
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST["last_name"]
                                    ?? $employee["Last_Name"]
                                );
                            ?>"
                        >

                    </div>


                    <!-- Email -->

                    <div class="form-group">

                        <label for="email">
                            Email
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="150"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST["email"]
                                    ?? $display_email
                                );
                            ?>"
                        >

                    </div>


                    <!-- Phone -->

                    <div class="form-group">

                        <label for="phone">
                            Phone
                        </label>

                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            maxlength="30"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST["phone"]
                                    ?? $display_phone
                                );
                            ?>"
                        >

                    </div>


                    <!-- Hire Date -->

                    <div class="form-group">

                        <label for="hire_date">
                            Hire Date
                            <span class="required">*</span>
                        </label>

                        <input
                            type="date"
                            id="hire_date"
                            name="hire_date"
                            required
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST["hire_date"]
                                    ?? $employee["Hire_Date"]
                                );
                            ?>"
                        >

                    </div>


                    <!-- Status -->

                    <div class="form-group">

                        <label for="status">
                            Status
                            <span class="required">*</span>
                        </label>


                        <select
                            id="status"
                            name="status"
                            required
                        >

                            <?php

                            $current_status =
                                $_POST["status"]
                                ?? $employee["Status"];

                            ?>

                            <option
                                value="Active"
                                <?php
                                echo $current_status === "Active"
                                    ? "selected"
                                    : "";
                                ?>
                            >
                                Active
                            </option>

                            <option
                                value="Inactive"
                                <?php
                                echo $current_status === "Inactive"
                                    ? "selected"
                                    : "";
                                ?>
                            >
                                Inactive
                            </option>

                        </select>

                    </div>


                    <!-- Department -->

                    <div class="form-group">

                        <label for="department_id">
                            Department
                            <span class="required">*</span>
                        </label>


                        <select
                            id="department_id"
                            name="department_id"
                            required
                        >

                            <option value="">
                                -- Select Department --
                            </option>


                            <?php

                            $selected_department =
                                intval(
                                    $_POST["department_id"]
                                    ?? $employee["Department_ID"]
                                );

                            ?>


                            <?php while (
                                $department =
                                $departments->fetch_assoc()
                            ): ?>

                                <option
                                    value="<?php
                                        echo $department["Department_ID"];
                                    ?>"
                                    <?php
                                    echo
                                        $selected_department ==
                                        $department["Department_ID"]
                                        ? "selected"
                                        : "";
                                    ?>
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $department["Department_Name"]
                                    );
                                    ?>

                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>


                    <!-- Position -->

                    <div class="form-group">

                        <label for="position_id">
                            Position
                            <span class="required">*</span>
                        </label>


                        <select
                            id="position_id"
                            name="position_id"
                            required
                        >

                            <option value="">
                                -- Select Position --
                            </option>


                            <?php

                            $selected_position =
                                intval(
                                    $_POST["position_id"]
                                    ?? $employee["Position_ID"]
                                );

                            ?>


                            <?php while (
                                $position =
                                $positions->fetch_assoc()
                            ): ?>

                                <option
                                    value="<?php
                                        echo $position["Position_ID"];
                                    ?>"
                                    <?php
                                    echo
                                        $selected_position ==
                                        $position["Position_ID"]
                                        ? "selected"
                                        : "";
                                    ?>
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $position["Position_Title"]
                                    );
                                    ?>

                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>


                </div>


                <!-- =================================================
                     BUTTONS
                ================================================== -->

                <div class="buttons">


                    <button
                        type="submit"
                        class="button save"
                    >

                        <!-- Save icon -->
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/>
                            <path d="M17 21v-8H7v8"/>
                            <path d="M7 3v5h8"/>
                        </svg>

                        Save Changes

                    </button>


                    <a
                        href="employee_management.php"
                        class="button cancel"
                    >
                        Cancel
                    </a>


                </div>


            </form>


            <!-- Back Link -->

            <a
                href="employee_management.php"
                class="back-link"
            >

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path d="m15 18-6-6 6-6"/>
                    <path d="M9 12h12"/>
                </svg>

                Back to Employee Management

            </a>


        </section>

    </main>


    <!-- Automatic logout -->

    <script src="auto_logout.js"></script>

</body>

</html>