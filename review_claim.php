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

$allowed_roles = ["Administrator", "HR Staff"];

if (!in_array($role, $allowed_roles, true)) {
    die("Access denied.");
}

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("Invalid claim ID.");
}

$claim_id = (int) $_GET["id"];

$message = "";
$error = "";

/* =========================
   PROCESS ACTION
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrf = $_POST["csrf_token"] ?? "";

    if (!verify_csrf_token($csrf)) {
        $error = "Invalid security token.";
    } else {

        $action = $_POST["action"] ?? "";

        if (!in_array($action, ["Approved", "Rejected"], true)) {
            $error = "Invalid action.";
        } else {

            $stmt = $conn->prepare("
                UPDATE claims
                SET Status = ?
                WHERE Claim_ID = ?
            ");

            if (!$stmt) {
                $error = "Unable to prepare the claim update.";
            } else {

                $stmt->bind_param("si", $action, $claim_id);

                if ($stmt->execute()) {

                    $description = "Claim #{$claim_id} was marked as {$action}.";

                    write_audit_log(
                        $conn,
                        $_SESSION["User_ID"],
                        "CLAIM_" . strtoupper($action),
                        $description
                    );

                    $message = "Claim has been successfully marked as {$action}.";
                } else {
                    $error = "Unable to update the claim.";
                }

                $stmt->close();
            }
        }
    }
}

/* =========================
   GET CLAIM INFORMATION
========================= */

$stmt = $conn->prepare("
    SELECT
        c.Claim_ID,
        c.Employee_ID,
        c.Claim_Date,
        c.Claim_Type,
        c.Status,
        c.Total_Amount,
        e.First_Name,
        e.Last_Name
    FROM claims c
    INNER JOIN employee e
        ON c.Employee_ID = e.Emp_ID
    WHERE c.Claim_ID = ?
");

$stmt->bind_param("i", $claim_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    die("Claim not found.");
}

$claim = $result->fetch_assoc();

$stmt->close();

/* =========================
   GET CLAIM DETAILS
========================= */

$detail_stmt = $conn->prepare("
    SELECT
        Description,
        Amount,
        Receipt_No,
        Date_Incurred
    FROM claim_details
    WHERE Claim_ID = ?
    ORDER BY Detail_ID ASC
");

$detail_stmt->bind_param("i", $claim_id);
$detail_stmt->execute();

$details = $detail_stmt->get_result();

function e($value)
{
    return htmlspecialchars($value ?? "", ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Review Claim</title>

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
    width: 92%;
    max-width: 1100px;
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
    text-decoration: none;
    color: #f4efe8;
    background: #641C28;
    padding: 10px 16px;
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
    margin-bottom: 20px;
}

.card h2 {
    margin-top: 0;
    color: #C9A66B;
    font-size: 18px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.info-item {
    background: #15191c;
    border: 1px solid #282d31;
    padding: 15px;
    border-radius: 7px;
}

.label {
    color: #9ca3a8;
    font-size: 12px;
    margin-bottom: 5px;
}

.value {
    font-size: 15px;
    color: #f4efe8;
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
    font-size: 13px;
}

td {
    padding: 12px;
    border-bottom: 1px solid #282d31;
    font-size: 13px;
}

.total {
    text-align: right;
    color: #C9A66B;
    font-size: 18px;
    font-weight: bold;
    margin-top: 20px;
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

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: bold;
}

.pending {
    background: rgba(201,166,107,.15);
    color: #C9A66B;
}

.approved {
    background: rgba(121,184,154,.15);
    color: #79b89a;
}

.rejected {
    background: rgba(122,38,53,.2);
    color: #f0aab5;
}

.actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}

.action-btn {
    border: none;
    padding: 11px 18px;
    border-radius: 7px;
    font-weight: bold;
    cursor: pointer;
    font-size: 14px;
}

.approve-btn {
    background: #79b89a;
    color: #090b0d;
}

.reject-btn {
    background: #641C28;
    color: #f4efe8;
}

.action-btn:hover {
    opacity: .85;
}

@media (max-width: 700px) {

    .info-grid {
        grid-template-columns: 1fr;
    }

    .header {
        flex-direction: column;
        align-items: flex-start;
    }

    table {
        display: block;
        overflow-x: auto;
    }

    .actions {
        flex-direction: column;
    }

}

</style>

</head>

<body>

<div class="container">

    <div class="header">

        <h1>Review Claim #<?php echo e($claim["Claim_ID"]); ?></h1>

        <a href="claims.php" class="back-btn">
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

    <div class="card">

        <h2>Claim Information</h2>

        <div class="info-grid">

            <div class="info-item">

                <div class="label">
                    Employee
                </div>

                <div class="value">
                    <?php echo e($claim["First_Name"] . " " . $claim["Last_Name"]); ?>
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
                    Claim Date
                </div>

                <div class="value">
                    <?php echo e($claim["Claim_Date"]); ?>
                </div>

            </div>

            <div class="info-item">

                <div class="label">
                    Status
                </div>

                <div class="value">

                    <?php
                    $status_class = strtolower($claim["Status"]);
                    ?>

                    <span class="status <?php echo e($status_class); ?>">
                        <?php echo e($claim["Status"]); ?>
                    </span>

                </div>

            </div>

        </div>

    </div>

    <div class="card">

        <h2>Claim Details</h2>

        <table>

            <thead>

                <tr>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Receipt No.</th>
                    <th>Date Incurred</th>
                </tr>

            </thead>

            <tbody>

            <?php while ($detail = $details->fetch_assoc()): ?>

                <tr>

                    <td>
                        <?php echo e($detail["Description"]); ?>
                    </td>

                    <td>
                        ₱<?php echo number_format((float)$detail["Amount"], 2); ?>
                    </td>

                    <td>
                        <?php echo e($detail["Receipt_No"] ?: "—"); ?>
                    </td>

                    <td>
                        <?php echo e($detail["Date_Incurred"] ?: "—"); ?>
                    </td>

                </tr>

            <?php endwhile; ?>

            </tbody>

        </table>

        <div class="total">
            Total: ₱<?php echo number_format((float)$claim["Total_Amount"], 2); ?>
        </div>

    </div>

    <div class="card">

        <h2>Claim Decision</h2>

        <?php if ($claim["Status"] === "Pending"): ?>

            <p style="color:#9ca3a8;">
                Review the claim details before choosing an action.
            </p>

            <div class="actions">

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo e(csrf_token()); ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="Approved"
                    >

                    <button
                        type="submit"
                        class="action-btn approve-btn"
                    >
                        Approve Claim
                    </button>

                </form>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo e(csrf_token()); ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="Rejected"
                    >

                    <button
                        type="submit"
                        class="action-btn reject-btn"
                    >
                        Reject Claim
                    </button>

                </form>

            </div>

        <?php else: ?>

            <p style="color:#9ca3a8;">
                This claim has already been processed.
            </p>

        <?php endif; ?>

    </div>

</div>

</body>
</html>

<?php
$detail_stmt->close();
?>