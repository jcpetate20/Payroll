<?php

session_start();

require_once "db.php";
require_once "audit_log.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {

        $error = "Please enter your username and password.";

    } else {

        $stmt = $conn->prepare("
            SELECT
                User_ID,
                Username,
                Password_Hash,
                Full_Name,
                Role,
                Emp_ID,
                Status,
                Failed_Attempts,
                Locked_Until
            FROM users
            WHERE Username = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error = "A system error occurred. Please try again.";

        } else {

            $stmt->bind_param("s", $username);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 1) {

                $user = $result->fetch_assoc();

                // Check if account is inactive
                if ($user["Status"] !== "Active") {

                    $error = "This account is inactive.";

                }

                // Check if account is locked
                elseif (
                    !empty($user["Locked_Until"]) &&
                    strtotime($user["Locked_Until"]) > time()
                ) {

                    $error =
                        "This account is temporarily locked. " .
                        "Please try again later.";

                }

                // Verify password
                elseif (
                    password_verify(
                        $password,
                        $user["Password_Hash"]
                    )
                ) {

                    // Successful login
                    session_regenerate_id(true);

                    $_SESSION["User_ID"] = $user["User_ID"];
                    $_SESSION["Username"] = $user["Username"];
                    $_SESSION["Full_Name"] = $user["Full_Name"];
                    $_SESSION["Role"] = $user["Role"];
                    $_SESSION["Emp_ID"] = $user["Emp_ID"];

                    // Record successful login
                    write_audit_log(
                        $conn,
                        $user["User_ID"],
                        "LOGIN_SUCCESS",
                        "User " .
                        $user["Username"] .
                        " logged in successfully"
                    );

                    // Reset failed attempts
                    $reset = $conn->prepare("
                        UPDATE users
                        SET
                            Failed_Attempts = 0,
                            Locked_Until = NULL,
                            Last_Login = NOW()
                        WHERE User_ID = ?
                    ");

                    if ($reset) {

                        $reset->bind_param(
                            "i",
                            $user["User_ID"]
                        );

                        $reset->execute();
                        $reset->close();
                    }

                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    exit;
                }

                // Wrong password
                else {

                    $new_attempts =
                        intval($user["Failed_Attempts"]) + 1;

                    // Record failed login
                    write_audit_log(
                        $conn,
                        $user["User_ID"],
                        "LOGIN_FAILED",
                        "Failed login attempt for user " .
                        $user["Username"]
                    );

                    // Lock account after 5 failed attempts
                    if ($new_attempts >= 5) {

                        $locked_until =
                            date(
                                "Y-m-d H:i:s",
                                time() + (15 * 60)
                            );

                        // Record account lock
                        write_audit_log(
                            $conn,
                            $user["User_ID"],
                            "ACCOUNT_LOCKED",
                            "Account " .
                            $user["Username"] .
                            " locked after 5 failed login attempts"
                        );

                        $lock = $conn->prepare("
                            UPDATE users
                            SET
                                Failed_Attempts = ?,
                                Locked_Until = ?
                            WHERE User_ID = ?
                        ");

                        if ($lock) {

                            $lock->bind_param(
                                "isi",
                                $new_attempts,
                                $locked_until,
                                $user["User_ID"]
                            );

                            $lock->execute();
                            $lock->close();
                        }

                        $error =
                            "Too many failed login attempts. " .
                            "Your account is locked for 15 minutes.";

                    } else {

                        $update = $conn->prepare("
                            UPDATE users
                            SET Failed_Attempts = ?
                            WHERE User_ID = ?
                        ");

                        if ($update) {

                            $update->bind_param(
                                "ii",
                                $new_attempts,
                                $user["User_ID"]
                            );

                            $update->execute();
                            $update->close();
                        }

                        $remaining =
                            5 - $new_attempts;

                        $error =
                            "Invalid username or password. " .
                            $remaining .
                            " attempt(s) remaining.";
                    }
                }

            } else {

                // Do not reveal whether username exists
                $error =
                    "Invalid username or password.";
            }

            $stmt->close();
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

    <title>Payroll &amp; Benefits System - Login</title>

    <style>

        * {
            box-sizing: border-box;
        }

        :root {
            --black: #080A0C;
            --charcoal: #101317;
            --panel: #15191E;
            --panel-light: #1C2127;
            --border: #2B3037;
            --gold: #C9A66B;
            --gold-soft: #E1C79A;
            --burgundy: #6E3045;
            --mauve: #9B7187;
            --text: #F5F3EF;
            --muted: #A8A8A4;
            --error-bg: #321A22;
            --error-border: #82445A;
            --error-text: #F2C6D0;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family:
                "Segoe UI",
                Arial,
                sans-serif;
            color: var(--text);
            background:
                radial-gradient(
                    circle at 12% 20%,
                    rgba(110, 48, 69, 0.22),
                    transparent 32%
                ),
                radial-gradient(
                    circle at 90% 85%,
                    rgba(201, 166, 107, 0.10),
                    transparent 28%
                ),
                var(--black);
            overflow-x: hidden;
        }

        .page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1.08fr 0.92fr;
        }

        .visual-panel {
            position: relative;
            min-height: 100vh;
            padding: 48px 7vw;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            border-right: 1px solid var(--border);
            background:
                linear-gradient(
                    145deg,
                    #111419 0%,
                    #0B0D10 55%,
                    #141016 100%
                );
        }

        .visual-panel::before {
            content: "";
            position: absolute;
            width: 520px;
            height: 520px;
            border-radius: 50%;
            left: -260px;
            bottom: -220px;
            background:
                radial-gradient(
                    circle,
                    rgba(110, 48, 69, 0.48),
                    rgba(110, 48, 69, 0.03) 68%,
                    transparent 70%
                );
        }

        .visual-panel::after {
            content: "";
            position: absolute;
            width: 380px;
            height: 380px;
            border-radius: 50%;
            right: -180px;
            top: -170px;
            border: 1px solid rgba(201, 166, 107, 0.16);
        }

        .brand {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-mark {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            color: var(--gold-soft);
            background:
                linear-gradient(
                    145deg,
                    rgba(201, 166, 107, 0.20),
                    rgba(110, 48, 69, 0.32)
                );
            border: 1px solid rgba(201, 166, 107, 0.30);
        }

        .brand-mark svg {
            width: 25px;
            height: 25px;
        }

        .brand-title {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }

        .brand-subtitle {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .visual-content {
            position: relative;
            z-index: 2;
            max-width: 650px;
            margin: auto 0;
            padding: 50px 0;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--gold-soft);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .eyebrow::before {
            content: "";
            width: 30px;
            height: 1px;
            background: var(--gold);
        }

        .visual-content h1 {
            margin: 0;
            max-width: 620px;
            font-size: clamp(42px, 5vw, 76px);
            line-height: 0.98;
            letter-spacing: -3px;
            font-weight: 700;
        }

        .visual-content h1 span {
            color: var(--gold-soft);
        }

        .visual-content p {
            max-width: 540px;
            margin: 26px 0 0;
            color: #BFC0BD;
            font-size: 17px;
            line-height: 1.7;
        }

        .dashboard-graphic {
            position: relative;
            margin-top: 45px;
            width: min(100%, 570px);
            height: 245px;
            border: 1px solid var(--border);
            border-radius: 22px;
            background:
                linear-gradient(
                    145deg,
                    rgba(255,255,255,0.045),
                    rgba(255,255,255,0.012)
                );
            box-shadow:
                0 25px 60px rgba(0,0,0,0.30);
            overflow: hidden;
        }

        .graphic-header {
            height: 44px;
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 0 16px;
            border-bottom: 1px solid var(--border);
        }

        .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #4B5056;
        }

        .graphic-body {
            display: grid;
            grid-template-columns: 130px 1fr;
            height: calc(100% - 44px);
        }

        .graphic-sidebar {
            padding: 18px 12px;
            border-right: 1px solid var(--border);
        }

        .graphic-line {
            height: 9px;
            border-radius: 5px;
            background: #30353B;
            margin-bottom: 13px;
        }

        .graphic-line.active {
            width: 88%;
            background: var(--burgundy);
        }

        .graphic-main {
            padding: 20px;
        }

        .mini-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .mini-card {
            height: 55px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #171B20;
        }

        .chart {
            position: relative;
            height: 105px;
            margin-top: 16px;
            border-bottom: 1px solid #343A40;
            border-left: 1px solid #343A40;
            overflow: hidden;
        }

        .chart::before,
        .chart::after {
            content: "";
            position: absolute;
            left: 8%;
            right: 0;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .chart::before {
            top: 35%;
        }

        .chart::after {
            top: 68%;
        }

        .chart-line {
            position: absolute;
            left: 6%;
            right: 3%;
            bottom: 23px;
            height: 60px;
            border-top: 3px solid var(--gold);
            transform: skewY(-10deg);
            border-radius: 50%;
        }

        .graphic-footer {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            color: var(--muted);
            font-size: 12px;
        }

        .secure-note {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .secure-note svg {
            width: 16px;
            height: 16px;
            color: var(--gold-soft);
        }

        .login-panel {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 45px;
            background: #0D1013;
        }

        .login-box {
            width: min(100%, 455px);
        }

        .login-heading {
            margin-bottom: 32px;
        }

        .login-heading .small-title {
            margin: 0 0 9px;
            color: var(--gold-soft);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .login-heading h2 {
            margin: 0;
            font-size: 38px;
            letter-spacing: -1.5px;
        }

        .login-heading p {
            margin: 10px 0 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .form-card {
            padding: 32px;
            border: 1px solid var(--border);
            border-radius: 20px;
            background:
                linear-gradient(
                    145deg,
                    #171B20,
                    #121519
                );
            box-shadow:
                0 25px 70px rgba(0,0,0,0.30);
        }

        .error {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            background: var(--error-bg);
            color: var(--error-text);
            border: 1px solid var(--error-border);
            padding: 13px 14px;
            border-radius: 11px;
            margin-bottom: 22px;
            font-size: 13px;
            line-height: 1.5;
        }

        .error svg {
            flex: 0 0 auto;
            width: 18px;
            height: 18px;
            margin-top: 1px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #E9E7E2;
            font-size: 13px;
            font-weight: 600;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: #777D83;
            pointer-events: none;
        }

        input {
            width: 100%;
            height: 50px;
            padding: 0 46px;
            border: 1px solid var(--border);
            border-radius: 11px;
            outline: none;
            color: var(--text);
            background: #0F1215;
            font-size: 14px;
            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }

        input::placeholder {
            color: #6F757A;
        }

        input:focus {
            border-color: var(--gold);
            background: #11151A;
            box-shadow:
                0 0 0 3px rgba(201, 166, 107, 0.10);
        }

        .password-toggle {
            position: absolute;
            right: 9px;
            top: 50%;
            transform: translateY(-50%);
            width: 35px;
            height: 35px;
            display: grid;
            place-items: center;
            padding: 0;
            border: 0;
            border-radius: 8px;
            color: #898E93;
            background: transparent;
            cursor: pointer;
        }

        .password-toggle:hover {
            color: var(--gold-soft);
            background: rgba(255,255,255,0.04);
        }

        .password-toggle svg {
            width: 18px;
            height: 18px;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2px 0 24px;
            color: var(--muted);
            font-size: 12px;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .forgot-password {
            display: block;
            margin-top: 14px;
            color: var(--gold-soft);
            text-decoration: none;
            text-align: center;
            font-size: 12px;
            transition: color 0.2s ease;
        }

        .forgot-password:hover {
            color: var(--gold);
            text-decoration: underline;
        }

        .remember input {
            width: 15px;
            height: 15px;
            padding: 0;
            accent-color: var(--gold);
            box-shadow: none;
        }

        .secure-login {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .secure-login svg {
            width: 14px;
            height: 14px;
            color: var(--gold);
        }

        .login-button {
            width: 100%;
            height: 52px;
            border: 1px solid rgba(201, 166, 107, 0.40);
            border-radius: 11px;
            background:
                linear-gradient(
                    135deg,
                    #8A6341,
                    #B38A55
                );
            color: #080A0C;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.2px;
            cursor: pointer;
            transition:
                transform 0.2s ease,
                filter 0.2s ease,
                box-shadow 0.2s ease;
        }

        .login-button:hover {
            filter: brightness(1.08);
            transform: translateY(-1px);
            box-shadow:
                0 10px 25px rgba(201,166,107,0.16);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .login-button:focus-visible,
        .password-toggle:focus-visible,
        input:focus-visible {
            outline: 2px solid var(--gold-soft);
            outline-offset: 2px;
        }

        .login-footer {
            margin-top: 24px;
            color: #6F757A;
            text-align: center;
            font-size: 11px;
            line-height: 1.6;
        }

        .login-footer strong {
            color: #9A9D9E;
        }

        @media (max-width: 1050px) {

            .page {
                grid-template-columns: 1fr;
            }

            .visual-panel {
                min-height: auto;
                padding: 38px 7vw;
                border-right: 0;
                border-bottom: 1px solid var(--border);
            }

            .visual-content {
                padding: 70px 0 45px;
            }

            .visual-content h1 {
                font-size: clamp(42px, 8vw, 65px);
            }

            .dashboard-graphic {
                max-width: 620px;
            }

            .graphic-footer {
                margin-top: 10px;
            }

            .login-panel {
                min-height: auto;
                padding: 55px 7vw;
            }

        }

        @media (max-width: 600px) {

            .visual-panel {
                padding: 28px 22px;
            }

            .visual-content {
                padding: 55px 0 35px;
            }

            .visual-content h1 {
                font-size: 42px;
                letter-spacing: -1.8px;
            }

            .visual-content p {
                font-size: 15px;
            }

            .dashboard-graphic {
                height: 190px;
                margin-top: 30px;
            }

            .graphic-sidebar {
                display: none;
            }

            .graphic-body {
                grid-template-columns: 1fr;
            }

            .login-panel {
                padding: 40px 20px;
            }

            .form-card {
                padding: 24px 20px;
                border-radius: 16px;
            }

            .login-heading h2 {
                font-size: 32px;
            }

        }

    </style>

</head>

<body>

    <main class="page">

        <section class="visual-panel">

            <div class="brand">

                <div class="brand-mark" aria-hidden="true">

                    <svg viewBox="0 0 24 24" fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round">

                        <path d="M4 7.5h16v11H4z"></path>
                        <path d="M7 7.5V5.8A2.8 2.8 0 0 1 9.8 3h4.4A2.8 2.8 0 0 1 17 5.8v1.7"></path>
                        <path d="M8 12h8"></path>
                        <path d="M8 15.5h5"></path>

                    </svg>

                </div>

                <div>

                    <p class="brand-title">
                        Payroll &amp; Benefits
                    </p>

                    <p class="brand-subtitle">
                        Secure Management System
                    </p>

                </div>

            </div>


            <div class="visual-content">

                <div class="eyebrow">
                    Secure payroll management
                </div>

                <h1>
                    Manage payroll with
                    <span>confidence.</span>
                </h1>

                <p>
                    A secure workspace for employee records,
                    payroll processing, benefits, reporting,
                    and administrative management.
                </p>


                <div class="dashboard-graphic"
                     aria-hidden="true">

                    <div class="graphic-header">

                        <span class="dot"></span>
                        <span class="dot"></span>
                        <span class="dot"></span>

                    </div>

                    <div class="graphic-body">

                        <div class="graphic-sidebar">

                            <div class="graphic-line active"></div>
                            <div class="graphic-line"></div>
                            <div class="graphic-line"></div>
                            <div class="graphic-line"></div>
                            <div class="graphic-line"></div>

                        </div>

                        <div class="graphic-main">

                            <div class="mini-cards">

                                <div class="mini-card"></div>
                                <div class="mini-card"></div>
                                <div class="mini-card"></div>

                            </div>

                            <div class="chart">
                                <div class="chart-line"></div>
                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <div class="graphic-footer">

                <div class="secure-note">

                    <svg viewBox="0 0 24 24" fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round">

                        <path d="M12 3l8 3v6c0 5-3.4 8.2-8 9-4.6-.8-8-4-8-9V6l8-3z"></path>
                        <path d="M9 12l2 2 4-4"></path>

                    </svg>

                    Protected access

                </div>

                <span>
                    Payroll &amp; Benefits System
                </span>

            </div>

        </section>


        <section class="login-panel">

            <div class="login-box">

                <div class="login-heading">

                    <p class="small-title">
                        Administrator &amp; employee portal
                    </p>

                    <h2>
                        Sign in
                    </h2>

                    <p>
                        Enter your account credentials to
                        access the payroll and benefits system.
                    </p>

                </div>


                <div class="form-card">

                    <?php if ($error !== ""): ?>

                        <div class="error" role="alert">

                            <svg viewBox="0 0 24 24" fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round">

                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="M12 8v5"></path>
                                <path d="M12 16h.01"></path>

                            </svg>

                            <span>
                                <?php
                                echo htmlspecialchars($error);
                                ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <form
                        method="POST"
                        action="login.php"
                        autocomplete="on"
                    >

                        <div class="form-group">

                            <label for="username">
                                Username
                            </label>

                            <div class="input-wrap">

                                <svg class="input-icon"
                                     viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="1.8"
                                     stroke-linecap="round"
                                     stroke-linejoin="round"
                                     aria-hidden="true">

                                    <circle cx="12" cy="8" r="4"></circle>
                                    <path d="M4.5 21a7.5 7.5 0 0 1 15 0"></path>

                                </svg>

                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    placeholder="Enter your username"
                                    autocomplete="username"
                                    required
                                    autofocus
                                >

                            </div>

                        </div>


                        <div class="form-group">

                            <label for="password">
                                Password
                            </label>

                            <div class="input-wrap">

                                <svg class="input-icon"
                                     viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="1.8"
                                     stroke-linecap="round"
                                     stroke-linejoin="round"
                                     aria-hidden="true">

                                    <rect x="4" y="10" width="16" height="11" rx="2"></rect>
                                    <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>

                                </svg>

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    id="passwordToggle"
                                    aria-label="Show password"
                                    title="Show password"
                                >

                                    <svg
                                        id="eyeIcon"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >

                                        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"></path>
                                        <circle cx="12" cy="12" r="2.5"></circle>

                                    </svg>

                                </button>

                            </div>

                        </div>


                        <div class="form-options">

                            <label class="remember">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    value="1"
                                >
                                <span>
                                    Remember me
                                </span>
                            </label>

                            <span class="secure-login">
                                <svg viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true">

                                    <path d="M12 3l8 3v6c0 5-3.4 8.2-8 9-4.6-.8-8-4-8-9V6l8-3z"></path>

                                </svg>

                                Secure login
                            </span>

                        </div>

                        <button
                            type="submit"
                            class="login-button"
                        >
                            Sign In
                        </button>

                        <a href="forgot_password.php" class="forgot-password">
                            Forgot password?
                        </a>

                    </form>

                </div>


                <div class="login-footer">

                    <strong>Payroll &amp; Benefits System</strong><br>

                    Authorized users only. All access activity
                    may be recorded for security purposes.

                </div>

            </div>

        </section>

    </main>


    <script>

        (function () {

            const password =
                document.getElementById("password");

            const toggle =
                document.getElementById("passwordToggle");

            const eyeIcon =
                document.getElementById("eyeIcon");

            if (!password || !toggle || !eyeIcon) {
                return;
            }

            toggle.addEventListener("click", function () {

                const isPassword =
                    password.type === "password";

                password.type =
                    isPassword
                        ? "text"
                        : "password";

                toggle.setAttribute(
                    "aria-label",
                    isPassword
                        ? "Hide password"
                        : "Show password"
                );

                toggle.setAttribute(
                    "title",
                    isPassword
                        ? "Hide password"
                        : "Show password"
                );

                if (isPassword) {

                    eyeIcon.innerHTML = `
                        <path d="M3 3l18 18"></path>
                        <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
                        <path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6 0 9.5 7 9.5 7a17 17 0 0 1-3.1 3.9"></path>
                        <path d="M6.1 6.1C3.8 7.7 2.5 12 2.5 12a17 17 0 0 0 5.2 5.2A10.8 10.8 0 0 0 12 19c1.2 0 2.3-.2 3.3-.6"></path>
                    `;

                } else {

                    eyeIcon.innerHTML = `
                        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"></path>
                        <circle cx="12" cy="12" r="2.5"></circle>
                    `;

                }

            });

        })();

    </script>

</body>

</html>
