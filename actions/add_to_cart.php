<?php
session_start();
include("../includes/db.php");

/* =========================
   PROTECT LOGIN
========================= */
if (!isset($_SESSION['user'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_id = intval($_SESSION['user']);

/* =========================
   VALIDATE INPUT
========================= */
if (!isset($_POST['product_id'], $_POST['quantity'])) {
    die("Invalid request");
}

$product_id = intval($_POST['product_id']);
$quantity   = intval($_POST['quantity']);

if ($product_id <= 0 || $quantity <= 0) {
    die("Invalid quantity selected");
}

/* =========================
   START TRANSACTION
========================= */
$conn->begin_transaction();

try {

    /* =========================
       GET LIVE PRODUCT DATA
    ========================= */
    $stmt = $conn->prepare("
        SELECT quantity, farmer_id, zone_id, name
        FROM products
        WHERE product_id = ?
        FOR UPDATE
    ");

    $stmt->bind_param("i", $product_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Product not found");
    }

    $product = $result->fetch_assoc();

    $available_stock = intval($product['quantity']);
    $farmer_id       = intval($product['farmer_id']);
    $product_zone    = intval($product['zone_id']);

    /* =========================
       PREVENT BUYING OWN ITEM
    ========================= */
    if ($user_id === $farmer_id) {
        throw new Exception("You cannot add your own product to cart");
    }

    /* =========================
       CHECK EXISTING CART ITEM (PER PRODUCT)
    ========================= */
    $stmt = $conn->prepare("
        SELECT quantity
        FROM cart
        WHERE user_id = ? AND product_id = ?
        FOR UPDATE
    ");

    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();

    $cartResult = $stmt->get_result();

    $cartQty = 0;

    if ($cartResult->num_rows > 0) {
        $cartRow = $cartResult->fetch_assoc();
        $cartQty = intval($cartRow['quantity']);
    }

    /* =========================
       FINAL STOCK VALIDATION
    ========================= */
    $newCartQty = $cartQty + $quantity;

    if ($newCartQty > $available_stock) {
        throw new Exception("Only $available_stock item(s) available in stock");
    }

    /* =========================
       UPDATE OR INSERT CART
    ========================= */
    if ($cartQty > 0) {

        $stmt = $conn->prepare("
            UPDATE cart
            SET quantity = ?
            WHERE user_id = ? AND product_id = ?
        ");

        $stmt->bind_param("iii", $newCartQty, $user_id, $product_id);

    } else {

        $stmt = $conn->prepare("
            INSERT INTO cart (user_id, product_id, quantity)
            VALUES (?, ?, ?)
        ");

        $stmt->bind_param("iii", $user_id, $product_id, $quantity);
    }

    $stmt->execute();

    $conn->commit();

    header("Location: ../pages/view_cart.php");
    exit();

} catch (Exception $e) {

    $conn->rollback();
    die("Cart error: " . $e->getMessage());
}
?>