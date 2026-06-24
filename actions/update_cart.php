<?php
session_start();
include("../includes/db.php");

if (!isset($_SESSION['user'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_id = intval($_SESSION['user']);

if (!isset($_POST['product_id'], $_POST['quantity'])) {
    $_SESSION['error'] = "Invalid request";
    header("Location: ../pages/view_cart.php");
    exit();
}

$product_id = intval($_POST['product_id']);
$new_quantity = intval($_POST['quantity']);

if ($product_id <= 0 || $new_quantity <= 0) {
    $_SESSION['error'] = "Invalid quantity";
    header("Location: ../pages/view_cart.php");
    exit();
}

$conn->begin_transaction();

try {

    // LOCK PRODUCT
    $stmt = $conn->prepare("
        SELECT quantity, price, status
        FROM products
        WHERE product_id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();

    if (!$product) {
        throw new Exception("Product not found");
    }

    if ($product['status'] !== 'active') {
        throw new Exception("Product not active");
    }

    $stock = intval($product['quantity']);

    if ($new_quantity > $stock) {
        throw new Exception("Only $stock items available");
    }

    // CHECK IF ITEM EXISTS IN CART
    $stmt = $conn->prepare("
        SELECT cart_id
        FROM cart
        WHERE user_id = ? AND product_id = ?
    ");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();

    if ($exists) {

        // UPDATE EXISTING ROW
        $stmt = $conn->prepare("
            UPDATE cart
            SET quantity = ?, updated_at = NOW()
            WHERE user_id = ? AND product_id = ?
        ");
        $stmt->bind_param("iii", $new_quantity, $user_id, $product_id);
        $stmt->execute();

    } else {

        // INSERT IF NOT EXISTS
        $stmt = $conn->prepare("
            INSERT INTO cart (user_id, product_id, quantity, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iii", $user_id, $product_id, $new_quantity);
        $stmt->execute();
    }

    $conn->commit();

    $_SESSION['success'] = "Cart updated successfully";
    header("Location: ../pages/view_cart.php");
    exit();

} catch (Exception $e) {

    $conn->rollback();

    $_SESSION['error'] = $e->getMessage();
    header("Location: ../pages/view_cart.php");
    exit();
}
?>