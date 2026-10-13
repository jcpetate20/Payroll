<?php

require_once "db.php";
require_once "session_security.php";
require_once "csrf.php";
require_once "audit_log.php";

if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}

$user_id   = $_SESSION["User_ID"];
$username  = $_SESSION["Username"];
$full_name = $_SESSION["Full_Name"];
$role      = $_SESSION["Role"];
$emp_id    = $_SESSION["Emp_ID"];

$allowed_roles = [
    "Administrator",
    "HR Staff",
    "Payroll Staff",
    "Employee"
];

if (!in_array($role, $allowed_roles, true)) {
    http_response_code(403);
    die("Access denied.");
}

$message = "";
$error = "";

/* =========================
   CREATE CLAIM
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        $error = "Invalid security token. Please refresh the page and try again.";
    } else {

        $claim_type = trim($_POST["claim_type"] ?? "");
        $claim_date = $_POST["claim_date"] ?? "";

        $descriptions = $_POST["description"] ?? [];
        $amounts      = $_POST["amount"] ?? [];
        $receipts     = $_POST["receipt_no"] ?? [];
        $dates        = $_POST["date_incurred"] ?? [];

        /*
         * Employees can only create claims for themselves.
         * Admin/HR/Payroll can select an employee.
         */

        if ($role === "Employee") {
            $claim_employee_id = (int)$emp_id;
        } else {
            $claim_employee_id = (int)($_POST["employee_id"] ?? 0);
        }

        if ($claim_employee_id <= 0) {
            $error = "Please select an employee.";
        } elseif ($claim_type === "") {
            $error = "Please enter a claim type.";
        } elseif ($claim_date === "") {
            $error = "Please select a claim date.";
        } else {

            /* ---------------------------------------------------------
            VERIFY EMPLOYEE EXISTS AND IS NOT REMOVED
            --------------------------------------------------------- */

            $employee_check = $conn->prepare("
                SELECT Emp_ID
                FROM employee
                WHERE Emp_ID = ?
                AND (Status <> 'Removed' OR Status IS NULL)
                LIMIT 1
            ");

            if (!$employee_check) {
                $error = "Database error while verifying employee.";
            } else {

                $employee_check->bind_param(
                    "i",
                    $claim_employee_id
                );

                $employee_check->execute();

                $employee_result = $employee_check->get_result();

                if ($employee_result->num_rows === 0) {
                    $error = "Invalid or inactive employee selected.";
                }

                $employee_check->close();
            }

            if ($error === "") {

            $valid_details = [];
            $total_amount = 0;

            for ($i = 0; $i < count($descriptions); $i++) {

                $description = trim($descriptions[$i] ?? "");
                $amount      = (float)($amounts[$i] ?? 0);
                $receipt_no  = trim($receipts[$i] ?? "");
                $date_inc    = $dates[$i] ?? "";

                if ($description === "" && $amount <= 0) {
                    continue;
                }

                if ($description === "") {
                    $error = "Every expense must have a description.";
                    break;
                }

                if ($amount <= 0) {
                    $error = "Every expense must have an amount greater than zero.";
                    break;
                }

                $valid_details[] = [
                    "description" => $description,
                    "amount" => $amount,
                    "receipt_no" => $receipt_no,
                    "date_incurred" => $date_inc
                ];

                $total_amount += $amount;
            }

            if ($error === "" && count($valid_details) === 0) {
                $error = "Please add at least one claim detail.";
            }

            if ($error === "" && $total_amount <= 0) {
                $error = "The claim total must be greater than zero.";
            }

            if ($error === "") {

                $conn->begin_transaction();

                try {

                    $claim_stmt = $conn->prepare("
                        INSERT INTO claims
                        (
                            Employee_ID,
                            Claim_Date,
                            Claim_Type,
                            Status,
                            Total_Amount
                        )
                        VALUES (?, ?, ?, 'Pending', ?)
                    ");

                    if (!$claim_stmt) {
                        throw new Exception("Unable to prepare claim.");
                    }

                    $claim_stmt->bind_param(
                        "issd",
                        $claim_employee_id,
                        $claim_date,
                        $claim_type,
                        $total_amount
                    );

                    if (!$claim_stmt->execute()) {
                        throw new Exception("Unable to save claim.");
                    }

                    $claim_id = $claim_stmt->insert_id;

                    $claim_stmt->close();

                    $detail_stmt = $conn->prepare("
                        INSERT INTO claim_details
                        (
                            Claim_ID,
                            Description,
                            Amount,
                            Receipt_No,
                            Date_Incurred
                        )
                        VALUES (?, ?, ?, ?, NULLIF(?, ''))
                    ");

                    if (!$detail_stmt) {
                        throw new Exception("Unable to prepare claim details.");
                    }

                    foreach ($valid_details as $detail) {

                        $detail_stmt->bind_param(
                            "isdss",
                            $claim_id,
                            $detail["description"],
                            $detail["amount"],
                            $detail["receipt_no"],
                            $detail["date_incurred"]
                        );

                        if (!$detail_stmt->execute()) {
                            throw new Exception("Unable to save claim details.");
                        }
                    }

                    $detail_stmt->close();

                    write_audit_log(
                        $conn,
                        $user_id,
                        "CLAIM_CREATED",
                        "Created Claim ID " . $claim_id .
                        " for Employee ID " . $claim_employee_id .
                        " with total amount ₱" .
                        number_format($total_amount, 2)
                    );

                    $conn->commit();

                    $message = "Claim submitted successfully.";

                } catch (Exception $e) {

                    $conn->rollback();

                    $error = "Unable to save the claim. Please try again.";
                }
            }
        }
    }
    }
}

/* =========================
   EMPLOYEE LIST
========================= */

$employees = [];

if ($role !== "Employee") {

    $employee_result = $conn->query("
        SELECT
            Emp_ID,
            First_Name,
            Last_Name
        FROM employee
        WHERE Status <> 'Removed'
           OR Status IS NULL
        ORDER BY Last_Name, First_Name
    ");

    if ($employee_result) {

        while ($employee = $employee_result->fetch_assoc()) {
            $employees[] = $employee;
        }
    }
}

/* =========================
   RECENT CLAIMS
========================= */

$claims = [];

if ($role === "Employee") {

    $claim_stmt = $conn->prepare("
        SELECT
            Claim_ID,
            Claim_Date,
            Claim_Type,
            Status,
            Total_Amount
        FROM claims
        WHERE Employee_ID = ?
        ORDER BY Claim_ID DESC
    ");

    $claim_stmt->bind_param("i", $emp_id);
    $claim_stmt->execute();

    $claim_result = $claim_stmt->get_result();

} else {

    $claim_result = $conn->query("
        SELECT
            c.Claim_ID,
            c.Claim_Date,
            c.Claim_Type,
            c.Status,
            c.Total_Amount,
            e.First_Name,
            e.Last_Name
        FROM claims c
        INNER JOIN employee e
            ON c.Employee_ID = e.Emp_ID
        ORDER BY c.Claim_ID DESC
    ");
}

if ($claim_result) {

    while ($claim = $claim_result->fetch_assoc()) {
        $claims[] = $claim;
    }
}

function e($value) {
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

    <title>Claims | Payroll & Benefits</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #090b0d;
            color: #f4efe8;
        }

        .container {
            width: min(1200px, 94%);
            margin: 30px auto;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .title {
            font-size: 28px;
            font-weight: 700;
            color: #f4efe8;
        }

        .subtitle {
            margin-top: 5px;
            color: #9ca3a8;
            font-size: 14px;
        }

        .back-btn {
            display: inline-block;
            text-decoration: none;
            background: #641C28;
            color: #fff;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 13px;
        }

        .back-btn:hover {
            background: #7A2635;
        }

        .panel {
            background: #111417;
            border: 1px solid #282d31;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .panel-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            color: #c4c8ca;
            font-size: 13px;
        }

        input,
        select {
            width: 100%;
            padding: 11px 12px;
            border-radius: 7px;
            border: 1px solid #282d31;
            background: #090b0d;
            color: #f4efe8;
            outline: none;
        }

        input:focus,
        select:focus {
            border-color: #C9A66B;
        }

        .details-header {
            display: grid;
            grid-template-columns: 1.5fr 150px 150px 150px 45px;
            gap: 10px;
            margin-top: 20px;
            padding-bottom: 8px;
            border-bottom: 1px solid #282d31;
        }

        .details-header div {
            font-size: 12px;
            color: #9ca3a8;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 1.5fr 150px 150px 150px 45px;
            gap: 10px;
            margin-top: 10px;
        }

        .remove-btn {
            border: 0;
            border-radius: 7px;
            background: #4a252b;
            color: #f4efe8;
            cursor: pointer;
            font-size: 18px;
        }

        .remove-btn:hover {
            background: #641C28;
        }

        .add-btn {
            margin-top: 14px;
            border: 1px solid #C9A66B;
            background: transparent;
            color: #C9A66B;
            padding: 10px 15px;
            border-radius: 7px;
            cursor: pointer;
        }

        .add-btn:hover {
            background: rgba(201,166,107,.12);
        }

        .summary {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 15px;
            padding-top: 15px;
            border-top: 1px solid #282d31;
        }

        .summary-label {
            color: #9ca3a8;
        }

        .total {
            font-size: 22px;
            font-weight: 700;
            color: #C9A66B;
        }

        .submit-btn {
            margin-top: 20px;
            width: 100%;
            border: 0;
            border-radius: 8px;
            padding: 13px;
            background: #641C28;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }

        .submit-btn:hover {
            background: #7A2635;
        }

        .message {
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
            background: rgba(121,184,154,.12);
            border: 1px solid #35634d;
            color: #9dd4b5;
        }

        .error {
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
            background: rgba(122,38,53,.15);
            border: 1px solid #641C28;
            color: #e5aab4;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 13px 10px;
            border-bottom: 1px solid #242A30;
            text-align: left;
            font-size: 13px;
        }

        th {
            color: #9ca3a8;
            font-weight: 500;
        }

        td {
            color: #f4efe8;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 11px;
            background: #2a2e32;
            color: #d5d8da;
        }

        .amount {
            color: #C9A66B;
            font-weight: 600;
        }
        .view-btn {
        display: inline-block;
        padding: 7px 12px;
        border-radius: 6px;
        background: #641C28;
        color: #f4efe8;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        transition: background .2s ease;
    }
        .review-btn {
        display: inline-block;
        padding: 7px 12px;
        margin-left: 5px;
        border-radius: 6px;
        background: #C9A66B;
        color: #090b0d;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        transition: background .2s ease;
}
        .reimbursement-btn {
        display: inline-block;
        padding: 7px 12px;
        margin-left: 5px;
        border-radius: 6px;
        background: #79b89a;
        color: #090b0d;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        transition: background .2s ease;
}

.reimbursement-btn:hover {
    background: #9ad0b3;
}

.review-btn:hover {
    background: #e0c58f;
}

    .view-btn:hover {
        background: #7A2635;
    }

        .empty {
            text-align: center;
            color: #9ca3a8;
            padding: 30px;
        }

        @media (max-width: 800px) {

        .form-grid {
        grid-template-columns: 1fr;
        }

        .field.full {
        grid-column: auto;
        }

        .details-header {
        display: none;
        }

        .detail-row {
        grid-template-columns: 1fr;
        padding: 14px;
        border: 1px solid #282d31;
        border-radius: 8px;
        }

        .remove-btn {
        min-height: 42px;
        }

        .topbar {
        align-items: flex-start;
        flex-direction: column;
        }

    }

    </style>

</head>

<body>

<div class="container">

    <div class="topbar">

        <div>

            <div class="title">
                Claims
            </div>

            <div class="subtitle">
                Submit and monitor employee claims
            </div>

        </div>

        <a
            href="dashboard.php"
            class="back-btn"
        >
            ← Dashboard
        </a>

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


    <!-- CREATE CLAIM -->

    <div class="panel">

        <div class="panel-title">
            Create New Claim
        </div>

        <form
            method="POST"
            action=""
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e(csrf_token()); ?>"
            >

            <div class="form-grid">

                <?php if ($role !== "Employee"): ?>

                    <div class="field">

                        <label for="employee_id">
                            Employee
                        </label>

                        <select
                            name="employee_id"
                            id="employee_id"
                            required
                        >

                            <option value="">
                                Select Employee
                            </option>

                            <?php foreach ($employees as $employee): ?>

                                <option
                                    value="<?php echo e($employee["Emp_ID"]); ?>"
                                >
                                    <?php echo e(
                                        $employee["Last_Name"] .
                                        ", " .
                                        $employee["First_Name"]
                                    ); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                <?php else: ?>

                    <div class="field">

                        <label>
                            Employee
                        </label>

                        <input
                            type="text"
                            value="<?php echo e($full_name); ?>"
                            readonly
                        >

                    </div>

                <?php endif; ?>


                <div class="field">

                    <label for="claim_type">
                        Claim Type
                    </label>

                    <input
                        type="text"
                        name="claim_type"
                        id="claim_type"
                        placeholder="e.g. Medical, Travel, Transportation"
                        required
                    >

                </div>


                <div class="field">

                    <label for="claim_date">
                        Claim Date
                    </label>

                    <input
                        type="date"
                        name="claim_date"
                        id="claim_date"
                        value="<?php echo date("Y-m-d"); ?>"
                        required
                    >

                </div>

            </div>


            <div class="panel-title" style="margin-top:28px;">
                Claim Details
            </div>


            <div class="details-header">

                <div>Description</div>
                <div>Amount</div>
                <div>Receipt No.</div>
                <div>Date Incurred</div>
                <div></div>

            </div>


            <div id="detailsContainer">

                <div class="detail-row">

                    <input
                        type="text"
                        name="description[]"
                        placeholder="Expense description"
                    >

                    <input
                        type="number"
                        name="amount[]"
                        class="amount-input"
                        placeholder="0.00"
                        min="0"
                        step="0.01"
                    >

                    <input
                        type="text"
                        name="receipt_no[]"
                        placeholder="Receipt number"
                    >

                    <input
                        type="date"
                        name="date_incurred[]"
                    >

                    <button
                        type="button"
                        class="remove-btn"
                        title="Remove"
                    >
                        ×
                    </button>

                </div>

            </div>


            <button
                type="button"
                class="add-btn"
                id="addDetail"
            >
                + Add Expense
            </button>


            <div class="summary">

                <span class="summary-label">
                    Total:
                </span>

                <span
                    class="total"
                    id="totalAmount"
                >
                    ₱0.00
                </span>

            </div>


            <button
                type="submit"
                class="submit-btn"
            >
                Submit Claim
            </button>

        </form>

    </div>


    <!-- CLAIM HISTORY -->

    <div class="panel">

        <div class="panel-title">
            Claim Records
        </div>

        <div class="table-wrap">

            <table>

                <thead>

                    <tr>

                        <th>Claim ID</th>

                        <?php if ($role !== "Employee"): ?>

                            <th>Employee</th>

                        <?php endif; ?>

                        <tr>
                            <th>Claim ID</th>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Action</th>
                        </tr>

                </thead>

                <tbody>

                    <?php if (count($claims) > 0): ?>

                        <?php foreach ($claims as $claim): ?>

                            <tr>

                                <td>
                                    #<?php echo e($claim["Claim_ID"]); ?>
                                </td>

                                <?php if ($role !== "Employee"): ?>

                                    <td>
                                        <?php echo e(
                                            $claim["Last_Name"] .
                                            ", " .
                                            $claim["First_Name"]
                                        ); ?>
                                    </td>

                                <?php endif; ?>

                                <td>
                                    <?php echo e($claim["Claim_Date"]); ?>
                                </td>

                                <td>
                                    <?php echo e($claim["Claim_Type"]); ?>
                                </td>

                                <td>

                                    <span class="status">
                                        <?php echo e($claim["Status"]); ?>
                                    </span>

                                </td>

                                <td class="amount">
                                ₱<?php echo number_format(
                                    (float)$claim["Total_Amount"],
                                    2
                                ); ?>

                            </td>

                            <td>
                        <a
                            href="view_claim.php?id=<?php echo e($claim["Claim_ID"]); ?>"
                            class="view-btn"
                        >
                            View
                        </a>

                        <?php if (
                            in_array($role, ["Administrator", "HR Staff"], true)
                            && $claim["Status"] === "Pending"
                        ): ?>

                            <a
                                href="review_claim.php?id=<?php echo e($claim["Claim_ID"]); ?>"
                                class="review-btn"
                            >
                                Review
                            </a>

                        <?php endif; ?>


                        <?php if (
                            in_array($role, ["Administrator", "Payroll Staff"], true)
                            && $claim["Status"] === "Approved"
                        ): ?>

                            <a
                                href="reimbursements.php"
                                class="reimbursement-btn"
                            >
                                Reimbursement
                            </a>

                        <?php endif; ?>

                    </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="<?php echo $role === "Employee" ? 6 : 7; ?>"
                                class="empty"
                            >
                                No claims have been submitted yet.
                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<script>

(function () {

    const container =
        document.getElementById("detailsContainer");

    const addButton =
        document.getElementById("addDetail");

    const totalDisplay =
        document.getElementById("totalAmount");


    function updateTotal() {

        let total = 0;

        const inputs =
            container.querySelectorAll(".amount-input");

        inputs.forEach(function (input) {

            const value =
                parseFloat(input.value);

            if (!isNaN(value) && value > 0) {
                total += value;
            }

        });

        totalDisplay.textContent =
            "₱" +
            total.toLocaleString(
                "en-PH",
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );
    }


    function attachRemoveButton(row) {

        const removeButton =
            row.querySelector(".remove-btn");

        removeButton.addEventListener(
            "click",
            function () {

                const rows =
                    container.querySelectorAll(".detail-row");

                if (rows.length === 1) {

                    row.querySelectorAll("input").forEach(
                        function (input) {
                            input.value = "";
                        }
                    );

                } else {

                    row.remove();

                }

                updateTotal();

            }
        );

    }


    container
        .querySelectorAll(".detail-row")
        .forEach(attachRemoveButton);


    container.addEventListener(
        "input",
        function (event) {

            if (
                event.target.classList.contains(
                    "amount-input"
                )
            ) {

                updateTotal();

            }

        }
    );


    addButton.addEventListener(
        "click",
        function () {

            const row =
                document.createElement("div");

            row.className = "detail-row";

            row.innerHTML = `
                <input
                    type="text"
                    name="description[]"
                    placeholder="Expense description"
                >

                <input
                    type="number"
                    name="amount[]"
                    class="amount-input"
                    placeholder="0.00"
                    min="0"
                    step="0.01"
                >

                <input
                    type="text"
                    name="receipt_no[]"
                    placeholder="Receipt number"
                >

                <input
                    type="date"
                    name="date_incurred[]"
                >

                <button
                    type="button"
                    class="remove-btn"
                    title="Remove"
                >
                    ×
                </button>
            `;

            container.appendChild(row);

            attachRemoveButton(row);

        }
    );


    updateTotal();

})();

</script>

</body>
</html>