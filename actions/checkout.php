<?php
session_start();
include("../includes/db.php");

if (!isset($_SESSION['user'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_id = intval($_SESSION['user']);

try {

    $conn->begin_transaction();

    /* =========================
       GET USER ZONE (DELIVERY ZONE)
    ========================= */
    $zoneStmt = $conn->prepare("
        SELECT zone_id
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $zoneStmt->bind_param("i", $user_id);
    $zoneStmt->execute();
    $userZone = $zoneStmt->get_result()->fetch_assoc();

    $order_zone_id = (int)($userZone['zone_id'] ?? 0);

    if ($order_zone_id <= 0) {
        throw new Exception("User zone not set. Please update your profile.");
    }

    /* =========================
       GET CART (WITH PRODUCT ZONE)
    ========================= */
    $stmt = $conn->prepare("
        SELECT 
            c.product_id,
            c.quantity,
            p.price,
            p.zone_id
        FROM cart c
        INNER JOIN products p ON p.product_id = c.product_id
        WHERE c.user_id = ?
        FOR UPDATE
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows <= 0) {
        throw new Exception("Cart is empty");
    }

    $items = [];
    $total = 0;

    while ($row = $result->fetch_assoc()) {

        $qty = (int)$row['quantity'];
        $price = (float)$row['price'];
        $product_zone = (int)$row['zone_id'];

        if ($qty <= 0) {
            throw new Exception("Invalid quantity in cart");
        }

        if ($price <= 0) {
            throw new Exception("Invalid product price detected");
        }

        /* =========================
           ENFORCE SINGLE ZONE RULE (PRODUCT ZONE ONLY FOR VALIDATION)
        ========================= */
        static $zone_id = null;

        if ($zone_id === null) {
            $zone_id = $product_zone;
        } else {
            if ($zone_id !== $product_zone) {
                throw new Exception("Cart contains products from multiple zones. Please checkout separately.");
            }
        }

        $total += ($price * $qty);

        $items[] = [
            'product_id' => (int)$row['product_id'],
            'quantity' => $qty,
            'price' => $price
        ];
    }

    if ($total <= 0) {
        throw new Exception("Invalid total calculated");
    }

    /* =========================
       CREATE ORDER (USER ZONE NOW USED)
    ========================= */
    $stmt = $conn->prepare("
        INSERT INTO orders 
        (user_id, total_amount, status, payment_status, zone_id, created_at)
        VALUES (?, ?, 'pending', 'pending', ?, NOW())
    ");

    $stmt->bind_param("idi", $user_id, $total, $order_zone_id);
    $stmt->execute();

    $order_id = $stmt->insert_id;

    /* =========================
       ORDER ITEMS
    ========================= */
    foreach ($items as $item) {

        $stmt = $conn->prepare("
            INSERT INTO order_items
            (order_id, product_id, quantity, price, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");

        $stmt->bind_param(
            "iiid",
            $order_id,
            $item['product_id'],
            $item['quantity'],
            $item['price']
        );

        $stmt->execute();
    }

    /* =========================
       PAYMENT RECORD
    ========================= */
    $stmt = $conn->prepare("
        INSERT INTO payments
        (order_id, user_id, amount, payment_method, payment_status, created_at)
        VALUES (?, ?, ?, 'mpesa', 'pending', NOW())
    ");

    $stmt->bind_param("iid", $order_id, $user_id, $total);
    $stmt->execute();

    /* =========================
       CLEAR CART
    ========================= */
    $clear = $conn->prepare("
        DELETE FROM cart WHERE user_id = ?
    ");

    $clear->bind_param("i", $user_id);
    $clear->execute();

    $conn->commit();

    header("Location: ../pages/payment_pending.php?order_id=$order_id");
    exit();

} catch (Exception $e) {

    $conn->rollback();
    die("Checkout failed: " . $e->getMessage());
}
?>