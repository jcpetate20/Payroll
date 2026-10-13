<?php

function write_audit_log(
    $conn,
    $user_id,
    $action,
    $description = ""
) {

    $ip_address = $_SERVER["REMOTE_ADDR"] ?? "UNKNOWN";

    $stmt = $conn->prepare("
        INSERT INTO audit_logs
        (
            User_ID,
            Action,
            Description,
            IP_Address
        )
        VALUES (?, ?, ?, ?)
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "isss",
        $user_id,
        $action,
        $description,
        $ip_address
    );

    $success = $stmt->execute();

    $stmt->close();

    return $success;
}

?>