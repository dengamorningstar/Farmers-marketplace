<?php
session_start();
include("../includes/db.php");

/* =========================
   AUTH PROTECTION
========================= */
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'farmer') {
    die("Unauthorized access");
}

$farmer_id = intval($_SESSION['user']);

/* =========================
   VALIDATE PRODUCT ID
========================= */
if (!isset($_GET['id'])) {
    die("Product ID missing");
}

$product_id = intval($_GET['id']);

/* =========================
   VERIFY OWNERSHIP (SECURITY LOCK)
========================= */
$stmt = $conn->prepare("
    SELECT product_id, image 
    FROM products 
    WHERE product_id = ? AND farmer_id = ?
");

$stmt->bind_param("ii", $product_id, $farmer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("You are not allowed to delete this product");
}

$product = $result->fetch_assoc();

/* =========================
   OPTIONAL: CHECK IF PRODUCT HAS ORDERS
   (PREVENT DATA BREAKAGE IN SYSTEM)
========================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM order_items 
    WHERE product_id = ?
");

$stmt->bind_param("i", $product_id);
$stmt->execute();
$orderCheck = $stmt->get_result()->fetch_assoc();

if ($orderCheck['total'] > 0) {
    die("Cannot delete product. It already has order history.");
}

/* =========================
   TRANSACTION SAFETY DELETE
========================= */
$conn->begin_transaction();

try {

    /* =========================
       DELETE PRODUCT
    ========================= */
    $stmt = $conn->prepare("
        DELETE FROM products 
        WHERE product_id = ? AND farmer_id = ?
    ");

    $stmt->bind_param("ii", $product_id, $farmer_id);
    $stmt->execute();

    /* =========================
       DELETE IMAGE FILE (CLEANUP)
    ========================= */
    if (!empty($product['image'])) {
        $file_path = __DIR__ . "/../uploads/" . $product['image'];

        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    $conn->commit();

    header("Location: ../pages/farmer_products.php?deleted=1");
    exit();

} catch (Exception $e) {

    $conn->rollback();
    die("Error deleting product: " . $e->getMessage());
}
?>