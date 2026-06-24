<?php
session_start();
include("../includes/db.php");

/* =========================
   CLEAR OLD MESSAGES
========================= */
unset($_SESSION['error']);
unset($_SESSION['success']);

/* =========================
   VALIDATION
========================= */
if (!isset($_POST['email']) || !isset($_POST['password'])) {
    $_SESSION['error'] = "Please fill in all fields.";
    header("Location: /myformapp/pages/login.php");
    exit();
}

$email = trim($_POST['email']);
$password = $_POST['password'];

if ($email === '' || $password === '') {
    $_SESSION['error'] = "Email and password are required.";
    header("Location: /myformapp/pages/login.php");
    exit();
}

/* =========================
   FETCH USER
========================= */
$stmt = $conn->prepare("
    SELECT user_id, name, email, password, role, account_status
    FROM users
    WHERE email = ?
    LIMIT 1
");

if (!$stmt) {
    die("LOGIN QUERY FAILED: " . $conn->error);
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

/* =========================
   USER NOT FOUND
========================= */
if ($result->num_rows === 0) {
    $_SESSION['error'] = "Email not found.";
    header("Location: /myformapp/pages/login.php");
    exit();
}

$user = $result->fetch_assoc();

/* =========================
   ACCOUNT STATUS CHECK
========================= */
if ($user['account_status'] === 'pending') {
    $_SESSION['error'] = "Your account is awaiting admin approval.";
    header("Location: /myformapp/pages/login.php");
    exit();
}

if ($user['account_status'] === 'rejected') {
    $_SESSION['error'] = "Your account request was rejected.";
    header("Location: /myformapp/pages/login.php");
    exit();
}

if ($user['account_status'] === 'suspended') {
    $_SESSION['error'] = "Your account has been suspended. Contact admin.";
    header("Location: /myformapp/pages/login.php");
    exit();
}

/* =========================
   PASSWORD CHECK
========================= */
if (!password_verify($password, $user['password'])) {
    $_SESSION['error'] = "Wrong password.";
    header("Location: /myformapp/pages/login.php");
    exit();
}

/* =========================
   SESSION SECURITY
========================= */
session_regenerate_id(true);

$_SESSION['user'] = $user['user_id'];
$_SESSION['role'] = $user['role'];
$_SESSION['name'] = $user['name'];

/* =========================
   FINAL REDIRECT
========================= */
header("Location: /myformapp/pages/dashboard.php");
exit();
?>