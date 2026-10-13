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

if (!in_array($role, ["Administrator", "Payroll Staff"], true)) {
    die("Access denied.");
}

$message = "";
$error = "";

/* =========================
   PROCESS REIMBURSEMENT
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrf = $_POST["csrf_token"] ?? "";

    if (!verify_csrf_token($csrf)) {

        $error = "Invalid security token.";

    } else {

        $claim_id = isset($_POST["claim_id"])
            ? (int) $_POST["claim_id"]
            : 0;

        $approver_id = isset($_POST["approver_id"])
            ? (int) $_POST["approver_id"]
            : 0;

        $reimbursement_date = $_POST["reimbursement_date"] ?? "";
        $reference_no = trim($_POST["reference_no"] ?? "");

        if ($claim_id <= 0) {

            $error = "Invalid claim.";

        } else
        
                if ($approver_id <= 0) {
            $error = "Please select an approver.";
        } elseif (empty($reimbursement_date)) {
            $error = "Please select a reimbursement date.";
        } else {

            /* ---------------------------------------------------------
            VERIFY APPROVER EXISTS
            --------------------------------------------------------- */

            $approver_stmt = $conn->prepare("
                SELECT Approver_ID
                FROM approvers
                WHERE Approver_ID = ?
                LIMIT 1
            ");

            if (!$approver_stmt) {
                $error = "Database error while verifying approver.";
            } else {

                $approver_stmt->bind_param("i", $approver_id);
                $approver_stmt->execute();

                $approver_result = $approver_stmt->get_result();

                if ($approver_result->num_rows === 0) {
                    $error = "Invalid approver selected.";
                }

                $approver_stmt->close();
            }

            if ($error === "") {

            /* Get approved claim */

            $claim_stmt = $conn->prepare("
                SELECT
                    Claim_ID,
                    Total_Amount,
                    Status
                FROM claims
                WHERE Claim_ID = ?
            ");

            $claim_stmt->bind_param("i", $claim_id);
            $claim_stmt->execute();

            $claim_result = $claim_stmt->get_result();

            if ($claim_result->num_rows === 0) {

                $error = "Claim not found.";

            } else {

                $claim = $claim_result->fetch_assoc();

                if ($claim["Status"] !== "Approved") {

                    $error = "Only approved claims can be reimbursed.";

                } else {

                    /* Check if already reimbursed */

                    $check_stmt = $conn->prepare("
                        SELECT Reimbursement_ID
                        FROM reimbursements
                        WHERE Claim_ID = ?
                        LIMIT 1
                    ");

                    $check_stmt->bind_param("i", $claim_id);
                    $check_stmt->execute();

                    $check_result = $check_stmt->get_result();

                    if ($check_result->num_rows > 0) {

                        $error = "This claim already has a reimbursement record.";

                    } else {

                        $status = "Reimbursed";
                        $total_amount = (float) $claim["Total_Amount"];

                        $insert_stmt = $conn->prepare("
                            INSERT INTO reimbursements
                            (
                                Claim_ID,
                                Approver_ID,
                                Status,
                                Reimbursement_Date,
                                Total_Amount,
                                Reference_No
                            )
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");

                        $insert_stmt->bind_param(
                            "iissds",
                            $claim_id,
                            $approver_id,
                            $status,
                            $reimbursement_date,
                            $total_amount,
                            $reference_no
                        );

                        if ($insert_stmt->execute()) {

                            $audit_description =
                                "Claim #{$claim_id} was reimbursed. " .
                                "Amount: ₱" .
                                number_format($total_amount, 2) .
                                ".";

                            write_audit_log(
                                $conn,
                                $_SESSION["User_ID"],
                                "CLAIM_REIMBURSED",
                                $audit_description
                            );

                            $message =
                                "Reimbursement successfully recorded.";

                        } else {

                            $error =
                                "Unable to save the reimbursement.";
                        }

                        $insert_stmt->close();
                    }

                    $check_stmt->close();
                }
            }

            $claim_stmt->close();
        }
    }
    }
}

/* =========================
   GET APPROVERS
========================= */

$approvers = $conn->query("
    SELECT
        Approver_ID,
        Approver_Name,
        Role
    FROM approvers
    ORDER BY Approver_Name ASC
");

/* =========================
   GET APPROVED CLAIMS
========================= */

$claims = $conn->query("
    SELECT
        c.Claim_ID,
        c.Claim_Date,
        c.Claim_Type,
        c.Total_Amount,
        e.First_Name,
        e.Last_Name
    FROM claims c
    INNER JOIN employee e
        ON c.Employee_ID = e.Emp_ID
    LEFT JOIN reimbursements r
        ON c.Claim_ID = r.Claim_ID
    WHERE c.Status = 'Approved'
      AND r.Reimbursement_ID IS NULL
    ORDER BY c.Claim_ID DESC
");

/* =========================
   GET REIMBURSEMENT HISTORY
========================= */

$history = $conn->query("
    SELECT
        r.Reimbursement_ID,
        r.Claim_ID,
        r.Status,
        r.Reimbursement_Date,
        r.Total_Amount,
        r.Reference_No,
        a.Approver_Name,
        e.First_Name,
        e.Last_Name
    FROM reimbursements r
    INNER JOIN claims c
        ON r.Claim_ID = c.Claim_ID
    INNER JOIN employee e
        ON c.Employee_ID = e.Emp_ID
    LEFT JOIN approvers a
        ON r.Approver_ID = a.Approver_ID
    ORDER BY r.Reimbursement_ID DESC
");

function e($value)
{
    return htmlspecialchars(
        $value ?? "",
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

<title>Reimbursement Processing</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #090b0d;
    color: #f4efe8;
    font-family: Arial, sans-serif;
}

.container {
    width: 94%;
    max-width: 1200px;
    margin: 40px auto;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
}

h1 {
    margin: 0;
    color: #C9A66B;
}

.back-btn {
    display: inline-block;
    padding: 10px 16px;
    background: #641C28;
    color: #f4efe8;
    text-decoration: none;
    border-radius: 7px;
    font-size: 14px;
}

.back-btn:hover {
    background: #7A2635;
}

.card {
    background: #111417;
    border: 1px solid #282d31;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 25px;
}

.card h2 {
    margin-top: 0;
    color: #C9A66B;
    font-size: 19px;
}

.message {
    background: rgba(121,184,154,.12);
    border: 1px solid #79b89a;
    color: #79b89a;
    padding: 12px;
    border-radius: 7px;
    margin-bottom: 20px;
}

.error {
    background: rgba(122,38,53,.15);
    border: 1px solid #7A2635;
    color: #f0aab5;
    padding: 12px;
    border-radius: 7px;
    margin-bottom: 20px;
}

.claim-box {
    background: #15191c;
    border: 1px solid #282d31;
    border-radius: 8px;
    padding: 18px;
    margin-bottom: 15px;
}

.claim-info {
    display: grid;
    grid-template-columns:
        repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 18px;
}

.info-item {
    min-width: 0;
}

.label {
    color: #9ca3a8;
    font-size: 11px;
    margin-bottom: 5px;
}

.value {
    color: #f4efe8;
    font-size: 14px;
}

.amount {
    color: #C9A66B;
    font-weight: bold;
}

.form-grid {
    display: grid;
    grid-template-columns:
        repeat(3, 1fr);
    gap: 15px;
    align-items: end;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.form-group label {
    color: #c4c8ca;
    font-size: 12px;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 10px;
    background: #090b0d;
    border: 1px solid #282d31;
    border-radius: 6px;
    color: #f4efe8;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #C9A66B;
}

.process-btn {
    border: none;
    padding: 10px 16px;
    background: #C9A66B;
    color: #090b0d;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
}

.process-btn:hover {
    background: #e0c58f;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #641C28;
    color: #f4efe8;
    padding: 12px;
    text-align: left;
    font-size: 12px;
}

td {
    padding: 12px;
    border-bottom: 1px solid #282d31;
    font-size: 13px;
}

.status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 5px;
    background: rgba(121,184,154,.15);
    color: #79b89a;
    font-size: 11px;
    font-weight: bold;
}

.empty {
    color: #9ca3a8;
    padding: 15px 0;
}

@media (max-width: 850px) {

    .claim-info {
        grid-template-columns: repeat(2, 1fr);
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .header {
        align-items: flex-start;
        flex-direction: column;
    }

    table {
        display: block;
        overflow-x: auto;
    }
}

@media (max-width: 500px) {

    .claim-info {
        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>

<div class="container">

    <div class="header">

        <h1>Reimbursement Processing</h1>

        <a
            href="claims.php"
            class="back-btn"
        >
            ← Back to Claims
        </a>

    </div>

    <?php if ($message): ?>

        <div class="message">
            <?php echo e($message); ?>
        </div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="error">
            <?php echo e($error); ?>
        </div>

    <?php endif; ?>


    <!-- =========================
         APPROVED CLAIMS
    ========================== -->

    <div class="card">

        <h2>Approved Claims for Reimbursement</h2>

        <?php if ($claims && $claims->num_rows > 0): ?>

            <?php while ($claim = $claims->fetch_assoc()): ?>

                <div class="claim-box">

                    <div class="claim-info">

                        <div class="info-item">

                            <div class="label">
                                Claim ID
                            </div>

                            <div class="value">
                                #<?php echo e($claim["Claim_ID"]); ?>
                            </div>

                        </div>

                        <div class="info-item">

                            <div class="label">
                                Employee
                            </div>

                            <div class="value">
                                <?php
                                echo e(
                                    $claim["First_Name"]
                                    . " "
                                    . $claim["Last_Name"]
                                );
                                ?>
                            </div>

                        </div>

                        <div class="info-item">

                            <div class="label">
                                Claim Type
                            </div>

                            <div class="value">
                                <?php echo e($claim["Claim_Type"]); ?>
                            </div>

                        </div>

                        <div class="info-item">

                            <div class="label">
                                Total Amount
                            </div>

                            <div class="value amount">
                                ₱<?php
                                echo number_format(
                                    (float)$claim["Total_Amount"],
                                    2
                                );
                                ?>
                            </div>

                        </div>

                    </div>


                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php
                            echo e(csrf_token());
                            ?>"
                        >

                        <input
                            type="hidden"
                            name="claim_id"
                            value="<?php
                            echo e($claim["Claim_ID"]);
                            ?>"
                        >

                        <div class="form-grid">

                            <div class="form-group">

                                <label>
                                    Approver
                                </label>

                                <select
                                    name="approver_id"
                                    required
                                >

                                    <option value="">
                                        Select Approver
                                    </option>

                                    <?php
                                    if ($approvers):
                                        $approvers->data_seek(0);
                                    ?>

                                        <?php
                                        while (
                                            $approver =
                                            $approvers->fetch_assoc()
                                        ):
                                        ?>

                                            <option
                                                value="<?php
                                                echo e(
                                                    $approver["Approver_ID"]
                                                );
                                                ?>"
                                            >
                                                <?php
                                                echo e(
                                                    $approver[
                                                        "Approver_Name"
                                                    ]
                                                    . " - "
                                                    . $approver["Role"]
                                                );
                                                ?>
                                            </option>

                                        <?php endwhile; ?>

                                    <?php endif; ?>

                                </select>

                            </div>


                            <div class="form-group">

                                <label>
                                    Reimbursement Date
                                </label>

                                <input
                                    type="date"
                                    name="reimbursement_date"
                                    value="<?php
                                    echo date("Y-m-d");
                                    ?>"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    Reference No.
                                </label>

                                <input
                                    type="text"
                                    name="reference_no"
                                    placeholder="Optional reference number"
                                    maxlength="100"
                                >

                            </div>

                        </div>

                        <div style="margin-top:15px;">

                            <button
                                type="submit"
                                class="process-btn"
                            >
                                Process Reimbursement
                            </button>

                        </div>

                    </form>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="empty">
                There are currently no approved claims
                waiting for reimbursement.
            </div>

        <?php endif; ?>

    </div>


    <!-- =========================
         REIMBURSEMENT HISTORY
    ========================== -->

    <div class="card">

        <h2>Reimbursement History</h2>

        <?php if ($history && $history->num_rows > 0): ?>

            <table>

                <thead>

                    <tr>
                        <th>Reimbursement ID</th>
                        <th>Claim ID</th>
                        <th>Employee</th>
                        <th>Approver</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Reference No.</th>
                        <th>Status</th>
                    </tr>

                </thead>

                <tbody>

                <?php while ($item = $history->fetch_assoc()): ?>

                    <tr>

                        <td>
                            #<?php
                            echo e(
                                $item["Reimbursement_ID"]
                            );
                            ?>
                        </td>

                        <td>
                            #<?php
                            echo e(
                                $item["Claim_ID"]
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo e(
                                $item["First_Name"]
                                . " "
                                . $item["Last_Name"]
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo e(
                                $item["Approver_Name"]
                                ?: "—"
                            );
                            ?>
                        </td>

                        <td class="amount">
                            ₱<?php
                            echo number_format(
                                (float)$item["Total_Amount"],
                                2
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo e(
                                $item["Reimbursement_Date"]
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo e(
                                $item["Reference_No"]
                                ?: "—"
                            );
                            ?>
                        </td>

                        <td>

                            <span class="status">
                                <?php
                                echo e(
                                    $item["Status"]
                                );
                                ?>
                            </span>

                        </td>

                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        <?php else: ?>

            <div class="empty">
                No reimbursement records yet.
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>