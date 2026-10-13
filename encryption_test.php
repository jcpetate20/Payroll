<?php

require_once "encryption.php";

$original_text = "Test Employee Information 123";

$encrypted_text = encrypt_data($original_text);

$decrypted_text = decrypt_data($encrypted_text);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encryption Test</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f1e8;
            color: #3a0b13;
            padding: 40px;
        }

        .container {
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
        }

        .encrypted {
            word-break: break-all;
            background: #f1e8df;
            padding: 15px;
            border-radius: 8px;
        }

        .success {
            color: green;
            font-weight: bold;
        }

        .error {
            color: red;
            font-weight: bold;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Encryption Test</h1>

    <p>
        <strong>Original:</strong><br>
        <?= htmlspecialchars($original_text) ?>
    </p>

    <p>
        <strong>Encrypted:</strong><br>
    </p>

    <div class="encrypted">
        <?php if ($encrypted_text !== false): ?>

            <?= htmlspecialchars($encrypted_text) ?>

        <?php else: ?>

            Encryption failed.

        <?php endif; ?>
    </div>

    <p>
        <strong>Decrypted:</strong><br>
        <?= htmlspecialchars($decrypted_text) ?>
    </p>

    <?php if (
        $encrypted_text !== false &&
        $decrypted_text === $original_text
    ): ?>

        <p class="success">
            ✅ Encryption and decryption are working correctly.
        </p>

    <?php else: ?>

        <p class="error">
            ❌ Encryption test failed.
        </p>

    <?php endif; ?>

</div>

</body>

</html>