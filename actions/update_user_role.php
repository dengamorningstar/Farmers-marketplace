<?php
session_start();
include("../includes/db.php");

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') {
    header("Location: /myformapp/pages/login.php");
    exit();
}

if (!isset($_POST['user_id'], $_POST['role'])) {
    header("Location: /myformapp/pages/manage_users.php");
    exit();
}

$user_id = (int) $_POST['user_id'];
$new_role = trim($_POST['role']);
$admin_id = $_SESSION['user'];

$allowed_roles = ['buyer', 'farmer', 'delivery_person', 'admin'];

if (!in_array($new_role, $allowed_roles)) {
    header("Location: /myformapp/pages/manage_users.php");
    exit();
}

/* Prevent changing own admin role accidentally */
if ($user_id == $admin_id && $new_role != 'admin') {
    header("Location: /myformapp/pages/manage_users.php");
    exit();
}

$stmt = $conn->prepare("
    UPDATE users
    SET role = ?
    WHERE user_id = ?
");

$stmt->bind_param("si", $new_role, $user_id);
$stmt->execute();

header("Location: /myformapp/pages/manage_users.php");
exit();
?>