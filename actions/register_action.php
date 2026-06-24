<?php
session_start();
include("../includes/db.php");

/* =========================
   GET FORM DATA
========================= */
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$phone = trim($_POST['phone'] ?? '');
$zone_id = intval($_POST['zone_id'] ?? 0);
$specific_location = trim($_POST['specific_location'] ?? '');
$role = trim($_POST['role'] ?? '');

$county = "Kiambu";

/* =========================
   VALIDATION
========================= */
if ($name === '' || $email === '' || $password === '' || $zone_id === 0 || $role === '') {
    $_SESSION['error'] = "All required fields must be filled.";
    header("Location: /myformapp/pages/register.php");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Invalid email format.";
    header("Location: /myformapp/pages/register.php");
    exit();
}

if (strlen($password) < 6) {
    $_SESSION['error'] = "Password must be at least 6 characters.";
    header("Location: /myformapp/pages/register.php");
    exit();
}

/* =========================
   ROLE CHECK
========================= */
$allowed_roles = ['buyer', 'farmer', 'delivery_person'];

if (!in_array($role, $allowed_roles)) {
    $_SESSION['error'] = "Invalid role selected.";
    header("Location: /myformapp/pages/register.php");
    exit();
}

/* =========================
   CLEAN PHONE
========================= */
$phone = !empty($phone) ? preg_replace('/[^0-9]/', '', $phone) : null;

/* =========================
   CHECK EMAIL EXISTS
========================= */
$check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    $_SESSION['error'] = "Email already registered.";
    header("Location: /myformapp/pages/register.php");
    exit();
}

/* =========================
   VALIDATE ZONE
========================= */
$zoneStmt = $conn->prepare("SELECT zone_label FROM delivery_zones WHERE zone_id = ?");
$zoneStmt->bind_param("i", $zone_id);
$zoneStmt->execute();
$zoneData = $zoneStmt->get_result()->fetch_assoc();

if (!$zoneData) {
    $_SESSION['error'] = "Invalid zone selected.";
    header("Location: /myformapp/pages/register.php");
    exit();
}

/* =========================
   PASSWORD HASH
========================= */
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

/* =========================
   STATUS RULE
========================= */
$status = ($role === 'buyer') ? 'active' : 'pending';

/* =========================
   INSERT USER
========================= */
$stmt = $conn->prepare("
    INSERT INTO users
    (name, email, password, role, phone, county, zone_id, specific_location, account_status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ssssssiss",
    $name,
    $email,
    $hashed_password,
    $role,
    $phone,
    $county,
    $zone_id,
    $specific_location,
    $status
);

if (!$stmt->execute()) {
    die("INSERT FAILED: " . $stmt->error);
}

/* =========================
   SUCCESS
========================= */
$_SESSION['success'] = "Registration successful. Please login.";

header("Location: /myformapp/pages/login.php");
exit();
?>