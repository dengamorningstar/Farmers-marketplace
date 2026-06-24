<?php
session_start();
include("../includes/db.php");

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_id = intval($_SESSION['user']);

/* =========================
   VALIDATE INPUT
========================= */
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Invalid request";
    header("Location: ../pages/view_cart.php");
    exit();
}

$product_id = intval($_GET['id']);

if ($product_id <= 0) {
    $_SESSION['error'] = "Invalid product";
    header("Location: ../pages/view_cart.php");
    exit();
}

/* =========================
   TRANSACTION START (SAFE REMOVE)
========================= */
$conn->begin_transaction();

try {

    /* =========================
       VERIFY ITEM EXISTS IN CART
    ========================== */
    $stmt = $conn->prepare("
        SELECT cart_id
        FROM cart
        WHERE user_id = ? 
        AND product_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new Exception("Failed to prepare cart check");
    }

    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();

    $cart = $stmt->get_result()->fetch_assoc();

    if (!$cart) {
        throw new Exception("Item not found in cart");
    }

    /* =========================
       DELETE ITEM
    ========================== */
    $stmt = $conn->prepare("
        DELETE FROM cart
        WHERE user_id = ? 
        AND product_id = ?
    ");

    if (!$stmt) {
        throw new Exception("Failed to prepare delete query");
    }

    $stmt->bind_param("ii", $user_id, $product_id);

    if (!$stmt->execute()) {
        throw new Exception("Failed to remove item from cart");
    }

    /* =========================
       COMMIT
    ========================== */
    $conn->commit();

    $_SESSION['success'] = "Item removed from cart";

    header("Location: ../pages/view_cart.php");
    exit();

} catch (Exception $e) {

    /* =========================
       ROLLBACK
    ========================== */
    $conn->rollback();

    error_log("Remove Cart Error: " . $e->getMessage());

    $_SESSION['error'] = $e->getMessage();

    header("Location: ../pages/view_cart.php");
    exit();
}
?>