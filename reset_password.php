<?php

session_start();

require_once "db.php";

$error = "";
$success = "";

$token = trim($_GET["token"] ?? $_POST["token"] ?? "");

if ($token === "") {

    $error = "Invalid or missing password reset link.";

} else {

    $token_hash = hash(
        "sha256",
        $token
    );

    $stmt = $conn->prepare("
        SELECT
            User_ID,
            Username,
            Status
        FROM users
        WHERE Password_Reset_Token_Hash = ?
          AND Password_Reset_Expires IS NOT NULL
          AND Password_Reset_Expires > NOW()
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "s",
            $token_hash
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {

            $error = "This password reset link is invalid or has expired.";

        } else {

            $user = $result->fetch_assoc();

            if ($user["Status"] !== "Active") {

                $error = "This account is inactive.";

            } elseif ($_SERVER["REQUEST_METHOD"] === "POST") {

                $password = $_POST["password"] ?? "";
                $confirm_password = $_POST["confirm_password"] ?? "";

                if (strlen($password) < 8) {

                    $error =
                        "Password must be at least 8 characters long.";

                } elseif ($password !== $confirm_password) {

                    $error =
                        "Passwords do not match.";

                } else {

                    $password_hash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $update = $conn->prepare("
                        UPDATE users
                        SET
                            Password_Hash = ?,
                            Password_Reset_Token_Hash = NULL,
                            Password_Reset_Expires = NULL,
                            Failed_Attempts = 0,
                            Locked_Until = NULL
                        WHERE User_ID = ?
                    ");

                    if ($update) {

                        $update->bind_param(
                            "si",
                            $password_hash,
                            $user["User_ID"]
                        );

                        if ($update->execute()) {

                            $success =
                                "Your password has been changed successfully.";

                        } else {

                            $error =
                                "Unable to change your password. Please try again.";
                        }

                        $update->close();

                    } else {

                        $error =
                            "A system error occurred. Please try again.";
                    }
                }
            }
        }

        $stmt->close();

    } else {

        $error =
            "A system error occurred. Please try again.";
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

    <title>Reset Password - Payroll & Benefits System</title>

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
            margin: 0 0 28px;
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

        .form-group {
            margin-bottom: 18px;
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
            margin-top: 5px;
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

        <p class="brand-title">
            Payroll &amp; Benefits System
        </p>

        <h1>
            Reset Password
        </h1>

        <?php if ($success !== ""): ?>

            <div class="message">

                <?php echo htmlspecialchars($success); ?>

            </div>

            <a
                href="login.php"
                class="back"
            >
                ← Return to Sign In
            </a>

        <?php elseif ($error !== ""): ?>

            <div class="error">

                <?php echo htmlspecialchars($error); ?>

            </div>

            <a
                href="login.php"
                class="back"
            >
                ← Return to Sign In
            </a>

        <?php else: ?>

            <p class="description">
                Create a new password for your account.
            </p>

            <form
                method="POST"
                action="reset_password.php"
            >

                <input
                    type="hidden"
                    name="token"
                    value="<?php echo htmlspecialchars($token); ?>"
                >

                <div class="form-group">

                    <label for="password">
                        New Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your new password"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="confirm_password">
                        Confirm New Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm your new password"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >

                </div>

                <button type="submit">
                    Change Password
                </button>

            </form>

            <a
                href="login.php"
                class="back"
            >
                ← Back to Sign In
            </a>

        <?php endif; ?>

    </main>

</body>

</html>