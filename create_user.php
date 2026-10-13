<?php

require_once "db.php";

$username = "admin";
$password = "Admin123!";
$full_name = "System Administrator";
$role = "Administrator";

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    INSERT INTO users
    (
        Username,
        Password_Hash,
        Full_Name,
        Role,
        Status
    )
    VALUES (?, ?, ?, ?, 'Active')
");

$stmt->bind_param(
    "ssss",
    $username,
    $password_hash,
    $full_name,
    $role
);

if ($stmt->execute()) {

    echo "<h2>Administrator account created successfully!</h2>";

    echo "<p>Username: <strong>admin</strong></p>";

    echo "<p>Password: <strong>Admin123!</strong></p>";

} else {

    echo "Failed to create account: " .
         htmlspecialchars($stmt->error);

}

$stmt->close();
$conn->close();

?>