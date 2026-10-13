<?php
require_once "db.php";
require_once "session_security.php";
require_once "csrf.php";
require_once "audit_log.php";

if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION["Role"] ?? "";

if (!in_array($role, ["Administrator", "HR Staff"], true)) {
    http_response_code(403);
    die("Access denied.");
}

$message = "";
$error = "";

/* =========================
   ADD APPROVER
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        $error = "Invalid security token. Please refresh the page and try again.";
    } else {

        $approver_name = trim($_POST["approver_name"] ?? "");
        $approver_role = trim($_POST["approver_role"] ?? "");
        $department_id = (int)($_POST["department_id"] ?? 0);

        if ($approver_name === "" || $approver_role === "" || $department_id <= 0) {

            $error = "Please complete all required fields.";

        } else {

            $stmt = $conn->prepare("
                INSERT INTO approvers
                (Approver_Name, Role, Department_ID)
                VALUES (?, ?, ?)
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "ssi",
                    $approver_name,
                    $approver_role,
                    $department_id
                );

                if ($stmt->execute()) {

                    $approver_id = $stmt->insert_id;

                    write_audit_log(
                        $conn,
                        $_SESSION["User_ID"],
                        "APPROVER_CREATED",
                        "Created approver: " . $approver_name
                    );

                    $message = "Approver added successfully.";

                } else {

                    $error = "Failed to add approver.";
                }

                $stmt->close();

            } else {

                $error = "Database error.";
            }
        }
    }
}

/* =========================
   GET DEPARTMENTS
========================= */

$departments = [];

$department_result = $conn->query("
    SELECT Department_ID, Department_Name
    FROM department
    ORDER BY Department_Name ASC
");

if ($department_result) {
    while ($row = $department_result->fetch_assoc()) {
        $departments[] = $row;
    }
}

/* =========================
   GET APPROVERS
========================= */

$approvers = [];

$approver_result = $conn->query("
    SELECT
        a.Approver_ID,
        a.Approver_Name,
        a.Role,
        a.Department_ID,
        d.Department_Name
    FROM approvers a
    INNER JOIN department d
        ON a.Department_ID = d.Department_ID
    ORDER BY a.Approver_Name ASC
");

if ($approver_result) {
    while ($row = $approver_result->fetch_assoc()) {
        $approvers[] = $row;
    }
}

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

    <title>Approver Management</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #090b0d;
            color: #f5f5f5;
        }

        .container {
            width: 92%;
            max-width: 1200px;
            margin: 40px auto;
        }

        .header {
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0 0 8px;
            color: #d8a84e;
            font-size: 30px;
        }

        .header p {
            margin: 0;
            color: #b8b8b8;
        }

        .card {
            background: #111418;
            border: 1px solid #2c3035;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .card h2 {
            margin-top: 0;
            color: #d8a84e;
            font-size: 21px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 7px;
            color: #ddd;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            padding: 11px 12px;
            border: 1px solid #3a3f45;
            border-radius: 6px;
            background: #0c0f12;
            color: #fff;
            outline: none;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #d8a84e;
        }

        .button-row {
            margin-top: 20px;
        }

        .btn {
            display: inline-block;
            border: none;
            padding: 11px 18px;
            border-radius: 6px;
            background: #641c28;
            color: #fff;
            cursor: pointer;
            font-weight: 600;
        }

        .btn:hover {
            background: #7a2635;
        }

        .message {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            background: #183d2a;
            color: #a9e6be;
        }

        .error {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            background: #461b22;
            color: #ffb5bd;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #3a0b13;
            color: #d8a84e;
            text-align: left;
            padding: 13px;
            font-size: 13px;
        }

        td {
            padding: 13px;
            border-bottom: 1px solid #292d31;
            color: #ddd;
            font-size: 14px;
        }

        tr:hover td {
            background: #15191d;
        }

        .empty {
            text-align: center;
            padding: 25px;
            color: #999;
        }
        .dashboard-btn {
        display: inline-block;
        margin-bottom: 18px;
        padding: 9px 15px;
        border-radius: 6px;
        background: #641c28;
        color: #ffffff;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: background 0.2s ease;
        }

        .dashboard-btn:hover {
            background: #7a2635;
        }

        @media (max-width: 800px) {

            .form-grid {
                grid-template-columns: 1fr;
            }

            .container {
                width: 95%;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <div class="header">

    <a href="dashboard.php" class="dashboard-btn">
        ← Dashboard
    </a>

    <h1>Approver Management</h1>

    <p>
        Manage employees who are authorized to approve claims and reimbursement requests.
    </p>

    </div>

    <?php if ($message !== ""): ?>

        <div class="message">
            <?php echo e($message); ?>
        </div>

    <?php endif; ?>

    <?php if ($error !== ""): ?>

        <div class="error">
            <?php echo e($error); ?>
        </div>

    <?php endif; ?>


    <!-- ADD APPROVER -->

    <div class="card">

        <h2>Add Approver</h2>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e(csrf_token()); ?>"
            >

            <div class="form-grid">

                <div class="form-group">

                    <label for="approver_name">
                        Approver Name
                    </label>

                    <input
                        type="text"
                        id="approver_name"
                        name="approver_name"
                        placeholder="Enter approver name"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="approver_role">
                        Role
                    </label>

                    <select
                        id="approver_role"
                        name="approver_role"
                        required
                    >

                        <option value="">
                            Select Role
                        </option>

                        <option value="Administrator">
                            Administrator
                        </option>

                        <option value="HR Staff">
                            HR Staff
                        </option>

                        <option value="Payroll Staff">
                            Payroll Staff
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label for="department_id">
                        Department
                    </label>

                    <select
                        id="department_id"
                        name="department_id"
                        required
                    >

                        <option value="">
                            Select Department
                        </option>

                        <?php foreach ($departments as $department): ?>

                            <option
                                value="<?php echo e($department["Department_ID"]); ?>"
                            >
                                <?php echo e($department["Department_Name"]); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>


            <div class="button-row">

                <button
                    type="submit"
                    class="btn"
                >
                    Add Approver
                </button>

            </div>

        </form>

    </div>


    <!-- APPROVER LIST -->

    <div class="card">

        <h2>Approver List</h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>Approver ID</th>
                        <th>Approver Name</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (count($approvers) > 0): ?>

                        <?php foreach ($approvers as $approver): ?>

                            <tr>

                                <td>
                                    <?php echo e($approver["Approver_ID"]); ?>
                                </td>

                                <td>
                                    <?php echo e($approver["Approver_Name"]); ?>
                                </td>

                                <td>
                                    <?php echo e($approver["Role"]); ?>
                                </td>

                                <td>
                                    <?php echo e($approver["Department_Name"]); ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="4"
                                class="empty"
                            >
                                No approvers found.
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