<?php

session_start();

require_once "db.php";
require_once "csrf.php";
require_once "session_security.php";
require_once "audit_log.php";
require_once "encryption.php";


/* =========================================================
   CHECK LOGIN
========================================================= */

if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}


/* =========================================================
   ONLY ADMINISTRATOR AND HR STAFF
========================================================= */

if (
    $_SESSION["Role"] !== "Administrator" &&
    $_SESSION["Role"] !== "HR Staff"
) {
    http_response_code(403);
    die("Access Denied");
}


$error = "";
$success = "";


/* =========================================================
   GET DEPARTMENTS
========================================================= */

$departments = $conn->query("
    SELECT Department_ID, Department_Name
    FROM department
    ORDER BY Department_Name ASC
");


/* =========================================================
   GET POSITIONS
========================================================= */

$positions = $conn->query("
    SELECT Position_ID, Position_Title, Department_ID
    FROM positions
    ORDER BY Position_Title ASC
");


/* =========================================================
   SAVE EMPLOYEE
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrf_token = $_POST["csrf_token"] ?? "";

    if (!verify_csrf_token($csrf_token)) {

        http_response_code(403);

        die(
            "Invalid security token. Please return to the Add Employee page and try again."
        );
    }


    /* =====================================================
       GET SUBMITTED EMPLOYEE INFORMATION
    ===================================================== */

    $first_name = trim($_POST["first_name"] ?? "");
    $last_name = trim($_POST["last_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");

    $hire_date = $_POST["hire_date"] ?? "";

    $department_id = intval(
        $_POST["department_id"] ?? 0
    );

    $position_id = intval(
        $_POST["position_id"] ?? 0
    );

    $status = $_POST["status"] ?? "Active";


    /* =====================================================
       VALIDATION
    ===================================================== */

    if ($first_name === "" || $last_name === "") {

        $error =
            "First name and last name are required.";

    } elseif (
        !preg_match(
            "/^[a-zA-ZÀ-ÿ .'-]+$/",
            $first_name
        ) ||
        !preg_match(
            "/^[a-zA-ZÀ-ÿ .'-]+$/",
            $last_name
        )
    ) {

        $error =
            "First name and last name can only contain letters, spaces, periods, apostrophes, and hyphens.";

    } elseif (
        $email !== "" &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $error =
            "Please enter a valid email address.";

    } elseif ($department_id <= 0) {

        $error =
            "Please select a department.";

    } elseif ($position_id <= 0) {

        $error =
            "Please select a position.";

    } elseif ($hire_date === "") {

        $error =
            "Please select a hire date.";

    } elseif (strtotime($hire_date) === false) {

        $error =
            "Please enter a valid hire date.";

    } elseif (
        $phone !== "" &&
        !preg_match(
            "/^[0-9+\-\s()]+$/",
            $phone
        )
    ) {

        $error =
            "Please enter a valid phone number.";

    } elseif (
        $status !== "Active" &&
        $status !== "Inactive"
    ) {

        $error =
            "Invalid employee status.";

    } else {


        /* =================================================
           CHECK DEPARTMENT
        ================================================= */

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


        /* =================================================
           CHECK POSITION
        ================================================= */

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


        /* =================================================
           ENCRYPT EMAIL AND PHONE
        ================================================= */

        if ($error === "") {

            $email_encrypted =
                $email === ""
                ? ""
                : encrypt_data($email);

            $phone_encrypted =
                $phone === ""
                ? ""
                : encrypt_data($phone);


            if (
                $email_encrypted === false ||
                $phone_encrypted === false
            ) {

                $error =
                    "Unable to securely encrypt employee contact information.";
            }
        }


        /* =================================================
           INSERT EMPLOYEE
        ================================================= */

        if ($error === "") {

            $stmt = $conn->prepare("
                INSERT INTO employee
                (
                    First_Name,
                    Last_Name,
                    Email,
                    Phone,
                    Hire_Date,
                    Status,
                    Department_ID,
                    Position_ID
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {

                $error =
                    "Unable to prepare employee record.";

            } else {

                $stmt->bind_param(
                    "ssssssii",
                    $first_name,
                    $last_name,
                    $email_encrypted,
                    $phone_encrypted,
                    $hire_date,
                    $status,
                    $department_id,
                    $position_id
                );


                if ($stmt->execute()) {

                    $new_employee_id =
                        $stmt->insert_id;


                    /* =====================================
                       AUDIT LOG
                    ===================================== */

                    write_audit_log(
                        $conn,
                        $_SESSION["User_ID"],
                        "CREATE_EMPLOYEE",
                        "Employee ID " .
                        $new_employee_id .
                        " created: " .
                        $first_name .
                        " " .
                        $last_name
                    );


                    $stmt->close();


                    header(
                        "Location: employee_management.php?added=1"
                    );

                    exit;

                } else {

                    $error =
                        "Failed to add employee: " .
                        $stmt->error;

                    $stmt->close();
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

<title>Add Employee | Payroll &amp; Benefits</title>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
}


/* =========================================================
   THEME VARIABLES
========================================================= */

:root {

    --bg: #090b0d;

    --sidebar: #0d1013;

    --header: #0c0f12;

    --panel: #111417;

    --panel-light: #15191c;

    --panel-hover: #191d20;

    --border: #282d31;

    --border-light: #343a3f;

    --maroon: #641C28;

    --maroon-light: #7A2635;

    --maroon-soft: rgba(100, 28, 40, 0.20);

    --gold: #C9A66B;

    --gold-soft: rgba(201, 166, 107, 0.12);

    --text: #F4EFE8;

    --muted: #9CA3A8;

    --muted-light: #C4C8CA;

    --green: #79B89A;

    --red: #D47D85;
}


/* =========================================================
   BODY
========================================================= */

body {

    margin: 0;

    min-height: 100vh;

    background:
        radial-gradient(
            circle at 85% 5%,
            rgba(100, 28, 40, 0.10),
            transparent 30%
        ),
        var(--bg);

    color: var(--text);

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    font-size: 13px;
}


/* =========================================================
   TOP HEADER
========================================================= */

.top-header {

    height: 76px;

    background:
        rgba(12, 15, 18, 0.96);

    border-bottom:
        1px solid var(--border);

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        0 30px;

    position: relative;

    z-index: 10;
}


/* LEFT */

.header-left {

    display: flex;

    align-items: center;

    gap: 13px;
}


.header-brand-icon {

    width: 42px;

    height: 42px;

    border-radius: 12px;

    display: grid;

    place-items: center;

    color: var(--gold);

    background:
        var(--gold-soft);

    border:
        1px solid
        rgba(201, 166, 107, 0.22);
}


.header-brand-icon svg {

    width: 24px;

    height: 24px;

    fill: none;

    stroke: currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;

    stroke-linejoin: round;
}


.brand-title {

    color: var(--text);

    font-size: 14px;

    font-weight: 600;

    line-height: 1.2;
}


.brand-subtitle {

    margin-top: 3px;

    color: var(--muted);

    font-size: 10px;

    letter-spacing: .5px;
}


/* USER */

.header-user {

    display: flex;

    align-items: center;

    gap: 11px;
}


.header-user-text {

    text-align: right;
}


.header-user-name {

    color: var(--text);

    font-size: 12px;

    font-weight: 600;
}


.header-user-role {

    margin-top: 3px;

    color: var(--muted);

    font-size: 10px;
}


.user-avatar {

    width: 38px;

    height: 38px;

    border-radius: 50%;

    display: grid;

    place-items: center;

    background:
        linear-gradient(
            145deg,
            #C9A66B,
            #8f6d3e
        );

    color: white;
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


/* =========================================================
   MAIN CONTENT
========================================================= */

.main-content {

    width: 100%;

    max-width: 1100px;

    margin: 0 auto;

    padding:
        34px 28px 50px;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    margin-bottom: 22px;
}


.page-eyebrow {

    color: var(--gold);

    font-size: 10px;

    font-weight: 700;

    letter-spacing: 1.7px;

    text-transform: uppercase;

    margin-bottom: 7px;
}


.page-header h1 {

    margin: 0;

    color: var(--text);

    font-size: 26px;

    font-weight: 600;

    letter-spacing: -.4px;
}


.page-description {

    margin:
        7px 0 0;

    max-width: 650px;

    color: var(--muted);

    font-size: 12px;

    line-height: 1.6;
}


/* =========================================================
   FORM CARD
========================================================= */

.form-card {

    background:
        linear-gradient(
            145deg,
            rgba(21, 25, 28, 0.98),
            rgba(15, 18, 21, 0.98)
        );

    border:
        1px solid var(--border);

    border-radius: 14px;

    overflow: hidden;

    box-shadow:
        0 18px 45px
        rgba(0, 0, 0, 0.18);
}


/* =========================================================
   CARD HEADER
========================================================= */

.form-card-header {

    padding:
        22px 25px;

    border-bottom:
        1px solid var(--border);

    display: flex;

    align-items: center;

    gap: 13px;
}


.form-card-icon {

    width: 40px;

    height: 40px;

    border-radius: 11px;

    background:
        rgba(100, 28, 40, 0.18);

    color:
        #C98793;

    border:
        1px solid
        rgba(201, 135, 147, 0.20);

    display: grid;

    place-items: center;

    flex-shrink: 0;
}


.form-card-icon svg {

    width: 21px;

    height: 21px;

    fill: none;

    stroke: currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;

    stroke-linejoin: round;
}


.form-card-title {

    color: var(--text);

    font-size: 14px;

    font-weight: 600;
}


.form-card-subtitle {

    color: var(--muted);

    font-size: 11px;

    margin-top: 3px;
}


/* =========================================================
   FORM BODY
========================================================= */

.form-body {

    padding: 25px;
}


/* =========================================================
   ERROR MESSAGE
========================================================= */

.error {

    display: flex;

    align-items: flex-start;

    gap: 10px;

    padding:
        12px 14px;

    margin-bottom: 22px;

    background:
        rgba(212, 125, 133, 0.08);

    border:
        1px solid
        rgba(212, 125, 133, 0.22);

    border-radius: 9px;

    color:
        #e6aeb6;

    font-size: 12px;

    line-height: 1.5;
}


.error-icon {

    flex-shrink: 0;

    width: 17px;

    height: 17px;

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

    gap:
        20px 18px;
}


/* =========================================================
   FORM GROUP
========================================================= */

.form-group {

    min-width: 0;

    display: flex;

    flex-direction: column;
}


.form-group label {

    color:
        var(--muted-light);

    font-size: 11px;

    font-weight: 600;

    margin-bottom: 7px;
}


.required {

    color:
        #C98793;
}


/* =========================================================
   INPUTS
========================================================= */

.form-group input,
.form-group select {

    width: 100%;

    height: 42px;

    padding:
        0 12px;

    background:
        #0d1013;

    border:
        1px solid var(--border-light);

    border-radius: 8px;

    color:
        var(--text);

    font-family:
        inherit;

    font-size: 12px;

    transition:
        border-color .18s ease,
        box-shadow .18s ease,
        background .18s ease;
}


.form-group input::placeholder {

    color:
        #626a70;
}


.form-group input:hover,
.form-group select:hover {

    border-color:
        #444b50;
}


.form-group input:focus,
.form-group select:focus {

    outline: none;

    border-color:
        var(--maroon-light);

    background:
        #111519;

    box-shadow:
        0 0 0 3px
        rgba(122, 38, 53, 0.13);
}


.form-group select {

    cursor: pointer;
}


.form-group select option {

    background:
        #111417;

    color:
        var(--text);
}


/* =========================================================
   DATE INPUT
========================================================= */

.form-group input[type="date"] {

    color-scheme: dark;
}


/* =========================================================
   BUTTON AREA
========================================================= */

.form-actions {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    margin-top: 26px;

    padding-top: 21px;

    border-top:
        1px solid var(--border);
}


.action-left {

    display: flex;

    gap: 9px;

    align-items: center;
}


.button {

    height: 40px;

    padding:
        0 16px;

    border-radius: 8px;

    border: 1px solid transparent;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    text-decoration: none;

    font-family: inherit;

    font-size: 11px;

    font-weight: 600;

    cursor: pointer;

    transition:
        background .18s ease,
        border-color .18s ease,
        transform .18s ease;
}


.button svg {

    width: 15px;

    height: 15px;

    fill: none;

    stroke: currentColor;

    stroke-width: 1.8;

    stroke-linecap: round;

    stroke-linejoin: round;
}


.button:hover {

    transform:
        translateY(-1px);
}


/* SAVE */

.button-save {

    color: white;

    background:
        linear-gradient(
            135deg,
            #641C28,
            #7A2635
        );

    border-color:
        rgba(255,255,255,.05);

    box-shadow:
        0 7px 18px
        rgba(100, 28, 40, .18);
}


.button-save:hover {

    background:
        linear-gradient(
            135deg,
            #702332,
            #8a2d3d
        );
}


/* CANCEL */

.button-cancel {

    color:
        var(--muted-light);

    background:
        #181c1f;

    border-color:
        var(--border-light);
}


.button-cancel:hover {

    color:
        var(--text);

    background:
        #202529;

    border-color:
        #444a4f;
}


/* =========================================================
   BACK LINK
========================================================= */

.back-link {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    margin-top: 18px;

    color:
        var(--muted);

    text-decoration: none;

    font-size: 11px;

    transition:
        color .18s ease;
}


.back-link:hover {

    color:
        var(--gold);
}


.back-link svg {

    width: 14px;

    height: 14px;

    fill: none;

    stroke: currentColor;

    stroke-width: 1.8;

    stroke-linecap: round;

    stroke-linejoin: round;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 720px) {

    .top-header {

        height: auto;

        min-height: 70px;

        padding:
            14px 18px;
    }


    .main-content {

        padding:
            25px 16px 40px;
    }


    .form-grid {

        grid-template-columns:
            1fr;
    }


    .form-actions {

        align-items:
            stretch;

        flex-direction:
            column;
    }


    .action-left {

        width: 100%;

        display: grid;

        grid-template-columns:
            1fr 1fr;
    }


    .button {

        width: 100%;
    }

}


@media (max-width: 480px) {

    .header-user-text {

        display: none;
    }


    .brand-title {

        font-size:
            13px;
    }


    .page-header h1 {

        font-size:
            23px;
    }


    .form-card-header {

        padding:
            19px;
    }


    .form-body {

        padding:
            19px;
    }


    .action-left {

        grid-template-columns:
            1fr;
    }

}

</style>

</head>


<body>


<!-- =========================================================
     TOP HEADER
========================================================= -->

<header class="top-header">


    <div class="header-left">


        <!-- PEOPLE ICON -->

        <div class="header-brand-icon">

            <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
            >

                <path
                    d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"
                />

                <circle
                    cx="9.5"
                    cy="7"
                    r="4"
                />

                <path
                    d="M17 11a4 4 0 1 0 0-8"
                />

                <path
                    d="M21 21v-2a4 4 0 0 0-3-3.87"
                />

            </svg>

        </div>


        <div>

            <div class="brand-title">
                Payroll &amp; Benefits
            </div>

            <div class="brand-subtitle">
                Management System
            </div>

        </div>


    </div>



    <!-- USER -->

    <div class="header-user">


        <div class="header-user-text">

            <div class="header-user-name">

                <?php

                echo htmlspecialchars(
                    $_SESSION["Full_Name"] ?? ""
                );

                ?>

            </div>


            <div class="header-user-role">

                <?php

                echo htmlspecialchars(
                    $_SESSION["Role"] ?? ""
                );

                ?>

            </div>

        </div>


        <!-- USER AVATAR -->

        <div class="user-avatar">

            <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
            >

                <circle
                    cx="12"
                    cy="8"
                    r="3.5"
                />

                <path
                    d="M5 20c.8-4 3.2-6 7-6s6.2 2 7 6"
                />

            </svg>

        </div>


    </div>

</header>



<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<main class="main-content">


    <!-- PAGE HEADER -->

    <section class="page-header">

        <div class="page-eyebrow">
            Employee Management
        </div>


        <h1>
            Add Employee
        </h1>


        <p class="page-description">
            Create a new employee record and assign their
            department and position.
        </p>

    </section>



    <!-- =====================================================
         FORM CARD
    ====================================================== -->

    <section class="form-card">


        <!-- CARD HEADER -->

        <div class="form-card-header">


            <div class="form-card-icon">

                <!-- EMPLOYEE SVG -->

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >

                    <path
                        d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"
                    />

                    <circle
                        cx="9.5"
                        cy="7"
                        r="4"
                    />

                    <path
                        d="M17 11a4 4 0 1 0 0-8"
                    />

                    <path
                        d="M21 21v-2a4 4 0 0 0-3-3.87"
                    />

                </svg>

            </div>


            <div>

                <div class="form-card-title">
                    Employee Information
                </div>

                <div class="form-card-subtitle">
                    Enter the employee information below.
                </div>

            </div>


        </div>



        <!-- CARD BODY -->

        <div class="form-body">


            <!-- ERROR -->

            <?php if ($error !== ""): ?>

                <div class="error">


                    <div class="error-icon">

                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >

                            <circle
                                cx="12"
                                cy="12"
                                r="9"
                            />

                            <path
                                d="M12 8v5"
                            />

                            <path
                                d="M12 16h.01"
                            />

                        </svg>

                    </div>


                    <div>

                        <?php

                        echo htmlspecialchars($error);

                        ?>

                    </div>


                </div>

            <?php endif; ?>



            <!-- FORM -->

            <form
                method="POST"
                action="add_employee.php"
            >


                <!-- CSRF -->

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php

                        echo htmlspecialchars(
                            csrf_token()
                        );

                    ?>"
                >



                <div class="form-grid">


                    <!-- FIRST NAME -->

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
                            placeholder="Enter first name"
                            value="<?php

                                echo htmlspecialchars(
                                    $_POST["first_name"] ?? ""
                                );

                            ?>"
                        >

                    </div>



                    <!-- LAST NAME -->

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
                            placeholder="Enter last name"
                            value="<?php

                                echo htmlspecialchars(
                                    $_POST["last_name"] ?? ""
                                );

                            ?>"
                        >

                    </div>



                    <!-- EMAIL -->

                    <div class="form-group">

                        <label for="email">
                            Email
                        </label>


                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="150"
                            placeholder="employee@example.com"
                            value="<?php

                                echo htmlspecialchars(
                                    $_POST["email"] ?? ""
                                );

                            ?>"
                        >

                    </div>



                    <!-- PHONE -->

                    <div class="form-group">

                        <label for="phone">
                            Phone
                        </label>


                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            maxlength="30"
                            placeholder="+63 9XX XXX XXXX"
                            value="<?php

                                echo htmlspecialchars(
                                    $_POST["phone"] ?? ""
                                );

                            ?>"
                        >

                    </div>



                    <!-- HIRE DATE -->

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
                                    $_POST["hire_date"] ?? ""
                                );

                            ?>"
                        >

                    </div>



                    <!-- STATUS -->

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

                            <option
                                value="Active"
                                <?php

                                echo (
                                    ($_POST["status"] ?? "Active")
                                    === "Active"
                                )
                                ? "selected"
                                : "";

                                ?>
                            >
                                Active
                            </option>


                            <option
                                value="Inactive"
                                <?php

                                echo (
                                    ($_POST["status"] ?? "")
                                    === "Inactive"
                                )
                                ? "selected"
                                : "";

                                ?>
                            >
                                Inactive
                            </option>

                        </select>

                    </div>



                    <!-- DEPARTMENT -->

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


                            <?php while (
                                $department =
                                $departments->fetch_assoc()
                            ): ?>

                                <option
                                    value="<?php

                                        echo
                                        $department[
                                            "Department_ID"
                                        ];

                                    ?>"
                                    <?php

                                    echo (
                                        isset(
                                            $_POST[
                                                "department_id"
                                            ]
                                        ) &&
                                        $_POST[
                                            "department_id"
                                        ] ==
                                        $department[
                                            "Department_ID"
                                        ]
                                    )
                                    ? "selected"
                                    : "";

                                    ?>
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $department[
                                            "Department_Name"
                                        ]
                                    );

                                    ?>

                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>



                    <!-- POSITION -->

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


                            <?php while (
                                $position =
                                $positions->fetch_assoc()
                            ): ?>

                                <option
                                    value="<?php

                                        echo
                                        $position[
                                            "Position_ID"
                                        ];

                                    ?>"
                                    <?php

                                    echo (
                                        isset(
                                            $_POST[
                                                "position_id"
                                            ]
                                        ) &&
                                        $_POST[
                                            "position_id"
                                        ] ==
                                        $position[
                                            "Position_ID"
                                        ]
                                    )
                                    ? "selected"
                                    : "";

                                    ?>
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $position[
                                            "Position_Title"
                                        ]
                                    );

                                    ?>

                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>


                </div>



                <!-- =================================================
                     ACTIONS
                ================================================== -->

                <div class="form-actions">


                    <div class="action-left">


                        <!-- SAVE -->

                        <button
                            type="submit"
                            class="button button-save"
                        >

                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >

                                <path
                                    d="M5 12h14"
                                />

                                <path
                                    d="M12 5v14"
                                />

                            </svg>

                            Save Employee

                        </button>



                        <!-- CANCEL -->

                        <a
                            href="employee_management.php"
                            class="button button-cancel"
                        >

                            Cancel

                        </a>


                    </div>


                </div>


            </form>


        </div>

    </section>



    <!-- BACK -->

    <a
        href="employee_management.php"
        class="back-link"
    >

        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
        >

            <path
                d="M19 12H5"
            />

            <path
                d="M12 19l-7-7 7-7"
            />

        </svg>

        Back to Employee Management

    </a>


</main>



<!-- AUTOMATIC LOGOUT -->

<script src="auto_logout.js"></script>


</body>

</html>