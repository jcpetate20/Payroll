<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";
require_once "csrf.php";
require_once "session_security.php";
require_once "audit_log.php";

// Check login
if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}

// Only Administrator and HR Staff
if (
    $_SESSION["Role"] !== "Administrator" &&
    $_SESSION["Role"] !== "HR Staff"
) {
    die("Access denied. Administrator or HR Staff access required.");
}

$error = "";
$success = "";

// ======================================================
// ADD BENEFIT
// ======================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

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

    // Validate employee
    if ($emp_id <= 0) {
        $error = "Please select an employee.";
    }

    // Validate benefit name
    elseif ($benefit_name === "") {
        $error = "Benefit name is required.";
    }

    elseif (strlen($benefit_name) > 100) {
        $error = "Benefit name must not exceed 100 characters.";
    }

    // Validate benefit type
    elseif ($benefit_type !== "" && strlen($benefit_type) > 100) {
        $error = "Benefit type must not exceed 100 characters.";
    }

    // Validate description
    elseif ($description !== "" && strlen($description) > 255) {
        $error = "Description must not exceed 255 characters.";
    }

    // Validate amount
    elseif ($amount === "" || !is_numeric($amount)) {
        $error = "Please enter a valid benefit amount.";
    }

    elseif ((float)$amount < 0) {
        $error = "Benefit amount cannot be negative.";
    }

    // Validate status
    elseif (
        $status !== "Active" &&
        $status !== "Inactive"
    ) {
        $error = "Invalid benefit status.";
    }

    // Validate date
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

    // ==================================================
    // CHECK EMPLOYEE
    // ==================================================

    if ($error === "") {

        $stmt = $conn->prepare("
            SELECT Emp_ID
            FROM employee
            WHERE Emp_ID = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error = "Database error.";

        } else {

            $stmt->bind_param("i", $emp_id);
            $stmt->execute();

            $employee_result = $stmt->get_result();

            if (!$employee_result->fetch_assoc()) {
                $error = "Selected employee does not exist.";
            }

            $stmt->close();
        }
    }

    // ==================================================
    // INSERT BENEFIT
    // ==================================================

    if ($error === "") {

        $amount_value = (float)$amount;

        $stmt = $conn->prepare("
            INSERT INTO benefits
            (
                Emp_ID,
                Benefit_Name,
                Benefit_Type,
                Description,
                Amount,
                Status,
                Effective_Date
            )
            VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''))
        ");

        if (!$stmt) {

            $error = "Failed to prepare benefit record.";

        } else {

            $stmt->bind_param(
                "isssdss",
                $emp_id,
                $benefit_name,
                $benefit_type,
                $description,
                $amount_value,
                $status,
                $effective_date
            );

            if ($stmt->execute()) {

                $benefit_id = $stmt->insert_id;

                write_audit_log(
                    $conn,
                    $_SESSION["User_ID"],
                    "CREATE_BENEFIT",
                    "Created benefit #{$benefit_id} ({$benefit_name}) for employee #{$emp_id}."
                );

                $success = "Benefit added successfully.";

            } else {

                $error = "Failed to add benefit.";
            }

            $stmt->close();
        }
    }
}

// ======================================================
// GET EMPLOYEES
// ======================================================

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

// ======================================================
// GET BENEFITS
// ======================================================

$benefits = $conn->query("
    SELECT
        b.Benefit_ID,
        b.Emp_ID,
        b.Benefit_Name,
        b.Benefit_Type,
        b.Description,
        b.Amount,
        b.Status,
        b.Effective_Date,
        b.Created_At,
        e.First_Name,
        e.Last_Name
    FROM benefits b
    INNER JOIN employee e
        ON b.Emp_ID = e.Emp_ID
    ORDER BY b.Created_At DESC, b.Benefit_ID DESC
");

if (!$benefits) {
    die("Failed to load benefits.");
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

    <title>Benefits Management</title>

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
           CARDS
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

            grid-template-columns: repeat(2, minmax(0, 1fr));

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

            box-shadow: 0 0 0 3px rgba(201, 166, 107, 0.08);
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

        .save-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            gap: 9px;

            margin-top: 21px;

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

        /* =========================
           TABLE
        ========================= */

        .table-container {
            overflow-x: auto;

            border: 1px solid var(--border);

            border-radius: 10px;
        }

        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 1100px;
        }

        th {
            background: var(--panel-light);

            color: var(--muted-light);

            padding: 14px;

            text-align: left;

            white-space: nowrap;

            font-size: 12px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .45px;

            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 14px;

            border-bottom: 1px solid var(--border);

            vertical-align: top;

            color: var(--text);

            font-size: 13px;

            line-height: 1.5;
        }

        tbody tr {
            transition: .15s ease;
        }

        tbody tr:hover {
            background: rgba(100, 28, 40, 0.08);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .benefit-id {
            color: var(--gold);

            font-weight: 700;
        }

        .employee-name {
            font-weight: 600;

            color: var(--text);
        }

        .employee-id {
            display: inline-block;

            margin-top: 3px;

            color: var(--muted);

            font-size: 11px;
        }

        .benefit-name {
            color: var(--text);

            font-weight: 600;
        }

        .secondary-text {
            color: var(--muted);

            font-size: 12px;
        }

        .amount {
            text-align: right;

            white-space: nowrap;

            color: var(--gold);

            font-weight: 700;
        }

        .status-badge {
            display: inline-flex;

            align-items: center;

            padding: 5px 9px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 700;
        }

        .status-badge.active {
            background: rgba(121, 184, 154, 0.10);

            color: var(--green);

            border: 1px solid rgba(121, 184, 154, 0.22);
        }

        .status-badge.inactive {
            background: rgba(212, 125, 133, 0.10);

            color: var(--red);

            border: 1px solid rgba(212, 125, 133, 0.22);
        }

        .empty {
            text-align: center;

            padding: 45px 30px;

            color: var(--muted);

            font-size: 14px;
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

                <h1>Benefits Management</h1>

                <p class="header-subtitle">
                    Manage employee benefits and benefit records.
                </p>

            </div>

        </div>

        <a href="dashboard.php" class="back-button">

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


    <!-- ADD BENEFIT -->

    <div class="card">

        <div class="card-header">

            <div>

                <h2>Add Employee Benefit</h2>

                <p class="card-description">
                    Create a custom benefit record for an active employee.
                </p>

            </div>

        </div>

        <div class="section-line"></div>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars(csrf_token()); ?>"
            >

            <div class="form-grid">

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

                        <?php while ($employee = $employees->fetch_assoc()): ?>

                            <option
                                value="<?php echo (int)$employee["Emp_ID"]; ?>"
                            >

                                <?php

                                echo htmlspecialchars(
                                    $employee["Last_Name"] . ", " .
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
                        placeholder="Example: Health Allowance"
                    >

                </div>


                <div class="form-group">

                    <label for="benefit_type">
                        Benefit Type
                    </label>

                    <input
                        type="text"
                        name="benefit_type"
                        id="benefit_type"
                        maxlength="100"
                        placeholder="Example: Company Benefit"
                    >

                </div>


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
                        placeholder="0.00"
                    >

                </div>


                <div class="form-group">

                    <label for="effective_date">
                        Effective Date
                    </label>

                    <input
                        type="date"
                        name="effective_date"
                        id="effective_date"
                    >

                </div>


                <div class="form-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        name="status"
                        id="status"
                    >

                        <option value="Active">
                            Active
                        </option>

                        <option value="Inactive">
                            Inactive
                        </option>

                    </select>

                </div>


                <div class="form-group full">

                    <label for="description">
                        Description
                    </label>

                    <textarea
                        name="description"
                        id="description"
                        maxlength="255"
                        placeholder="Optional benefit description"
                    ></textarea>

                </div>

            </div>


            <button
                type="submit"
                class="save-button"
            >

                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path d="M12 5v14"/>
                    <path d="M5 12h14"/>
                </svg>

                Add Benefit

            </button>

        </form>

    </div>


    <!-- BENEFIT RECORDS -->

    <div class="card">

        <div class="card-header">

            <div>

                <h2>Employee Benefits</h2>

                <p class="card-description">
                    View all benefit records currently stored in the system.
                </p>

            </div>

        </div>

        <div class="section-line"></div>

        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>Benefit ID</th>

                        <th>Employee</th>

                        <th>Benefit Name</th>

                        <th>Type</th>

                        <th>Description</th>

                        <th>Amount</th>

                        <th>Status</th>

                        <th>Effective Date</th>

                        <th>Created At</th>

                    </tr>

                </thead>

                <tbody>

                <?php if ($benefits->num_rows > 0): ?>

                    <?php while ($benefit = $benefits->fetch_assoc()): ?>

                        <tr>

                            <td class="benefit-id">

                                <?php

                                echo (int)$benefit["Benefit_ID"];

                                ?>

                            </td>


                            <td>

                                <span class="employee-name">

                                    <?php

                                    echo htmlspecialchars(
                                        $benefit["First_Name"] . " " .
                                        $benefit["Last_Name"]
                                    );

                                    ?>

                                </span>

                                <br>

                                <span class="employee-id">

                                    ID:

                                    <?php

                                    echo (int)$benefit["Emp_ID"];

                                    ?>

                                </span>

                            </td>


                            <td class="benefit-name">

                                <?php

                                echo htmlspecialchars(
                                    $benefit["Benefit_Name"]
                                );

                                ?>

                            </td>


                            <td>

                                <span class="secondary-text">

                                    <?php

                                    echo htmlspecialchars(
                                        $benefit["Benefit_Type"] ?? ""
                                    );

                                    ?>

                                </span>

                            </td>


                            <td>

                                <span class="secondary-text">

                                    <?php

                                    echo htmlspecialchars(
                                        $benefit["Description"] ?? ""
                                    );

                                    ?>

                                </span>

                            </td>


                            <td class="amount">

                                ₱<?php

                                echo number_format(
                                    (float)$benefit["Amount"],
                                    2
                                );

                                ?>

                            </td>


                            <td>

                                <?php if ($benefit["Status"] === "Active"): ?>

                                    <span class="status-badge active">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="status-badge inactive">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <span class="secondary-text">

                                    <?php

                                    echo htmlspecialchars(
                                        $benefit["Effective_Date"] ?? ""
                                    );

                                    ?>

                                </span>

                            </td>


                            <td>

                                <span class="secondary-text">

                                    <?php

                                    echo htmlspecialchars(
                                        $benefit["Created_At"]
                                    );

                                    ?>

                                </span>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="9"
                            class="empty"
                        >

                            No employee benefits have been added yet.

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