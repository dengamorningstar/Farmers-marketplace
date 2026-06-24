<?php
session_start();

// Clear all session data
$_SESSION = [];

// Destroy session completely
session_destroy();

// Prevent browser caching old session
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// FIXED: correct absolute project path redirect
header("Location: /myformapp/pages/login.php");
exit();
?>