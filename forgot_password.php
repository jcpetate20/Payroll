<?php

session_start();

require_once "db.php";
require_once "encryption.php";

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");

    if ($username === "") {

        $error = "Please enter your username.";

    } else {

        $stmt = $conn->prepare("
            SELECT
                u.User_ID,
                u.Username,
                u.Status,
                e.Email
            FROM users u
            LEFT JOIN employee e
                ON u.Emp_ID = e.Emp_ID
            WHERE u.Username = ?
            LIMIT 1
        ");

        if ($stmt) {

            $stmt->bind_param("s", $username);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 1) {

                $user = $result->fetch_assoc();

                if ($user["Status"] === "Active" && !empty($user["Email"])) {

                    $email = decrypt_data($user["Email"]);

                    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {

                        $token = bin2hex(random_bytes(32));

                        $token_hash = hash(
                            "sha256",
                            $token
                        );

                        $expires = date(
                            "Y-m-d H:i:s",
                            time() + (15 * 60)
                        );

                        $update = $conn->prepare("
                            UPDATE users
                            SET
                                Password_Reset_Token_Hash = ?,
                                Password_Reset_Expires = ?
                            WHERE User_ID = ?
                        ");

                        if ($update) {

                            $update->bind_param(
                                "ssi",
                                $token_hash,
                                $expires,
                                $user["User_ID"]
                            );

                            if ($update->execute()) {

                                /*
                                 * IMPORTANT:
                                 * Change this URL if your project
                                 * is hosted somewhere else.
                                 */
                                $reset_link =
                                    "http://localhost/payroll_system/reset_password.php?token=" .
                                    urlencode($token);

                                $subject =
                                    "Payroll & Benefits System - Password Reset";

                                $body =
                                    "Hello,\n\n" .
                                    "A password reset was requested for your Payroll & Benefits System account.\n\n" .
                                    "Click the link below to create a new password:\n\n" .
                                    $reset_link .
                                    "\n\n" .
                                    "This link will expire in 15 minutes.\n\n" .
                                    "If you did not request this password reset, you can ignore this email.\n";

                                $headers =
                                    "From: Payroll & Benefits System <noreply@localhost>\r\n" .
                                    "Reply-To: noreply@localhost\r\n" .
                                    "Content-Type: text/plain; charset=UTF-8\r\n";

                                mail(
                                    $email,
                                    $subject,
                                    $body,
                                    $headers
                                );
                            }

                            $update->close();
                        }
                    }
                }
            }

            $stmt->close();
        }

        /*
         * Do not reveal whether the username exists.
         */
        $message =
            "If the account exists and has a registered email address, " .
            "a password reset link has been sent.";
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

    <title>Forgot Password - Payroll & Benefits System</title>

    <style>

        * {
            box-sizing: border-box;
        }

        :root {
            --black: #080A0C;
            --panel: #15191E;
            --border: #2B3037;
            --gold: #C9A66B;
            --gold-soft: #E1C79A;
            --text: #F5F3EF;
            --muted: #A8A8A4;
            --success-bg: #18251F;
            --success-border: #456A59;
            --success-text: #B9DCCB;
            --error-bg: #321A22;
            --error-border: #82445A;
            --error-text: #F2C6D0;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 25px;
            font-family:
                "Segoe UI",
                Arial,
                sans-serif;
            color: var(--text);
            background:
                radial-gradient(
                    circle at 15% 20%,
                    rgba(110, 48, 69, 0.22),
                    transparent 32%
                ),
                radial-gradient(
                    circle at 90% 85%,
                    rgba(201, 166, 107, 0.10),
                    transparent 28%
                ),
                var(--black);
        }

        .box {
            width: min(100%, 455px);
            padding: 34px;
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

        .brand {
            margin-bottom: 30px;
        }

        .brand-title {
            margin: 0;
            color: var(--gold-soft);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        h1 {
            margin: 8px 0 10px;
            font-size: 32px;
            letter-spacing: -1px;
        }

        .description {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
            font-size: 14px;
        }

        .message,
        .error {
            padding: 13px 14px;
            border-radius: 11px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.5;
        }

        .message {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
        }

        .error {
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-text);
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #E9E7E2;
            font-size: 13px;
            font-weight: 600;
        }

        input {
            width: 100%;
            height: 50px;
            padding: 0 15px;
            border: 1px solid var(--border);
            border-radius: 11px;
            outline: none;
            color: var(--text);
            background: #0F1215;
            font-size: 14px;
        }

        input:focus {
            border-color: var(--gold);
            box-shadow:
                0 0 0 3px rgba(201,166,107,0.10);
        }

        button {
            width: 100%;
            height: 52px;
            margin-top: 20px;
            border: 1px solid rgba(201,166,107,0.40);
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
            cursor: pointer;
        }

        button:hover {
            filter: brightness(1.08);
        }

        .back {
            display: block;
            margin-top: 22px;
            color: var(--gold-soft);
            text-align: center;
            text-decoration: none;
            font-size: 13px;
        }

        .back:hover {
            color: var(--gold);
            text-decoration: underline;
        }

    </style>

</head>

<body>

    <main class="box">

        <div class="brand">

            <p class="brand-title">
                Payroll &amp; Benefits System
            </p>

            <h1>
                Forgot Password
            </h1>

            <p class="description">
                Enter your username and we will send a password
                reset link to the email registered with your account.
            </p>

        </div>

        <?php if ($error !== ""): ?>

            <div class="error">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>

        <?php if ($message !== ""): ?>

            <div class="message">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>

        <form
            method="POST"
            action="forgot_password.php"
        >

            <label for="username">
                Username
            </label>

            <input
                type="text"
                id="username"
                name="username"
                placeholder="Enter your username"
                autocomplete="username"
                required
                autofocus
            >

            <button type="submit">
                Send Reset Link
            </button>

        </form>

        <a
            href="login.php"
            class="back"
        >
            ← Back to Sign In
        </a>

    </main>

</body>

</html>