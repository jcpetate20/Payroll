<?php

require_once "db.php";
require_once "session_security.php";
require_once "csrf.php";

if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["User_ID"];
$role    = $_SESSION["Role"];
$emp_id  = $_SESSION["Emp_ID"];

$claim_id = (int)($_GET["id"] ?? 0);

if ($claim_id <= 0) {
    die("Invalid claim.");
}

/*
|--------------------------------------------------------------------------
| Get Claim
|--------------------------------------------------------------------------
*/

if ($role === "Employee") {

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
          AND c.Employee_ID = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "ii",
        $claim_id,
        $emp_id
    );

} else {

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
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $claim_id
    );
}

$stmt->execute();

$result = $stmt->get_result();

$claim = $result->fetch_assoc();

$stmt->close();

if (!$claim) {
    http_response_code(404);
    die("Claim not found or access denied.");
}

/*
|--------------------------------------------------------------------------
| Get Claim Details
|--------------------------------------------------------------------------
*/

$details = [];

$detail_stmt = $conn->prepare("
    SELECT
        Detail_ID,
        Description,
        Amount,
        Receipt_No,
        Date_Incurred
    FROM claim_details
    WHERE Claim_ID = ?
    ORDER BY Detail_ID ASC
");

$detail_stmt->bind_param(
    "i",
    $claim_id
);

$detail_stmt->execute();

$detail_result = $detail_stmt->get_result();

while ($detail = $detail_result->fetch_assoc()) {
    $details[] = $detail;
}

$detail_stmt->close();


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

    <title>
        View Claim #<?php echo e($claim["Claim_ID"]); ?>
    </title>

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
            width: min(1100px, 94%);
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

        .claim-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .info {
            padding: 15px;
            background: #15191c;
            border: 1px solid #282d31;
            border-radius: 8px;
        }

        .info-label {
            color: #9ca3a8;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .info-value {
            color: #f4efe8;
            font-size: 14px;
            font-weight: 600;
        }

        .status {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            background: #2a2e32;
            color: #d5d8da;
            font-size: 11px;
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

        .amount {
            color: #C9A66B;
            font-weight: 600;
        }

        .total-row td {
            border-bottom: 0;
            padding-top: 20px;
            font-weight: 700;
        }

        .total-value {
            color: #C9A66B;
            font-size: 20px;
        }

        .empty {
            text-align: center;
            color: #9ca3a8;
            padding: 30px;
        }

        @media (max-width: 700px) {

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .claim-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <div class="topbar">

        <div>

            <div class="title">
                Claim #<?php echo e($claim["Claim_ID"]); ?>
            </div>

            <div class="subtitle">
                Claim details and expense information
            </div>

        </div>

        <a
            href="claims.php"
            class="back-btn"
        >
            ← Back to Claims
        </a>

    </div>


    <!-- CLAIM INFORMATION -->

    <div class="panel">

        <div class="panel-title">
            Claim Information
        </div>

        <div class="claim-grid">

            <div class="info">

                <div class="info-label">
                    Employee
                </div>

                <div class="info-value">
                    <?php echo e(
                        $claim["First_Name"] .
                        " " .
                        $claim["Last_Name"]
                    ); ?>
                </div>

            </div>


            <div class="info">

                <div class="info-label">
                    Claim Type
                </div>

                <div class="info-value">
                    <?php echo e($claim["Claim_Type"]); ?>
                </div>

            </div>


            <div class="info">

                <div class="info-label">
                    Claim Date
                </div>

                <div class="info-value">
                    <?php echo e($claim["Claim_Date"]); ?>
                </div>

            </div>


            <div class="info">

                <div class="info-label">
                    Status
                </div>

                <div class="info-value">

                    <span class="status">
                        <?php echo e($claim["Status"]); ?>
                    </span>

                </div>

            </div>

        </div>

    </div>


    <!-- CLAIM DETAILS -->

    <div class="panel">

        <div class="panel-title">
            Claim Details
        </div>

        <div class="table-wrap">

            <table>

                <thead>

                    <tr>

                        <th>#</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Receipt No.</th>
                        <th>Date Incurred</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if (count($details) > 0): ?>

                        <?php foreach ($details as $index => $detail): ?>

                            <tr>

                                <td>
                                    <?php echo $index + 1; ?>
                                </td>

                                <td>
                                    <?php echo e(
                                        $detail["Description"]
                                    ); ?>
                                </td>

                                <td class="amount">

                                    ₱<?php echo number_format(
                                        (float)$detail["Amount"],
                                        2
                                    ); ?>

                                </td>

                                <td>
                                    <?php
                                    echo e(
                                        $detail["Receipt_No"]
                                        ?: "—"
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo e(
                                        $detail["Date_Incurred"]
                                        ?: "—"
                                    );
                                    ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="5"
                                class="empty"
                            >
                                No claim details found.
                            </td>

                        </tr>

                    <?php endif; ?>


                    <tr class="total-row">

                        <td colspan="2">
                            Total Claim
                        </td>

                        <td
                            colspan="3"
                            class="total-value"
                        >
                            ₱<?php echo number_format(
                                (float)$claim["Total_Amount"],
                                2
                            ); ?>
                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>

</html>