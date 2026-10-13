<?php

session_start();

require_once "db.php";
require_once "session_security.php";
require_once "csrf.php";
require_once "audit_log.php";


// Check if user is logged in
if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}


// Only Administrator and HR Staff can remove employees
$role = $_SESSION["Role"];

if ($role !== "Administrator" && $role !== "HR Staff") {
    http_response_code(403);
    die("Access Denied.");
}


// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: employee_management.php");
    exit;
}


// Verify CSRF token
$csrf_token = $_POST["csrf_token"] ?? "";

if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    die("Invalid security token. Please go back and try again.");
}


// Get employee ID
$emp_id = filter_input(
    INPUT_POST,
    "emp_id",
    FILTER_VALIDATE_INT
);

if (!$emp_id) {
    header("Location: employee_management.php");
    exit;
}


// Get employee information first
$stmt = $conn->prepare("
    SELECT
        Emp_ID,
        First_Name,
        Last_Name,
        Status
    FROM employee
    WHERE Emp_ID = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $emp_id);

$stmt->execute();

$result = $stmt->get_result();

$employee = $result->fetch_assoc();

$stmt->close();


// Employee does not exist
if (!$employee) {
    header("Location: employee_management.php");
    exit;
}


// Prevent removing an employee who is already removed
if ($employee["Status"] === "Removed") {
    header("Location: employee_management.php");
    exit;
}


$employee_name =
    $employee["First_Name"] .
    " " .
    $employee["Last_Name"];


// Change employee status to Removed
$stmt = $conn->prepare("
    UPDATE employee
    SET Status = 'Removed'
    WHERE Emp_ID = ?
");

if (!$stmt) {
    die("Database update error: " . $conn->error);
}

$stmt->bind_param("i", $emp_id);

if (!$stmt->execute()) {
    $stmt->close();
    die("Unable to remove employee.");
}

$stmt->close();


// Write audit log
$current_user_id = (int) $_SESSION["User_ID"];

$audit_description =
    "Employee #" .
    $emp_id .
    " (" .
    $employee_name .
    ") was removed from the company/payroll system. " .
    "Employee and payroll history were preserved.";

write_audit_log(
    $conn,
    $current_user_id,
    "EMPLOYEE_REMOVED",
    $audit_description
);


// Return to Employee Management
header("Location: employee_management.php");
exit;

?>