<?php

session_start();

require_once "db.php";
require_once "session_security.php";
require_once "csrf.php";
require_once "audit_log.php";


/*
|--------------------------------------------------------------------------
| Check if user is logged in
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["User_ID"])) {
    header("Location: login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Get logged-in user's role
|--------------------------------------------------------------------------
*/

$role = $_SESSION["Role"];


/*
|--------------------------------------------------------------------------
| Only Administrator and HR Staff
|--------------------------------------------------------------------------
*/

if ($role !== "Administrator" && $role !== "HR Staff") {
    http_response_code(403);
    die("Access Denied.");
}


/*
|--------------------------------------------------------------------------
| Only POST requests are allowed
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: employee_management.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Verify CSRF Token
|--------------------------------------------------------------------------
*/

$csrf_token = $_POST["csrf_token"] ?? "";

if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    die("Invalid security token. Please go back and try again.");
}


/*
|--------------------------------------------------------------------------
| Get Employee ID
|--------------------------------------------------------------------------
*/

$emp_id = filter_input(
    INPUT_POST,
    "emp_id",
    FILTER_VALIDATE_INT
);

$action = $_POST["action"] ?? "";


/*
|--------------------------------------------------------------------------
| Validate Request
|--------------------------------------------------------------------------
*/

if (
    !$emp_id ||
    !in_array(
        $action,
        ["deactivate", "reactivate"],
        true
    )
) {
    header("Location: employee_management.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Employee Information
|--------------------------------------------------------------------------
*/

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

$stmt->bind_param(
    "i",
    $emp_id
);

$stmt->execute();

$result = $stmt->get_result();

$employee = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Employee Not Found
|--------------------------------------------------------------------------
*/

if (!$employee) {
    header("Location: employee_management.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Employee Name
|--------------------------------------------------------------------------
*/

$employee_name =
    $employee["First_Name"] .
    " " .
    $employee["Last_Name"];


/*
|--------------------------------------------------------------------------
| Determine New Status
|--------------------------------------------------------------------------
*/

if ($action === "deactivate") {

    $new_status = "Inactive";

} else {

    $new_status = "Active";

}


/*
|--------------------------------------------------------------------------
| Update Employee Status
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    UPDATE employee
    SET Status = ?
    WHERE Emp_ID = ?
");

if (!$stmt) {
    die("Database update error: " . $conn->error);
}

$stmt->bind_param(
    "si",
    $new_status,
    $emp_id
);

if (!$stmt->execute()) {

    $stmt->close();

    die("Unable to update employee status.");

}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Audit Log
|--------------------------------------------------------------------------
*/

$current_user_id = (int) $_SESSION["User_ID"];


if ($action === "deactivate") {

    $audit_action = "EMPLOYEE_DEACTIVATED";

    $audit_description =
        "Employee #" .
        $emp_id .
        " (" .
        $employee_name .
        ") was deactivated.";

} else {

    $audit_action = "EMPLOYEE_REACTIVATED";

    $audit_description =
        "Employee #" .
        $emp_id .
        " (" .
        $employee_name .
        ") was reactivated.";

}


write_audit_log(
    $conn,
    $current_user_id,
    $audit_action,
    $audit_description
);


/*
|--------------------------------------------------------------------------
| Return to Employee Management
|--------------------------------------------------------------------------
*/

header("Location: employee_management.php");

exit;

?>