<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";
require_once "csrf.php";
require_once "session_security.php";
require_once "audit_log.php";

/* ======================================================
   CHECK LOGIN
====================================================== */

if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}

/* ======================================================
   ONLY ADMINISTRATOR AND HR STAFF
====================================================== */

if (
    $_SESSION["Role"] !== "Administrator" &&
    $_SESSION["Role"] !== "HR Staff"
) {
    die("Access denied. Administrator or HR Staff access required.");
}

/* ======================================================
   GET BENEFIT ID
====================================================== */

$benefit_id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($benefit_id <= 0) {
    die("Invalid benefit ID.");
}

$error = "";
$success = "";

/* ======================================================
   GET EXISTING BENEFIT
====================================================== */

$stmt = $conn->prepare("
    SELECT
        Benefit_ID,
        Emp_ID,
        Benefit_Name,
        Benefit_Type,
        Description,
        Amount,
        Status,
        Effective_Date
    FROM benefits
    WHERE Benefit_ID = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error.");
}

$stmt->bind_param("i", $benefit_id);
$stmt->execute();

$result = $stmt->get_result();
$benefit = $result->fetch_assoc();

$stmt->close();

if (!$benefit) {
    die("Benefit record not found.");
}

/* ======================================================
   UPDATE BENEFIT
====================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* CSRF CHECK */

    if (
        !isset($_POST["csrf_token"]) ||
        !verify_csrf_token($_POST["csrf_token"])
    ) {
        die("Invalid CSRF token.");
    }

    $emp_id = isset($_POST["emp_id"])
        ? (int) $_POST["emp_id"]
        : 0;

    $benefit_name = trim($_POST["benefit_name"] ?? "");
    $benefit_type = trim($_POST["benefit_type"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $amount = trim($_POST["amount"] ?? "");
    $status = $_POST["status"] ?? "Active";
    $effective_date = $_POST["effective_date"] ?? "";

    /* ==================================================
       VALIDATE EMPLOYEE
    ================================================== */

    if ($emp_id <= 0) {

        $error = "Please select an employee.";

    }

    /* ==================================================
       VALIDATE BENEFIT NAME
    ================================================== */

    elseif ($benefit_name === "") {

        $error = "Benefit name is required.";

    }

    elseif (strlen($benefit_name) > 100) {

        $error = "Benefit name must not exceed 100 characters.";

    }

    /* ==================================================
       VALIDATE BENEFIT TYPE
    ================================================== */

    elseif (
        $benefit_type !== "" &&
        strlen($benefit_type) > 100
    ) {

        $error = "Benefit type must not exceed 100 characters.";

    }

    /* ==================================================
       VALIDATE DESCRIPTION
    ================================================== */

    elseif (
        $description !== "" &&
        strlen($description) > 255
    ) {

        $error = "Description must not exceed 255 characters.";

    }

    /* ==================================================
       VALIDATE AMOUNT
    ================================================== */

    elseif (
        $amount === "" ||
        !is_numeric($amount)
    ) {

        $error = "Please enter a valid benefit amount.";

    }

    elseif ((float)$amount < 0) {

        $error = "Benefit amount cannot be negative.";

    }

    /* ==================================================
       VALIDATE STATUS
    ================================================== */

    elseif (
        $status !== "Active" &&
        $status !== "Inactive"
    ) {

        $error = "Invalid benefit status.";

    }

    /* ==================================================
       VALIDATE DATE
    ================================================== */

    elseif ($effective_date !== "") {

        $date_object = DateTime::createFromFormat(
            "Y-m-d",
            $effective_date
        );

        if (
            !$date_object ||
            $date_object->format("Y-m-d") !== $effective_date
        ) {

            $error = "Invalid effective date.";

        }
    }

    /* ==================================================
       CHECK EMPLOYEE
    ================================================== */

    if ($error === "") {

        $stmt = $conn->prepare("
            SELECT Emp_ID
            FROM employee
            WHERE Emp_ID = ?
              AND Status = 'Active'
            LIMIT 1
        ");

        if (!$stmt) {

            $error = "Database error.";

        } else {

            $stmt->bind_param("i", $emp_id);
            $stmt->execute();

            $employee_result = $stmt->get_result();

            if (!$employee_result->fetch_assoc()) {

                $error = "Selected employee does not exist or is not active.";

            }

            $stmt->close();
        }
    }

    /* ==================================================
       UPDATE BENEFIT
    ================================================== */

    if ($error === "") {

        $amount_value = (float)$amount;

        $stmt = $conn->prepare("
            UPDATE benefits
            SET
                Emp_ID = ?,
                Benefit_Name = ?,
                Benefit_Type = ?,
                Description = ?,
                Amount = ?,
                Status = ?,
                Effective_Date = NULLIF(?, '')
            WHERE Benefit_ID = ?
        ");

        if (!$stmt) {

            $error = "Failed to prepare benefit update.";

        } else {

            $stmt->bind_param(
                "isssdssi",
                $emp_id,
                $benefit_name,
                $benefit_type,
                $description,
                $amount_value,
                $status,
                $effective_date,
                $benefit_id
            );

            if ($stmt->execute()) {

                write_audit_log(
                    $conn,
                    $_SESSION["User_ID"],
                    "UPDATE_BENEFIT",
                    "Updated benefit #{$benefit_id} ({$benefit_name}) for employee #{$emp_id}."
                );

                $success = "Benefit updated successfully.";

                /* Refresh displayed values */

                $benefit["Emp_ID"] = $emp_id;
                $benefit["Benefit_Name"] = $benefit_name;
                $benefit["Benefit_Type"] = $benefit_type;
                $benefit["Description"] = $description;
                $benefit["Amount"] = $amount_value;
                $benefit["Status"] = $status;
                $benefit["Effective_Date"] = $effective_date;

            } else {

                $error = "Failed to update benefit.";

            }

            $stmt->close();
        }
    }
}

/* ======================================================
   GET ACTIVE EMPLOYEES
====================================================== */

$employees = $conn->query("
    SELECT
        Emp_ID,
        First_Name,
        Last_Name
    FROM employee
    WHERE Status = 'Active'
    ORDER BY Last_Name ASC, First_Name ASC
");

if (!$employees) {
    die("Failed to load employees.");
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

    <title>Edit Benefit</title>

    <style>

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

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
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

            margin: 35px auto 50px;

        }

        /* =========================
           HEADER
        ========================= */

        .header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

            flex-wrap: wrap;

            margin-bottom: 25px;

            padding-bottom: 20px;

            border-bottom: 1px solid var(--border);

        }

        .title-section {

            display: flex;

            align-items: center;

            gap: 15px;

        }

        .benefit-icon {

            width: 48px;

            height: 48px;

            border-radius: 14px;

            background: rgba(201, 166, 107, 0.12);

            color: var(--gold);

            display: grid;

            place-items: center;

            border: 1px solid rgba(201, 166, 107, 0.22);

        }

        .benefit-icon svg {

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

            letter-spacing: -0.3px;

        }

        .header-subtitle {

            margin: 5px 0 0;

            color: var(--muted);

            font-size: 14px;

        }

        .back-button {

            display: inline-flex;

            align-items: center;

            gap: 9px;

            text-decoration: none;

            background: var(--maroon);

            color: white;

            padding: 11px 17px;

            border-radius: 9px;

            font-size: 14px;

            font-weight: 600;

            border: 1px solid rgba(255,255,255,.06);

            transition: .2s ease;

        }

        .back-button:hover {

            background: var(--maroon-hover);

            transform: translateY(-1px);

        }

        .back-button svg {

            width: 17px;

            height: 17px;

            fill: none;

            stroke: currentColor;

            stroke-width: 1.8;

            stroke-linecap: round;

            stroke-linejoin: round;

        }

        /* =========================
           MESSAGES
        ========================= */

        .message {

            padding: 14px 17px;

            border-radius: 10px;

            margin-bottom: 20px;

            font-size: 14px;

            border: 1px solid transparent;

        }

        .error {

            background: rgba(212, 125, 133, 0.10);

            color: var(--red);

            border-color: rgba(212, 125, 133, 0.25);

        }

        .success {

            background: rgba(121, 184, 154, 0.10);

            color: var(--green);

            border-color: rgba(121, 184, 154, 0.25);

        }

        /* =========================
           CARD
        ========================= */

        .card {

            background: var(--panel);

            border: 1px solid var(--border);

            border-radius: 14px;

            padding: 26px;

            margin-bottom: 25px;

        }

        .card-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            margin-bottom: 22px;

        }

        .card h2 {

            margin: 0;

            color: var(--text);

            font-size: 19px;

            font-weight: 650;

        }

        .card-description {

            margin: 5px 0 0;

            color: var(--muted);

            font-size: 13px;

        }

        .section-line {

            width: 100%;

            height: 1px;

            background: var(--border);

            margin-bottom: 24px;

        }

        /* =========================
           FORM
        ========================= */

        .form-grid {

            display: grid;

            grid-template-columns: repeat(
                2,
                minmax(0, 1fr)
            );

            gap: 18px;

        }

        .form-group {

            display: flex;

            flex-direction: column;

        }

        .form-group.full {

            grid-column: 1 / -1;

        }

        label {

            color: var(--muted-light);

            font-size: 13px;

            font-weight: 600;

            margin-bottom: 8px;

        }

        input,
        select,
        textarea {

            width: 100%;

            padding: 12px 13px;

            border: 1px solid var(--border);

            border-radius: 9px;

            font-size: 14px;

            background: var(--panel-light);

            color: var(--text);

            transition: .2s ease;

        }

        input::placeholder,
        textarea::placeholder {

            color: #697177;

        }

        input:hover,
        select:hover,
        textarea:hover {

            border-color: #373d42;

        }

        input:focus,
        select:focus,
        textarea:focus {

            outline: none;

            border-color: var(--gold);

            box-shadow:
                0 0 0 3px
                rgba(201, 166, 107, 0.08);

        }

        select {

            cursor: pointer;

        }

        select option {

            background: #111417;

            color: var(--text);

        }

        textarea {

            min-height: 95px;

            resize: vertical;

            line-height: 1.5;

        }

        .button-row {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

            margin-top: 21px;

        }

        .save-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 9px;

            background: var(--maroon);

            color: white;

            border: 1px solid rgba(255,255,255,.06);

            padding: 12px 20px;

            border-radius: 9px;

            font-size: 14px;

            font-weight: 600;

            cursor: pointer;

            transition: .2s ease;

        }

        .save-button:hover {

            background: var(--maroon-hover);

            transform: translateY(-1px);

        }

        .save-button svg {

            width: 17px;

            height: 17px;

            fill: none;

            stroke: currentColor;

            stroke-width: 1.8;

            stroke-linecap: round;

            stroke-linejoin: round;

        }

        .cancel-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 9px;

            background: var(--panel-light);

            color: var(--muted-light);

            border: 1px solid var(--border);

            padding: 12px 20px;

            border-radius: 9px;

            font-size: 14px;

            font-weight: 600;

            text-decoration: none;

            transition: .2s ease;

        }

        .cancel-button:hover {

            border-color: #373d42;

            color: var(--text);

        }

        .cancel-button svg {

            width: 17px;

            height: 17px;

            fill: none;

            stroke: currentColor;

            stroke-width: 1.8;

            stroke-linecap: round;

            stroke-linejoin: round;

        }

        /* =========================
           BENEFIT INFORMATION
        ========================= */

        .info-grid {

            display: grid;

            grid-template-columns: repeat(
                3,
                minmax(0, 1fr)
            );

            gap: 12px;

            margin-bottom: 22px;

        }

        .info-item {

            background: var(--panel-light);

            border: 1px solid var(--border);

            border-radius: 9px;

            padding: 13px 14px;

        }

        .info-label {

            display: block;

            color: var(--muted);

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: .4px;

            margin-bottom: 5px;

        }

        .info-value {

            color: var(--gold);

            font-size: 14px;

            font-weight: 700;

        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 800px) {

            .container {

                width: 92%;

                margin-top: 25px;

            }

            .form-grid {

                grid-template-columns: 1fr;

            }

            .form-group.full {

                grid-column: auto;

            }

            .info-grid {

                grid-template-columns: 1fr;

            }

            .header {

                align-items: flex-start;

            }

            .back-button {

                width: 100%;

                justify-content: center;

            }

            .card {

                padding: 20px;

            }

        }

        @media (max-width: 500px) {

            h1 {

                font-size: 23px;

            }

            .benefit-icon {

                width: 43px;

                height: 43px;

            }

            .benefit-icon svg {

                width: 23px;

                height: 23px;

            }

            .title-section {

                gap: 11px;

            }

            .header-subtitle {

                font-size: 12px;

            }

            .save-button,
            .cancel-button {

                width: 100%;

            }

        }

    </style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <div class="title-section">

            <div class="benefit-icon">

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >

                    <path d="M20 12v8a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-8"/>

                    <path d="M2 7h20v5H2z"/>

                    <path d="M12 7v14"/>

                    <path d="M12 7H7.5a2.5 2.5 0 1 1 0-5C10 2 12 7 12 7Z"/>

                    <path d="M12 7h4.5a2.5 2.5 0 1 0 0-5C14 2 12 7 12 7Z"/>

                </svg>

            </div>

            <div>

                <h1>Edit Benefit</h1>

                <p class="header-subtitle">
                    Update employee benefit information.
                </p>

            </div>

        </div>

        <a
            href="benefits_management.php"
            class="back-button"
        >

            <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
            >

                <path d="M19 12H5"/>

                <path d="M12 19l-7-7 7-7"/>

            </svg>

            Back to Benefits

        </a>

    </div>


    <!-- MESSAGES -->

    <?php if ($error !== ""): ?>

        <div class="message error">

            <?php echo htmlspecialchars($error); ?>

        </div>

    <?php endif; ?>


    <?php if ($success !== ""): ?>

        <div class="message success">

            <?php echo htmlspecialchars($success); ?>

        </div>

    <?php endif; ?>


    <!-- BENEFIT INFORMATION -->

    <div class="card">

        <div class="card-header">

            <div>

                <h2>
                    Benefit Information
                </h2>

                <p class="card-description">
                    Review and update the selected employee benefit.
                </p>

            </div>

        </div>

        <div class="section-line"></div>


        <div class="info-grid">

            <div class="info-item">

                <span class="info-label">
                    Benefit ID
                </span>

                <span class="info-value">
                    #<?php echo (int)$benefit["Benefit_ID"]; ?>
                </span>

            </div>

            <div class="info-item">

                <span class="info-label">
                    Current Employee
                </span>

                <span class="info-value">

                    <?php

                    $employee_name = "";

                    $employee_lookup = $conn->prepare("
                        SELECT First_Name, Last_Name
                        FROM employee
                        WHERE Emp_ID = ?
                        LIMIT 1
                    ");

                    if ($employee_lookup) {

                        $employee_lookup->bind_param(
                            "i",
                            $benefit["Emp_ID"]
                        );

                        $employee_lookup->execute();

                        $employee_result =
                            $employee_lookup->get_result();

                        $employee_data =
                            $employee_result->fetch_assoc();

                        if ($employee_data) {

                            $employee_name =
                                $employee_data["First_Name"] .
                                " " .
                                $employee_data["Last_Name"];

                        }

                        $employee_lookup->close();

                    }

                    echo htmlspecialchars(
                        $employee_name !== ""
                            ? $employee_name
                            : "Unknown Employee"
                    );

                    ?>

                </span>

            </div>

            <div class="info-item">

                <span class="info-label">
                    Current Amount
                </span>

                <span class="info-value">

                    ₱<?php echo number_format(
                        (float)$benefit["Amount"],
                        2
                    ); ?>

                </span>

            </div>

        </div>


        <!-- EDIT FORM -->

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars(
                    csrf_token()
                ); ?>"
            >

            <div class="form-grid">


                <!-- EMPLOYEE -->

                <div class="form-group">

                    <label for="emp_id">
                        Employee
                    </label>

                    <select
                        name="emp_id"
                        id="emp_id"
                        required
                    >

                        <option value="">
                            -- Select Employee --
                        </option>

                        <?php while (
                            $employee =
                            $employees->fetch_assoc()
                        ): ?>

                            <option
                                value="<?php echo (int)$employee["Emp_ID"]; ?>"
                                <?php
                                echo (
                                    (int)$benefit["Emp_ID"] ===
                                    (int)$employee["Emp_ID"]
                                )
                                    ? "selected"
                                    : "";
                                ?>
                            >

                                <?php

                                echo htmlspecialchars(

                                    $employee["Last_Name"] .
                                    ", " .
                                    $employee["First_Name"] .
                                    " (ID: " .
                                    $employee["Emp_ID"] .
                                    ")"

                                );

                                ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <!-- BENEFIT NAME -->

                <div class="form-group">

                    <label for="benefit_name">
                        Benefit Name
                    </label>

                    <input
                        type="text"
                        name="benefit_name"
                        id="benefit_name"
                        maxlength="100"
                        required
                        value="<?php echo htmlspecialchars(
                            $benefit["Benefit_Name"]
                        ); ?>"
                        placeholder="Example: Health Allowance"
                    >

                </div>


                <!-- BENEFIT TYPE -->

                <div class="form-group">

                    <label for="benefit_type">
                        Benefit Type
                    </label>

                    <input
                        type="text"
                        name="benefit_type"
                        id="benefit_type"
                        maxlength="100"
                        value="<?php echo htmlspecialchars(
                            $benefit["Benefit_Type"] ?? ""
                        ); ?>"
                        placeholder="Example: Company Benefit"
                    >

                </div>


                <!-- AMOUNT -->

                <div class="form-group">

                    <label for="amount">
                        Amount
                    </label>

                    <input
                        type="number"
                        name="amount"
                        id="amount"
                        min="0"
                        step="0.01"
                        required
                        value="<?php echo htmlspecialchars(
                            $benefit["Amount"]
                        ); ?>"
                        placeholder="0.00"
                    >

                </div>


                <!-- EFFECTIVE DATE -->

                <div class="form-group">

                    <label for="effective_date">
                        Effective Date
                    </label>

                    <input
                        type="date"
                        name="effective_date"
                        id="effective_date"
                        value="<?php echo htmlspecialchars(
                            $benefit["Effective_Date"] ?? ""
                        ); ?>"
                    >

                </div>


                <!-- STATUS -->

                <div class="form-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        name="status"
                        id="status"
                    >

                        <option
                            value="Active"
                            <?php echo (
                                $benefit["Status"] === "Active"
                            )
                                ? "selected"
                                : ""; ?>
                        >
                            Active
                        </option>

                        <option
                            value="Inactive"
                            <?php echo (
                                $benefit["Status"] === "Inactive"
                            )
                                ? "selected"
                                : ""; ?>
                        >
                            Inactive
                        </option>

                    </select>

                </div>


                <!-- DESCRIPTION -->

                <div class="form-group full">

                    <label for="description">
                        Description
                    </label>

                    <textarea
                        name="description"
                        id="description"
                        maxlength="255"
                        placeholder="Optional benefit description"
                    ><?php echo htmlspecialchars(
                        $benefit["Description"] ?? ""
                    ); ?></textarea>

                </div>

            </div>


            <!-- BUTTONS -->

            <div class="button-row">

                <button
                    type="submit"
                    class="save-button"
                >

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >

                        <path d="M5 12l4 4L19 6"/>

                    </svg>

                    Save Changes

                </button>


                <a
                    href="benefits_management.php"
                    class="cancel-button"
                >

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >

                        <path d="M6 6l12 12"/>

                        <path d="M18 6L6 18"/>

                    </svg>

                    Cancel

                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>