<?php
session_start();
include("../includes/db.php");

/* =========================
   AUTH CHECK (FARMER ONLY)
========================= */
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'farmer') {
    die("Unauthorized access. Farmers only.");
}

/* =========================
   FARMER ID (SECURE SOURCE)
========================= */
$farmer_id = intval($_SESSION['user']);

/* =========================
   GET FARMER ZONE (AUTO ASSIGN)
========================= */
$stmtZone = $conn->prepare("
    SELECT zone_id 
    FROM users 
    WHERE user_id = ? 
    LIMIT 1
");

$stmtZone->bind_param("i", $farmer_id);
$stmtZone->execute();
$resZone = $stmtZone->get_result();

$rowZone = $resZone->fetch_assoc();
$zone_id = intval($rowZone['zone_id'] ?? 0);

if ($zone_id <= 0) {
    die("Farmer zone not set. Please update your profile.");
}

/* =========================
   VALIDATE INPUTS
========================= */
if (
    empty($_POST['name']) ||
    !isset($_POST['price'], $_POST['quantity'])
) {
    die("Invalid form submission");
}

$name        = trim($_POST['name']);
$description = trim($_POST['description'] ?? '');

/* =========================
   PRICE VALIDATION (SAFE FLOAT HANDLING)
========================= */
if (!is_numeric($_POST['price'])) {
    die("Invalid price format");
}

$price = (float)$_POST['price'];
$price = round($price, 2);

if ($price <= 0) {
    die("Price must be greater than 0");
}

/* =========================
   QUANTITY
========================= */
$quantity = intval($_POST['quantity']);

if ($quantity <= 0) {
    die("Quantity must be greater than 0");
}

/* =========================
   UNIT VALIDATION
========================= */
$unit = strtolower(trim($_POST['unit'] ?? 'kg'));

$allowed_units = ['kg', 'bunch', 'piece', 'crate', 'bag', 'dozen'];

if (!in_array($unit, $allowed_units)) {
    die("Invalid unit. Allowed: kg, bunch, piece, crate, bag, dozen");
}

/* =========================
   IMAGE VALIDATION
========================= */
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    die("Image upload failed");
}

$allowed_types = ["jpg", "jpeg", "png", "webp"];

$image_name = $_FILES['image']['name'];
$image_tmp  = $_FILES['image']['tmp_name'];
$image_ext  = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));

if (!in_array($image_ext, $allowed_types)) {
    die("Only JPG, JPEG, PNG, WEBP allowed");
}

/* =========================
   SECURE FILE NAME
========================= */
$new_image_name = time() . "_" . bin2hex(random_bytes(5)) . "." . $image_ext;

/* =========================
   UPLOAD DIRECTORY
========================= */
$upload_dir = __DIR__ . "/../uploads/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$upload_path = $upload_dir . $new_image_name;

if (!move_uploaded_file($image_tmp, $upload_path)) {
    die("Failed to upload image");
}

/* =========================
   INSERT PRODUCT (WITH ZONE)
========================= */
$stmt = $conn->prepare("
    INSERT INTO products
    (name, description, price, quantity, unit, image, farmer_id, zone_id, status, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

/* =========================
   FIXED BIND PARAM TYPES
========================= */
$stmt->bind_param(
    "ssdissii",
    $name,
    $description,
    $price,
    $quantity,
    $unit,
    $new_image_name,
    $farmer_id,
    $zone_id
);

if ($stmt->execute()) {

    header("Location: ../pages/farmer_products.php?success=1");
    exit();

} else {
    die("Database error: " . $stmt->error);
}
?>