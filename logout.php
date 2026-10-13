<?php

session_start();

/*
 * Destroy all session data
 */

$_SESSION = [];

/*
 * Destroy the session
 */

session_destroy();

/*
 * Return to login page
 */

header("Location: login.php");
exit;

?>