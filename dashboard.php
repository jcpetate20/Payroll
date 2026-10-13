<?php

session_start();

require_once "db.php";
require_once "session_security.php";

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Logged-in user information
|--------------------------------------------------------------------------
*/

$user_id   = $_SESSION["User_ID"];
$username  = $_SESSION["Username"];
$full_name = $_SESSION["Full_Name"];
$role      = $_SESSION["Role"];
$emp_id    = $_SESSION["Emp_ID"];

/*
|--------------------------------------------------------------------------
| Dashboard statistics
|--------------------------------------------------------------------------
*/

$total_employees = 0;
$total_payroll = 0;
$total_gross = 0;
$total_deductions = 0;
$total_net_pay = 0;
$total_active_benefits = 0;
$total_users = 0;

/*
|--------------------------------------------------------------------------
| Claims & Reimbursement statistics
|--------------------------------------------------------------------------
*/

$total_pending_claims = 0;
$total_approved_claims = 0;
$total_rejected_claims = 0;
$total_reimbursed_claims = 0;

$total_claimed_amount = 0;
$total_reimbursed_amount = 0;
$total_pending_reimbursements = 0;

$my_payroll_count = 0;
$my_gross_pay = 0;
$my_deductions = 0;
$my_net_pay = 0;
$my_active_benefits = 0;

$monthly_payroll = [];
$benefit_distribution = [];

/*
|--------------------------------------------------------------------------
| Employee dashboard
|--------------------------------------------------------------------------
*/

if ($role === "Employee") {

    if (!empty($emp_id)) {

        $stmt = $conn->prepare("
            SELECT
                COUNT(*) AS total,
                COALESCE(SUM(Total_Gross), 0) AS gross,
                COALESCE(SUM(Total_Deductions), 0) AS deductions,
                COALESCE(SUM(Net_Pay), 0) AS net
            FROM payroll
            WHERE Emp_ID = ?
        ");

        if ($stmt) {
            $stmt->bind_param("i", $emp_id);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result) {
                $row = $result->fetch_assoc();

                $my_payroll_count = (int)$row["total"];
                $my_gross_pay = (float)$row["gross"];
                $my_deductions = (float)$row["deductions"];
                $my_net_pay = (float)$row["net"];
            }

            $stmt->close();
        }

        /*
        |--------------------------------------------------------------------------
        | Employee benefits
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM benefits
            WHERE Emp_ID = ?
            AND Status = 'Active'
        ");

        if ($stmt) {
            $stmt->bind_param("i", $emp_id);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result) {
                $row = $result->fetch_assoc();
                $my_active_benefits = (int)$row["total"];
            }

            $stmt->close();
        }

        /*
        |--------------------------------------------------------------------------
        | Employee payroll chart
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT
                DATE_FORMAT(Pay_Date, '%b %Y') AS month_label,
                DATE_FORMAT(Pay_Date, '%Y-%m') AS month_key,
                COALESCE(SUM(Total_Gross), 0) AS gross,
                COALESCE(SUM(Net_Pay), 0) AS net
            FROM payroll
            WHERE Emp_ID = ?
            GROUP BY month_key, month_label
            ORDER BY month_key DESC
            LIMIT 6
        ");

        if ($stmt) {
            $stmt->bind_param("i", $emp_id);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $monthly_payroll[] = $row;
                }
            }

            $stmt->close();
        }

        /*
        |--------------------------------------------------------------------------
        | Employee benefits distribution
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT
                COALESCE(NULLIF(Benefit_Type, ''), 'Other') AS benefit_type,
                COUNT(*) AS total
            FROM benefits
            WHERE Emp_ID = ?
            AND Status = 'Active'
            GROUP BY benefit_type
            ORDER BY total DESC
        ");

        if ($stmt) {
            $stmt->bind_param("i", $emp_id);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $benefit_distribution[] = $row;
                }
            }

            $stmt->close();
        }
    }

} else {

    /*
    |--------------------------------------------------------------------------
    | Company-wide statistics
    |--------------------------------------------------------------------------
    */

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM employee
    ");

    if ($result) {
        $row = $result->fetch_assoc();
        $total_employees = (int)$row["total"];
    }

    /*
    |--------------------------------------------------------------------------
    | Payroll statistics
    |--------------------------------------------------------------------------
    */

    $result = $conn->query("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(Total_Gross), 0) AS gross,
            COALESCE(SUM(Total_Deductions), 0) AS deductions,
            COALESCE(SUM(Net_Pay), 0) AS net
        FROM payroll
    ");

    if ($result) {
        $row = $result->fetch_assoc();

        $total_payroll = (int)$row["total"];
        $total_gross = (float)$row["gross"];
        $total_deductions = (float)$row["deductions"];
        $total_net_pay = (float)$row["net"];
    }

    /*
    |--------------------------------------------------------------------------
    | Active benefits
    |--------------------------------------------------------------------------
    */

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM benefits
        WHERE Status = 'Active'
    ");

    if ($result) {
        $row = $result->fetch_assoc();
        $total_active_benefits = (int)$row["total"];
    }

    /*
    |--------------------------------------------------------------------------
    | Active users
    |--------------------------------------------------------------------------
    */

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM users
        WHERE Status = 'Active'
    ");

    if ($result) {
        $row = $result->fetch_assoc();
        $total_users = (int)$row["total"];
    }

    // Claims & Reimbursement statistics

// Pending Claims
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM claims
    WHERE Status = 'Pending'
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_pending_claims = (int)$row["total"];
}

// Approved Claims awaiting reimbursement
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM claims c
    WHERE c.Status = 'Approved'
    AND NOT EXISTS (
        SELECT 1
        FROM reimbursements r
        WHERE r.Claim_ID = c.Claim_ID
        AND r.Status = 'Reimbursed'
    )
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_approved_claims = (int)$row["total"];
}

// Rejected Claims
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM claims
    WHERE Status = 'Rejected'
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_rejected_claims = (int)$row["total"];
}

// Reimbursed Claims
$result = $conn->query("
    SELECT COUNT(DISTINCT c.Claim_ID) AS total
    FROM claims c
    INNER JOIN reimbursements r
        ON r.Claim_ID = c.Claim_ID
    WHERE r.Status = 'Reimbursed'
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_reimbursed_claims = (int)$row["total"];
}

// Total Claimed Amount
$result = $conn->query("
    SELECT COALESCE(SUM(Total_Amount), 0) AS total
    FROM claims
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_claimed_amount = (float)$row["total"];
}

// Total Reimbursed Amount
$result = $conn->query("
    SELECT COALESCE(SUM(Total_Amount), 0) AS total
    FROM reimbursements
    WHERE Status = 'Reimbursed'
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_reimbursed_amount = (float)$row["total"];
}

// Pending Reimbursements
$total_pending_reimbursements = $total_approved_claims;

    /*
    |--------------------------------------------------------------------------
    | Payroll overview - last 6 months with records
    |--------------------------------------------------------------------------
    */

    $result = $conn->query("
        SELECT
            DATE_FORMAT(Pay_Date, '%b %Y') AS month_label,
            DATE_FORMAT(Pay_Date, '%Y-%m') AS month_key,
            COALESCE(SUM(Total_Gross), 0) AS gross,
            COALESCE(SUM(Net_Pay), 0) AS net
        FROM payroll
        GROUP BY month_key, month_label
        ORDER BY month_key DESC
        LIMIT 6
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $monthly_payroll[] = $row;
        }
    }

    $monthly_payroll = array_reverse($monthly_payroll);

    /*
    |--------------------------------------------------------------------------
    | Benefits distribution
    |--------------------------------------------------------------------------
    */

    $result = $conn->query("
        SELECT
            COALESCE(NULLIF(Benefit_Type, ''), 'Other') AS benefit_type,
            COUNT(*) AS total
        FROM benefits
        WHERE Status = 'Active'
        GROUP BY benefit_type
        ORDER BY total DESC
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $benefit_distribution[] = $row;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Chart data
|--------------------------------------------------------------------------
*/

$payroll_chart_labels = [];
$payroll_chart_gross = [];
$payroll_chart_net = [];

foreach ($monthly_payroll as $row) {

    $payroll_chart_labels[] = $row["month_label"];

    $payroll_chart_gross[] = (float)$row["gross"];

    $payroll_chart_net[] = (float)$row["net"];
}


$benefit_chart_labels = [];
$benefit_chart_values = [];

foreach ($benefit_distribution as $row) {

    $benefit_chart_labels[] = $row["benefit_type"];

    $benefit_chart_values[] = (int)$row["total"];
}


$benefit_total = 0;

foreach ($benefit_distribution as $row) {
    $benefit_total += (int)$row["total"];
}

/*
|--------------------------------------------------------------------------
| Helper for safe output
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

/*
|--------------------------------------------------------------------------
| Inline SVG icons
|--------------------------------------------------------------------------
*/

function icon($name)
{
    $icons = [

        "grid" => '<svg viewBox="0 0 24 24" aria-hidden="true">
            <rect x="3" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/>
            <rect x="14" y="14" width="7" height="7" rx="1"/>
        </svg>',

        "users" => '<svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/>
            <circle cx="9.5" cy="7" r="4"/>
            <path d="M17 11a4 4 0 1 0 0-8"/>
            <path d="M21 21v-2a4 4 0 0 0-3-3.87"/>
        </svg>',

        "wallet" => '<svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M3 7h17a1 1 0 0 1 1 1v11a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h15"/>
            <path d="M16 13h5"/>
            <circle cx="16" cy="13" r="1"/>
        </svg>',

        "file" => '<svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <path d="M14 2v6h6M8 13h8M8 17h6"/>
        </svg>',

        "gift" => '<svg viewBox="0 0 24 24" aria-hidden="true">
            <rect x="3" y="8" width="18" height="13" rx="2"/>
            <path d="M12 8v13M3 12h18"/>
            <path d="M12 8H7.5a2.5 2.5 0 1 1 2.5-2.5C10 7 12 8 12 8z"/>
            <path d="M12 8h4.5A2.5 2.5 0 1 0 14 5.5C14 7 12 8 12 8z"/>
        </svg>',

        "user" => '<svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="8" r="4"/>
            <path d="M4 21a8 8 0 0 1 16 0"/>
        </svg>',

        "shield" => '<svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 3l8 3v6c0 5-3.4 8.7-8 10-4.6-1.3-8-5-8-10V6z"/>
            <path d="m9 12 2 2 4-4"/>
        </svg>',

        "database" => '<svg viewBox="0 0 24 24" aria-hidden="true">
            <ellipse cx="12" cy="5" rx="8" ry="3"/>
            <path d="M4 5v7c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/>
            <path d="M4 12v7c0 1.7 3.6 3 8 3s8-1.3 8-3v-7"/>
        </svg>',

        "logout" => '<svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M10 17l5-5-5-5M15 12H3"/>
            <path d="M13 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/>
        </svg>',

        "chart" => '<svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M4 19V5M4 19h17"/>
            <path d="m7 15 4-4 3 2 5-6"/>
        </svg>',

        "calendar" => '<svg viewBox="0 0 24 24" aria-hidden="true">
            <rect x="3" y="4" width="18" height="17" rx="2"/>
            <path d="M16 2v4M8 2v4M3 9h18"/>
        </svg>',

        "menu" => '<svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M4 6h16M4 12h16M4 18h16"/>
        </svg>',

        "activity" => '<svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M3 12h4l2-6 4 12 2-6h6"/>
        </svg>',

        "lock" => '<svg viewBox="0 0 24 24" aria-hidden="true">
            <rect x="4" y="10" width="16" height="11" rx="2"/>
            <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
        </svg>'

    ];

    return $icons[$name] ?? "";
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

    <title>Payroll &amp; Benefits Dashboard</title>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>

        :root {
            --black: #090B0D;
            --sidebar: #111418;
            --sidebar-2: #15191E;
            --card: #171B20;
            --card-hover: #1C2127;
            --border: #2A3037;

            --gold: #C9A66B;
            --gold-soft: #D8C29A;

            --burgundy: #6E3045;
            --burgundy-soft: #9B7187;

            --green: #71998B;
            --blue: #7088A8;
            --rose: #A9798D;
            --beige: #C9BBA5;

            --text: #F5F3EF;
            --muted: #A8AAA7;
            --danger: #B86D78;

            --radius: 16px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family:
                Inter,
                "Segoe UI",
                Arial,
                sans-serif;
            background: var(--black);
            color: var(--text);
        }

        a {
            color: inherit;
        }

        .app {
            min-height: 100vh;
            display: flex;
        }

        /* SIDEBAR */

        .sidebar {
            width: 255px;
            min-width: 255px;
            background:
                linear-gradient(
                    180deg,
                    #121519 0%,
                    #0D1013 100%
                );
            border-right: 1px solid var(--border);
            padding: 26px 18px;
            display: flex;
            flex-direction: column;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 2px 10px 28px;
        }

        .brand-icon {
            width: 43px;
            height: 43px;
            border-radius: 13px;
            background: rgba(201,166,107,.12);
            color: var(--gold);
            display: grid;
            place-items: center;
            border: 1px solid rgba(201,166,107,.22);
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

        .brand strong {
            display: block;
            font-size: 16px;
            line-height: 1.15;
        }

        .brand span {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 12px;
        }

        .nav {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 13px;
            min-height: 47px;
            padding: 0 13px;
            border-radius: 11px;
            text-decoration: none;
            color: #C8CAC7;
            font-size: 13px;
            transition:
                background .2s ease,
                color .2s ease,
                transform .2s ease;
        }

        .nav-link:hover {
            background: #1B2026;
            color: white;
            transform: translateX(2px);
        }

        .nav-link.active {
            background:
                linear-gradient(
                    135deg,
                    #6E3045,
                    #542637
                );
            color: white;
            box-shadow:
                0 8px 22px rgba(110,48,69,.18);
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            display: grid;
            place-items: center;
            flex: 0 0 20px;
        }

        .nav-icon svg {
            width: 18px;
            height: 18px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .sidebar-spacer {
            flex: 1;
        }

        .nav-divider {
            height: 1px;
            background: var(--border);
            margin: 15px 9px;
        }

        .logout-link {
            color: #C8AAA9;
        }

        /* MAIN */

        .main {
            flex: 1;
            min-width: 0;
        }

        .topbar {
            min-height: 91px;
            padding: 0 34px;
            border-bottom: 1px solid var(--border);
            background: #0D1013;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .mobile-menu {
            display: none;
            border: 0;
            background: transparent;
            color: var(--text);
            cursor: pointer;
        }

        .mobile-menu svg {
            width: 23px;
            height: 23px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
        }

        .greeting {
            color: var(--muted);
            font-size: 12px;
        }

        .greeting strong {
            display: block;
            margin-top: 2px;
            color: var(--text);
            font-size: 18px;
        }

        .system-label {
            margin-top: 2px;
            color: #858A87;
            font-size: 11px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 23px;
        }

        .date-box {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-right: 22px;
            border-right: 1px solid var(--border);
        }

        .date-icon {
            color: var(--gold);
        }

        .date-icon svg {
            width: 22px;
            height: 22px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .date-box strong {
            display: block;
            font-size: 12px;
            font-weight: 500;
        }

        .date-box span {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 11px;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-avatar {
            width: 39px;
            height: 39px;
            border-radius: 50%;
            background:
                linear-gradient(
                    135deg,
                    #6E3045,
                    #C9A66B
                );
            display: grid;
            place-items: center;
            color: white;
        }

        .profile-avatar svg {
            width: 21px;
            height: 21px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .profile-name {
            font-size: 12px;
        }

        .profile-role {
            margin-top: 2px;
            color: var(--muted);
            font-size: 10px;
        }

        /* CONTENT */

        .content {
            padding: 29px 34px 38px;
            max-width: 1550px;
            margin: 0 auto;
        }

        .hero {
            min-height: 218px;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 19px;
            background:
                radial-gradient(
                    circle at 85% 45%,
                    rgba(110,48,69,.26),
                    transparent 33%
                ),
                linear-gradient(
                    115deg,
                    #14181D,
                    #101317
                );
            padding: 39px 42px;
            margin-bottom: 22px;
        }

        .hero-copy {
            position: relative;
            z-index: 3;
            max-width: 610px;
        }

        .hero-kicker {
            color: var(--gold-soft);
            text-transform: uppercase;
            letter-spacing: 1.8px;
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .hero h1 {
            margin: 0;
            font-family:
                Georgia,
                "Times New Roman",
                serif;
            font-size: clamp(30px, 4vw, 48px);
            font-weight: 400;
            letter-spacing: -.7px;
        }

        .hero h1 span {
            color: var(--gold-soft);
        }

        .hero p {
            margin: 12px 0 0;
            color: #BFC1BD;
            font-size: 14px;
            line-height: 1.6;
        }

        /* HERO ART */

        .hero-art {
            position: absolute;
            right: 2%;
            bottom: 0;
            width: 43%;
            height: 100%;
            opacity: .9;
        }

        .hero-orbit {
            position: absolute;
            width: 440px;
            height: 220px;
            right: 5%;
            bottom: -80px;
            border-radius: 50%;
            background:
                radial-gradient(
                    ellipse,
                    rgba(110,48,69,.34),
                    rgba(110,48,69,.08) 50%,
                    transparent 71%
                );
        }

        .hero-window {
            position: absolute;
            width: 190px;
            height: 110px;
            right: 31%;
            top: 39px;
            border-radius: 9px;
            background: #1E2329;
            border: 1px solid #343B43;
            box-shadow: 0 18px 35px rgba(0,0,0,.3);
        }

        .hero-window-top {
            height: 23px;
            border-bottom: 1px solid #343B43;
            display: flex;
            gap: 5px;
            align-items: center;
            padding-left: 10px;
        }

        .hero-window-top i {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #73777A;
        }

        .hero-bars {
            height: 70px;
            display: flex;
            align-items: end;
            gap: 9px;
            padding: 13px 18px 10px;
        }

        .hero-bars i {
            display: block;
            width: 18px;
            border-radius: 4px 4px 0 0;
            background:
                linear-gradient(
                    180deg,
                    var(--gold-soft),
                    #725D40
                );
        }

        .hero-bars i:nth-child(1) { height: 25px; }
        .hero-bars i:nth-child(2) { height: 40px; }
        .hero-bars i:nth-child(3) { height: 31px; }
        .hero-bars i:nth-child(4) { height: 56px; }
        .hero-bars i:nth-child(5) { height: 65px; }

        .person {
            position: absolute;
            right: 7%;
            bottom: 0;
            width: 185px;
            height: 177px;
        }

        .person-head {
            position: absolute;
            right: 52px;
            top: 7px;
            width: 55px;
            height: 63px;
            border-radius: 45% 45% 47% 47%;
            background: #D8B09A;
            z-index: 2;
        }

        .person-hair {
            position: absolute;
            right: 42px;
            top: 0;
            width: 76px;
            height: 78px;
            border-radius: 48% 48% 45% 38%;
            background: #17191C;
            z-index: 3;
        }

        .person-body {
            position: absolute;
            right: 22px;
            bottom: 0;
            width: 135px;
            height: 116px;
            border-radius: 62px 62px 0 0;
            background:
                linear-gradient(
                    140deg,
                    #6E3045,
                    #3A1F2A
                );
        }

        .person-arm {
            position: absolute;
            right: 8px;
            bottom: 20px;
            width: 105px;
            height: 20px;
            border-radius: 30px;
            transform: rotate(12deg);
            background: #C99783;
            z-index: 4;
        }

        .laptop {
            position: absolute;
            right: 22px;
            bottom: 10px;
            width: 150px;
            height: 68px;
            z-index: 5;
        }

        .laptop-screen {
            width: 100px;
            height: 58px;
            margin: 0 auto;
            border: 7px solid #242A30;
            border-bottom-width: 9px;
            border-radius: 5px;
            background: #0E1114;
            transform: perspective(250px) rotateX(-2deg);
        }

        .laptop-screen:after {
            content: "";
            display: block;
            width: 35px;
            height: 3px;
            background: var(--gold);
            margin: 22px auto;
            opacity: .65;
        }

        .laptop-base {
            height: 9px;
            width: 150px;
            background: #343A40;
            border-radius: 50%;
            margin-top: -1px;
        }

        /* STAT CARDS */

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 13px;
            margin-bottom: 20px;
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            min-height: 123px;
            padding: 20px;
            background:
                linear-gradient(
                    145deg,
                    #181C21,
                    #14181C
                );
            border: 1px solid var(--border);
            border-radius: var(--radius);
            transition:
                transform .2s ease,
                border-color .2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            border-color: #3A4149;
        }

        .stat-card:before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--accent);
        }

        .stat-top {
            display: flex;
            align-items: flex-start;
            gap: 13px;
        }

        .stat-icon {
            width: 43px;
            height: 43px;
            min-width: 43px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: var(--accent);
            background: color-mix(
                in srgb,
                var(--accent) 18%,
                transparent
            );
            border: 1px solid color-mix(
                in srgb,
                var(--accent) 30%,
                transparent
            );
        }

        .stat-icon svg {
            width: 21px;
            height: 21px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .stat-label {
            color: #B5B8B5;
            font-size: 11px;
            line-height: 1.3;
        }

        .stat-number {
            margin-top: 5px;
            color: #F5F3EF;
            font-size: 21px;
            font-weight: 650;
            letter-spacing: -.3px;
        }

        .stat-sub {
            margin-top: 12px;
            color: #767B79;
            font-size: 10px;
        }

        /* PANELS */

        .panel-grid {
            display: grid;
            grid-template-columns: 1.35fr 1fr .85fr;
            gap: 15px;
        }

        .panel {
            min-width: 0;
            background: #14181D;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .panel-header {
            padding: 19px 20px 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .panel-header h2 {
            margin: 0;
            font-size: 14px;
            font-weight: 650;
        }

        .panel-header span {
            color: var(--muted);
            font-size: 10px;
        }

        /* FUNCTIONAL PAYROLL CHART */

        .chart-container {
            position: relative;
            width: 100%;
            height: 270px;
            padding: 10px 18px 20px;
        }

        .chart-container canvas {
            width: 100% !important;
            height: 100% !important;
        }

        /* FUNCTIONAL BENEFITS CHART */

        .benefit-chart-container {
            position: relative;
            width: 100%;
            height: 245px;
            padding: 12px 30px 0;
        }

        .benefit-chart-container canvas {
            width: 100% !important;
            height: 100% !important;
        }

        .benefit-total-label {
            text-align: center;
            margin-top: -76px;
            margin-bottom: 45px;
            pointer-events: none;
        }

        .benefit-total-label strong {
            display: block;
            font-size: 27px;
            font-weight: 650;
            color: var(--text);
        }

        .benefit-total-label span {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 9px;
        }

        .empty-state {
            padding: 35px 10px;
            text-align: center;
            color: #777C7A;
            font-size: 11px;
        }

        /* ACTIVITY */

        .activity-list {
            padding: 5px 20px 18px;
        }

        .activity {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 11px 0;
            border-bottom: 1px solid #242A30;
        }

        .activity:last-child {
            border-bottom: 0;
        }

        .activity-icon {
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: #252B31;
            color: var(--gold);
        }

        .activity-icon svg {
            width: 15px;
            height: 15px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .activity-text {
            min-width: 0;
        }

        .activity-text strong {
            display: block;
            color: #D6D8D5;
            font-size: 10px;
            font-weight: 550;
            line-height: 1.4;
        }

        .activity-text span {
            display: block;
            margin-top: 2px;
            color: #747976;
            font-size: 9px;
        }

        /* FOOTER MESSAGE */

        .security-message {
            margin-top: 15px;
            min-height: 70px;
            padding: 0 23px;
            display: flex;
            align-items: center;
            gap: 14px;
            background:
                linear-gradient(
                    90deg,
                    #15191E,
                    #111519
                );
            border: 1px solid var(--border);
            border-radius: var(--radius);
        }

        .security-message-icon {
            width: 37px;
            height: 37px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            color: var(--green);
            background: rgba(113,153,139,.1);
        }

        .security-message-icon svg {
            width: 19px;
            height: 19px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .security-message strong {
            display: block;
            font-family: Georgia, serif;
            font-size: 12px;
            font-weight: 400;
        }

        .security-message span {
            display: block;
            margin-top: 3px;
            color: #777D79;
            font-size: 9px;
        }

        /* MOBILE */

        @media (max-width: 1180px) {

            .stat-grid {
                grid-template-columns: 1fr;
            }

            .panel-grid {
                grid-template-columns: 1fr 1fr;
            }

            .panel:last-child {
                grid-column: 1 / -1;
            }

            .hero-art {
                opacity: .45;
                width: 55%;
            }
        }

        @media (max-width: 850px) {

            .sidebar {
                position: fixed;
                z-index: 50;
                left: -270px;
                top: 0;
                bottom: 0;
                transition: left .25s ease;
            }

            .sidebar.open {
                left: 0;
            }

            .mobile-menu {
                display: block;
            }

            .topbar {
                padding: 0 18px;
            }

            .date-box {
                display: none;
            }

            .profile-name,
            .profile-role {
                display: none;
            }

            .content {
                padding: 20px 15px 30px;
            }

            .hero {
                padding: 29px 25px;
                min-height: 240px;
            }

            .hero-art {
                width: 100%;
                right: -20%;
                opacity: .25;
            }

            .panel-grid {
                grid-template-columns: 1fr;
            }

            .panel:last-child {
                grid-column: auto;
            }

            .chart-container {
                height: 250px;
            }

            .benefit-chart-container {
                height: 230px;
            }
        }

        @media (max-width: 560px) {

            .stat-grid {
                grid-template-columns: 1fr;
            }

            .hero h1 {
                font-size: 31px;
            }

            .hero p {
                font-size: 12px;
            }

            .stat-card {
                min-height: 112px;
            }

            .chart-container {
                height: 230px;
                padding-left: 10px;
                padding-right: 10px;
            }

            .benefit-chart-container {
                height: 220px;
                padding-left: 15px;
                padding-right: 15px;
            }
        }

    </style>

</head>

<body>

<div class="app">

    <!-- SIDEBAR -->

    <aside class="sidebar" id="sidebar">

        <div class="brand">

            <div class="brand-icon">
                <?php echo icon("users"); ?>
            </div>

            <div>
                <strong>Payroll &amp; Benefits</strong>
                <span>Management System</span>
            </div>

        </div>

        <nav class="nav">

            <a href="dashboard.php" class="nav-link active">
                <span class="nav-icon">
                    <?php echo icon("grid"); ?>
                </span>
                <span>Dashboard</span>
            </a>

            <?php if (
                $role === "Administrator" ||
                $role === "HR Staff"
            ): ?>

                <a href="employee_management.php" class="nav-link">
                    <span class="nav-icon">
                        <?php echo icon("users"); ?>
                    </span>
                    <span>Employee Management</span>
                </a>

            <?php endif; ?>

            <?php if (
                $role === "Administrator" ||
                $role === "HR Staff" ||
                $role === "Payroll Staff"
            ): ?>

                <a href="payroll.php" class="nav-link">
                    <span class="nav-icon">
                        <?php echo icon("wallet"); ?>
                    </span>
                    <span>Payroll</span>
                </a>

            <?php endif; ?>

            <?php if (
                $role === "Administrator" ||
                $role === "Payroll Staff"
            ): ?>

                <a href="payroll_records.php" class="nav-link">
                    <span class="nav-icon">
                        <?php echo icon("file"); ?>
                    </span>
                    <span>Payroll Records</span>
                </a>

                <a href="claims.php" class="nav-link">
                    <span class="nav-icon">
                        <?php echo icon("file"); ?>
                    </span>
                    <span>Claims</span>
                </a>

                <?php if (in_array($role, ["Administrator", "Payroll Staff"], true)): ?>

                <a href="reimbursements.php" class="nav-link">
                    <span class="nav-icon">
                        <?php echo icon("file"); ?>
                    </span>
                    <span>Reimbursement</span>
                </a>

                <a href="approvers.php" class="nav-link">
                    <span class="nav-icon">
                        <?php echo icon("users"); ?>
                    </span>
                    <span>Approver Management</span>
                </a>
                <a href="benefits_management.php" class="nav-link">
                    <span class="nav-icon">
                        <?php echo icon("gift"); ?>
                    </span>
                    <span>Benefits</span>
                </a>

            <?php endif; ?>
            
            <?php endif; ?>

            <?php if (
                $role === "Administrator" ||
                $role === "HR Staff"
            ): ?>

            <?php endif; ?>

            <?php if ($role === "Administrator"): ?>

                <a href="user_management.php" class="nav-link">
                    <span class="nav-icon">
                        <?php echo icon("user"); ?>
                    </span>
                    <span>User Management</span>
                </a>

                <a href="audit_logs.php" class="nav-link">
                    <span class="nav-icon">
                        <?php echo icon("activity"); ?>
                    </span>
                    <span>Audit Logs</span>
                </a>

                <a href="backup_database.php" class="nav-link">
                    <span class="nav-icon">
                        <?php echo icon("database"); ?>
                    </span>
                    <span>Database Backup</span>
                </a>

            <?php endif; ?>

            <?php if ($role === "Employee"): ?>

                <a href="employee_payroll.php" class="nav-link">
                    <span class="nav-icon">
                        <?php echo icon("file"); ?>
                    </span>
                    <span>My Payslips</span>
                </a>
                
                <a href="my_benefits.php" class="nav-link">
                    <span class="nav-icon">
                        <?php echo icon("gift"); ?>
                    </span>
                    <span>My Benefits</span>
                </a>

                <a href="my_payroll_history.php" class="nav-link">
                    <span class="nav-icon">
                        <?php echo icon("chart"); ?>
                    </span>
                    <span>My Payroll History</span>
                </a>

            <?php endif; ?>

        </nav>

        <div class="sidebar-spacer"></div>

        <div class="nav-divider"></div>

        <a href="logout.php" class="nav-link logout-link">
            <span class="nav-icon">
                <?php echo icon("logout"); ?>
            </span>
            <span>Logout</span>
        </a>

    </aside>


    <!-- MAIN -->

    <main class="main">

        <!-- TOPBAR -->

        <header class="topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="mobile-menu"
                    id="mobileMenu"
                    aria-label="Open menu"
                >
                    <?php echo icon("menu"); ?>
                </button>

                <div>

                    <div class="greeting">
                        Welcome back,
                        <strong><?php echo e($full_name); ?></strong>
                    </div>

                    <div class="system-label">
                        Payroll and Benefits System
                    </div>

                </div>

            </div>


            <div class="topbar-right">

                <div class="date-box">

                    <div class="date-icon">
                        <?php echo icon("calendar"); ?>
                    </div>

                    <div>

                        <strong id="liveDate">
                            Loading date...
                        </strong>

                        <span id="liveTime">
                            Loading time...
                        </span>

                    </div>

                </div>


                <div class="profile">

                    <div class="profile-avatar">
                        <?php echo icon("user"); ?>
                    </div>

                    <div>

                        <div class="profile-name">
                            <?php echo e($full_name); ?>
                        </div>

                        <div class="profile-role">
                            <?php echo e($role); ?>
                        </div>

                    </div>

                </div>

            </div>

        </header>


        <section class="content">

            <!-- HERO -->

            <div class="hero">

                <div class="hero-copy">

                    <div class="hero-kicker">
                        Payroll &amp; Benefits
                    </div>

                    <h1>
                        Welcome Back,
                        <span><?php echo e($full_name); ?></span>
                    </h1>

                    <p>
                        Here is an overview of your payroll and benefits
                        system. Manage records, employees, benefits, and
                        payroll information from one secure workspace.
                    </p>

                </div>


                <div class="hero-art" aria-hidden="true">

                    <div class="hero-orbit"></div>

                    <div class="hero-window">

                        <div class="hero-window-top">
                            <i></i>
                            <i></i>
                            <i></i>
                        </div>

                        <div class="hero-bars">
                            <i></i>
                            <i></i>
                            <i></i>
                            <i></i>
                            <i></i>
                        </div>

                    </div>


                    <div class="person">

                        <div class="person-head"></div>
                        <div class="person-hair"></div>
                        <div class="person-body"></div>
                        <div class="person-arm"></div>

                        <div class="laptop">
                            <div class="laptop-screen"></div>
                            <div class="laptop-base"></div>
                        </div>

                    </div>

                </div>

            </div>


            <!-- STATISTICS -->

            <div class="stat-grid">

                <?php if ($role === "Employee"): ?>

                    <div
                        class="stat-card"
                        style="--accent: var(--burgundy-soft);"
                    >

                        <div class="stat-top">

                            <div class="stat-icon">
                                <?php echo icon("file"); ?>
                            </div>

                            <div>

                                <div class="stat-label">
                                    My Payroll Records
                                </div>

                                <div class="stat-number">
                                    <?php
                                    echo number_format(
                                        $my_payroll_count
                                    );
                                    ?>
                                </div>

                            </div>

                        </div>

                        <div class="stat-sub">
                            Your available payroll records
                        </div>

                    </div>


                    <div
                        class="stat-card"
                        style="--accent: var(--gold);"
                    >

                        <div class="stat-top">

                            <div class="stat-icon">
                                <?php echo icon("wallet"); ?>
                            </div>

                            <div>

                                <div class="stat-label">
                                    My Gross Pay
                                </div>

                                <div class="stat-number">
                                    ₱<?php
                                    echo number_format(
                                        $my_gross_pay,
                                        2
                                    );
                                    ?>
                                </div>

                            </div>

                        </div>

                        <div class="stat-sub">
                            Total recorded gross pay
                        </div>

                    </div>


                    <div
                        class="stat-card"
                        style="--accent: var(--green);"
                    >

                        <div class="stat-top">

                            <div class="stat-icon">
                                <?php echo icon("wallet"); ?>
                            </div>

                            <div>

                                <div class="stat-label">
                                    My Net Pay
                                </div>

                                <div class="stat-number">
                                    ₱<?php
                                    echo number_format(
                                        $my_net_pay,
                                        2
                                    );
                                    ?>
                                </div>

                            </div>

                        </div>

                        <div class="stat-sub">
                            Total recorded net pay
                        </div>

                    </div>


                    <div
                        class="stat-card"
                        style="--accent: var(--blue);"
                    >

                        <div class="stat-top">

                            <div class="stat-icon">
                                <?php echo icon("gift"); ?>
                            </div>

                            <div>

                                <div class="stat-label">
                                    Active Benefits
                                </div>

                                <div class="stat-number">
                                    <?php
                                    echo number_format(
                                        $my_active_benefits
                                    );
                                    ?>
                                </div>

                            </div>

                        </div>

                        <div class="stat-sub">
                            Benefits currently assigned to you
                        </div>

                    </div>

                <?php else: ?>

                    <div
                        class="stat-card"
                        style="--accent: var(--burgundy-soft);"
                    >

                        <div class="stat-top">

                            <div class="stat-icon">
                                <?php echo icon("users"); ?>
                            </div>

                            <div>

                                <div class="stat-label">
                                    Total Employees
                                </div>

                                <div class="stat-number">
                                    <?php
                                    echo number_format(
                                        $total_employees
                                    );
                                    ?>
                                </div>

                            </div>

                        </div>

                        <div class="stat-sub">
                            Employees registered in the system
                        </div>

                    </div>


                    <div
                        class="stat-card"
                        style="--accent: var(--blue);"
                    >

                        <div class="stat-top">

                            <div class="stat-icon">
                                <?php echo icon("file"); ?>
                            </div>

                            <div>

                                <div class="stat-label">
                                    Payroll Records
                                </div>

                                <div class="stat-number">
                                    <?php
                                    echo number_format(
                                        $total_payroll
                                    );
                                    ?>
                                </div>

                            </div>

                        </div>

                        <div class="stat-sub">
                            Payroll records stored
                        </div>

                    </div>


                    <div
                        class="stat-card"
                        style="--accent: var(--gold);"
                    >

                        <div class="stat-top">

                            <div class="stat-icon">
                                <?php echo icon("wallet"); ?>
                            </div>

                            <div>

                                <div class="stat-label">
                                    Total Gross Pay
                                </div>

                                <div class="stat-number">
                                    ₱<?php
                                    echo number_format(
                                        $total_gross,
                                        2
                                    );
                                    ?>
                                </div>

                            </div>

                        </div>

                        <div class="stat-sub">
                            Total recorded gross payroll
                        </div>

                    </div>


                    <div
                        class="stat-card"
                        style="--accent: var(--rose);"
                    >

                        <div class="stat-top">

                            <div class="stat-icon">
                                <?php echo icon("file"); ?>
                            </div>

                            <div>

                                <div class="stat-label">
                                    Total Deductions
                                </div>

                                <div class="stat-number">
                                    ₱<?php
                                    echo number_format(
                                        $total_deductions,
                                        2
                                    );
                                    ?>
                                </div>

                            </div>

                        </div>

                        <div class="stat-sub">
                            Total recorded deductions
                        </div>

                    </div>


                    <div
                        class="stat-card"
                        style="--accent: var(--green);"
                    >

                        <div class="stat-top">

                            <div class="stat-icon">
                                <?php echo icon("wallet"); ?>
                            </div>

                            <div>

                                <div class="stat-label">
                                    Total Net Pay
                                </div>

                                <div class="stat-number">
                                    ₱<?php
                                    echo number_format(
                                        $total_net_pay,
                                        2
                                    );
                                    ?>
                                </div>

                            </div>

                        </div>

                        <div class="stat-sub">
                            Total recorded net payroll
                        </div>

                    </div>


                    <div
                        class="stat-card"
                        style="--accent: var(--burgundy-soft);"
                    >

                        <div class="stat-top">

                            <div class="stat-icon">
                                <?php echo icon("gift"); ?>
                            </div>

                            <div>

                                <div class="stat-label">
                                    Active Benefits
                                </div>

                                <div class="stat-number">
                                    <?php
                                    echo number_format(
                                        $total_active_benefits
                                    );
                                    ?>
                                </div>

                            </div>

                        </div>

                        <div class="stat-sub">
                            Currently active benefits
                        </div>

                    </div>


                    <!-- PENDING CLAIMS -->

<div
    class="stat-card"
    style="--accent: var(--gold);"
>

    <div class="stat-top">

        <div class="stat-icon">
            <?php echo icon("file"); ?>
        </div>

        <div>
            <div class="stat-label">
                Pending Claims
            </div>

            <div class="stat-number">
                <?php
                echo number_format(
                    $total_pending_claims
                );
                ?>
            </div>
        </div>

    </div>

    <div class="stat-sub">
        Claims waiting for review
    </div>

</div>


<!-- APPROVED CLAIMS -->

<div
    class="stat-card"
    style="--accent: var(--green);"
>

    <div class="stat-top">

        <div class="stat-icon">
            <?php echo icon("file"); ?>
        </div>

        <div>
            <div class="stat-label">
                Approved Claims
            </div>

            <div class="stat-number">
                <?php
                echo number_format(
                    $total_approved_claims
                );
                ?>
            </div>
        </div>

    </div>

    <div class="stat-sub">
        Awaiting reimbursement
    </div>

</div>


<!-- REJECTED CLAIMS -->

<div
    class="stat-card"
    style="--accent: var(--danger);"
>

    <div class="stat-top">

        <div class="stat-icon">
            <?php echo icon("file"); ?>
        </div>

        <div>
            <div class="stat-label">
                Rejected Claims
            </div>

            <div class="stat-number">
                <?php
                echo number_format(
                    $total_rejected_claims
                );
                ?>
            </div>
        </div>

    </div>

    <div class="stat-sub">
        Claims that were rejected
    </div>

</div>


<!-- REIMBURSED CLAIMS -->

<div
    class="stat-card"
    style="--accent: var(--blue);"
>

    <div class="stat-top">

        <div class="stat-icon">
            <?php echo icon("wallet"); ?>
        </div>

        <div>
            <div class="stat-label">
                Reimbursed Claims
            </div>

            <div class="stat-number">
                <?php
                echo number_format(
                    $total_reimbursed_claims
                );
                ?>
            </div>
        </div>

    </div>

    <div class="stat-sub">
        Claims already reimbursed
    </div>

</div>


<!-- TOTAL CLAIMED -->

<div
    class="stat-card"
    style="--accent: var(--rose);"
>

    <div class="stat-top">

        <div class="stat-icon">
            <?php echo icon("file"); ?>
        </div>

        <div>
            <div class="stat-label">
                Total Claimed
            </div>

            <div class="stat-number">
                ₱<?php
                echo number_format(
                    $total_claimed_amount,
                    2
                );
                ?>
            </div>
        </div>

    </div>

    <div class="stat-sub">
        Total amount submitted in claims
    </div>

</div>


<!-- TOTAL REIMBURSED -->

<div
    class="stat-card"
    style="--accent: var(--gold);"
>

    <div class="stat-top">

        <div class="stat-icon">
            <?php echo icon("wallet"); ?>
        </div>

        <div>
            <div class="stat-label">
                Total Reimbursed
            </div>

            <div class="stat-number">
                ₱<?php
                echo number_format(
                    $total_reimbursed_amount,
                    2
                );
                ?>
            </div>
        </div>

    </div>

    <div class="stat-sub">
        Total amount reimbursed
    </div>

</div>


<!-- PENDING REIMBURSEMENTS -->

<div
    class="stat-card"
    style="--accent: var(--beige);"
>

    <div class="stat-top">

        <div class="stat-icon">
            <?php echo icon("file"); ?>
        </div>

        <div>
            <div class="stat-label">
                Pending Reimbursements
            </div>

            <div class="stat-number">
                <?php
                echo number_format(
                    $total_pending_reimbursements
                );
                ?>
            </div>
        </div>

    </div>

    <div class="stat-sub">
        Approved claims awaiting payment
    </div>

</div>


<!-- ACTIVE USERS -->

<div
    class="stat-card"
    style="--accent: var(--blue);"
>

    <div class="stat-top">

        <div class="stat-icon">
            <?php echo icon("user"); ?>
        </div>

        <div>
            <div class="stat-label">
                Active Users
            </div>

            <div class="stat-number">
                <?php
                echo number_format(
                    $total_users
                );
                ?>
            </div>
        </div>

    </div>

    <div class="stat-sub">
        Active system accounts
    </div>

</div>
                <?php endif; ?>

            </div>


            <!-- PANELS -->

            <div class="panel-grid">

                <!-- FUNCTIONAL PAYROLL OVERVIEW -->

                <section class="panel">

                    <div class="panel-header">

                        <h2>
                            Payroll Overview
                        </h2>

                        <span>
                            Recorded payroll
                        </span>

                    </div>

                    <?php if (!empty($monthly_payroll)): ?>

                        <div class="chart-container">

                            <canvas
                                id="payrollChart"
                            ></canvas>

                        </div>

                    <?php else: ?>

                        <div class="empty-state">
                            No payroll records are available yet.
                        </div>

                    <?php endif; ?>

                </section>


                <!-- FUNCTIONAL BENEFITS DISTRIBUTION -->

                <section class="panel">

                    <div class="panel-header">

                        <h2>
                            Benefits Distribution
                        </h2>

                        <span>
                            Active benefits
                        </span>

                    </div>

                    <?php if ($benefit_total > 0): ?>

                        <div class="benefit-chart-container">

                            <canvas
                                id="benefitChart"
                            ></canvas>

                        </div>

                        <div class="benefit-total-label">

                            <strong>
                                <?php
                                echo number_format(
                                    $benefit_total
                                );
                                ?>
                            </strong>

                            <span>
                                Active Benefits
                            </span>

                        </div>

                    <?php else: ?>

                        <div class="empty-state">
                            No active benefits are available yet.
                        </div>

                    <?php endif; ?>

                </section>


                <!-- QUICK ACCESS -->

                <section class="panel">

                    <div class="panel-header">

                        <h2>
                            Quick Access
                        </h2>

                        <span>
                            Available actions
                        </span>

                    </div>


                    <div class="activity-list">

                        <?php if (
                            $role === "Administrator" ||
                            $role === "HR Staff" ||
                            $role === "Payroll Staff"
                        ): ?>

                            <div class="activity">

                                <div class="activity-icon">
                                    <?php echo icon("wallet"); ?>
                                </div>

                                <div class="activity-text">

                                    <strong>
                                        Create Payroll
                                    </strong>

                                    <span>
                                        Enter a new payroll record
                                    </span>

                                </div>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            $role === "Administrator" ||
                            $role === "HR Staff"
                        ): ?>

                            <div class="activity">

                                <div class="activity-icon">
                                    <?php echo icon("users"); ?>
                                </div>

                                <div class="activity-text">

                                    <strong>
                                        Employee Management
                                    </strong>

                                    <span>
                                        Manage employee records
                                    </span>

                                </div>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            $role === "Administrator" ||
                            $role === "Payroll Staff"
                        ): ?>

                            <div class="activity">

                                <div class="activity-icon">
                                    <?php echo icon("file"); ?>
                                </div>

                                <div class="activity-text">

                                    <strong>
                                        Payroll Records
                                    </strong>

                                    <span>
                                        Review payroll information
                                    </span>

                                </div>

                            </div>

                        <?php endif; ?>


                        <?php if ($role === "Administrator"): ?>

                            <div class="activity">

                                <div class="activity-icon">
                                    <?php echo icon("shield"); ?>
                                </div>

                                <div class="activity-text">

                                    <strong>
                                        Security
                                    </strong>

                                    <span>
                                        Encryption, access control and
                                        audit logs enabled
                                    </span>

                                </div>

                            </div>

                        <?php endif; ?>


                        <?php if ($role === "Employee"): ?>

                            <div class="activity">

                                <div class="activity-icon">
                                    <?php echo icon("file"); ?>
                                </div>

                                <div class="activity-text">

                                    <strong>
                                        My Payslips
                                    </strong>

                                    <span>
                                        View your payroll records
                                    </span>

                                </div>

                            </div>


                            <div class="activity">

                                <div class="activity-icon">
                                    <?php echo icon("gift"); ?>
                                </div>

                                <div class="activity-text">

                                    <strong>
                                        My Benefits
                                    </strong>

                                    <span>
                                        View your active benefits
                                    </span>

                                </div>

                            </div>

                        <?php endif; ?>

                    </div>

                </section>

            </div>


            <!-- SECURITY MESSAGE -->

            <div class="security-message">

                <div class="security-message-icon">
                    <?php echo icon("shield"); ?>
                </div>

                <div>

                    <strong>
                        Secure payroll. Better benefits. A stronger tomorrow.
                    </strong>

                    <span>
                        Payroll and employee information is protected by
                        role-based access, encryption, session security,
                        audit logging, CSRF protection, and secure backups.
                    </span>

                </div>

            </div>

        </section>

    </main>

</div>


<!-- AUTO LOGOUT -->

<script src="auto_logout.js"></script>


<script>

/*
|--------------------------------------------------------------------------
| Live Philippine Date and Time
|--------------------------------------------------------------------------
*/

function updateLiveClock() {

    const dateElement =
        document.getElementById("liveDate");

    const timeElement =
        document.getElementById("liveTime");

    if (!dateElement || !timeElement) {
        return;
    }

    const now = new Date();

    const dateFormatter =
        new Intl.DateTimeFormat(
            "en-PH",
            {
                timeZone: "Asia/Manila",
                year: "numeric",
                month: "long",
                day: "2-digit"
            }
        );

    const timeFormatter =
        new Intl.DateTimeFormat(
            "en-PH",
            {
                timeZone: "Asia/Manila",
                hour: "2-digit",
                minute: "2-digit",
                second: "2-digit",
                hour12: true
            }
        );

    dateElement.textContent =
        dateFormatter.format(now);

    timeElement.textContent =
        timeFormatter.format(now);
}

updateLiveClock();

setInterval(
    updateLiveClock,
    1000
);


/*
|--------------------------------------------------------------------------
| Mobile Menu
|--------------------------------------------------------------------------
*/

(function () {

    const menuButton =
        document.getElementById("mobileMenu");

    const sidebar =
        document.getElementById("sidebar");

    if (menuButton && sidebar) {

        menuButton.addEventListener(
            "click",
            function () {

                sidebar.classList.toggle("open");

            }
        );

    }

})();


/*
|--------------------------------------------------------------------------
| Functional Payroll Chart
|--------------------------------------------------------------------------
*/

const payrollLabels = <?php
echo json_encode(
    $payroll_chart_labels,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);
?>;

const payrollGross = <?php
echo json_encode(
    $payroll_chart_gross,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);
?>;

const payrollNet = <?php
echo json_encode(
    $payroll_chart_net,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);
?>;


const payrollCanvas =
    document.getElementById("payrollChart");

if (
    payrollCanvas &&
    payrollLabels.length > 0 &&
    typeof Chart !== "undefined"
) {

    new Chart(
        payrollCanvas,
        {
            type: "bar",

            data: {

                labels: payrollLabels,

                datasets: [

                    {
                        label: "Gross Pay",

                        data: payrollGross,

                        backgroundColor: "#C9A66B",

                        borderColor: "#C9A66B",

                        borderWidth: 1,

                        borderRadius: 5,

                        maxBarThickness: 22
                    },

                    {
                        label: "Net Pay",

                        data: payrollNet,

                        backgroundColor: "#9B7187",

                        borderColor: "#9B7187",

                        borderWidth: 1,

                        borderRadius: 5,

                        maxBarThickness: 22
                    }

                ]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                interaction: {
                    mode: "index",
                    intersect: false
                },

                plugins: {

                    legend: {

                        position: "top",

                        align: "end",

                        labels: {

                            color: "#A8AAA7",

                            boxWidth: 8,

                            boxHeight: 8,

                            padding: 15,

                            font: {
                                size: 10
                            }

                        }

                    },

                    tooltip: {

                        backgroundColor: "#111418",

                        borderColor: "#2A3037",

                        borderWidth: 1,

                        titleColor: "#F5F3EF",

                        bodyColor: "#C9BBA5",

                        padding: 11,

                        callbacks: {

                            label: function(context) {

                                const value =
                                    Number(
                                        context.raw || 0
                                    );

                                return (
                                    " " +
                                    context.dataset.label +
                                    ": ₱" +
                                    value.toLocaleString(
                                        "en-PH",
                                        {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        }
                                    )
                                );

                            }

                        }

                    }

                },

                scales: {

                    x: {

                        grid: {
                            display: false
                        },

                        ticks: {

                            color: "#777C7A",

                            font: {
                                size: 9
                            }

                        }

                    },

                    y: {

                        beginAtZero: true,

                        grid: {

                            color:
                                "rgba(255,255,255,.045)"

                        },

                        ticks: {

                            color: "#777C7A",

                            font: {
                                size: 9
                            },

                            callback: function(value) {

                                return "₱" +
                                    Number(value)
                                    .toLocaleString(
                                        "en-PH",
                                        {
                                            notation: "compact",
                                            maximumFractionDigits: 1
                                        }
                                    );

                            }

                        }

                    }

                }

            }

        }
    );

}


/*
|--------------------------------------------------------------------------
| Functional Benefits Chart
|--------------------------------------------------------------------------
*/

const benefitLabels = <?php
echo json_encode(
    $benefit_chart_labels,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);
?>;

const benefitValues = <?php
echo json_encode(
    $benefit_chart_values,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);
?>;


const benefitCanvas =
    document.getElementById("benefitChart");

if (
    benefitCanvas &&
    benefitLabels.length > 0 &&
    typeof Chart !== "undefined"
) {

    new Chart(
        benefitCanvas,
        {
            type: "doughnut",

            data: {

                labels: benefitLabels,

                datasets: [

                    {
                        data: benefitValues,

                        backgroundColor: [
                            "#C9A66B",
                            "#9B7187",
                            "#71998B",
                            "#7088A8",
                            "#A9798D",
                            "#B86D78"
                        ],

                        borderColor: "#14181D",

                        borderWidth: 3,

                        hoverOffset: 8

                    }

                ]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                cutout: "64%",

                plugins: {

                    legend: {
                        display: false
                    },

                    tooltip: {

                        backgroundColor: "#111418",

                        borderColor: "#2A3037",

                        borderWidth: 1,

                        titleColor: "#F5F3EF",

                        bodyColor: "#C9BBA5",

                        padding: 11,

                        callbacks: {

                            label: function(context) {

                                const total =
                                    context.dataset.data
                                    .reduce(
                                        function(
                                            sum,
                                            value
                                        ) {

                                            return (
                                                sum +
                                                Number(value)
                                            );

                                        },
                                        0
                                    );

                                const value =
                                    Number(
                                        context.raw || 0
                                    );

                                const percentage =
                                    total > 0
                                    ? Math.round(
                                        (
                                            value /
                                            total
                                        ) * 100
                                    )
                                    : 0;

                                return (
                                    " " +
                                    context.label +
                                    ": " +
                                    value +
                                    " (" +
                                    percentage +
                                    "%)"
                                );

                            }

                        }

                    }

                }

            }

        }
    );

}

</script>

</body>
</html>