<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("../includes/db.php");

/* =========================
   PROTECT FARMER ONLY
========================= */
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'farmer') {
    die("Unauthorized access");
}

$farmer_id = intval($_SESSION['user']);

/* =========================
   INPUT VALIDATION
========================= */
if (!isset($_POST['order_item_id'], $_POST['status'])) {
    die("Invalid request");
}

$order_item_id = intval($_POST['order_item_id']);
$new_status = trim($_POST['status']);

/* =========================
   VALID TRANSITIONS
========================= */
$valid_transitions = [
    'processing' => 'ready_for_pickup'
];

/* =========================
   START TRANSACTION
========================= */
$conn->begin_transaction();

try {

    /* =========================
       LOCK ITEM
    ========================== */
    $stmt = $conn->prepare("
        SELECT 
            oi.status,
            oi.order_id,
            o.user_id,
            o.payment_status,
            o.zone_id,
            p.farmer_id
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.order_id
        INNER JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_item_id = ?
        FOR UPDATE
    ");

    $stmt->bind_param("i", $order_item_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Order item not found");
    }

    $item = $result->fetch_assoc();

    /* =========================
       OWNERSHIP CHECK
    ========================== */
    if ((int)$item['farmer_id'] !== $farmer_id) {
        throw new Exception("Not your product/order item");
    }

    $current_status = trim($item['status']);
    $payment_status = trim($item['payment_status']);

    $order_id = (int)$item['order_id'];
    $buyer_id = (int)$item['user_id'];
    $zone_id = (int)$item['zone_id'];

    /* =========================
       PAYMENT CHECK
    ========================== */
    if ($payment_status !== 'paid') {
        throw new Exception("Payment not confirmed");
    }

    /* =========================
       PREVENT DUPLICATE UPDATE
    ========================== */
    if ($current_status === $new_status) {
        throw new Exception("Item already in this status");
    }

    /* =========================
       VALID TRANSITION CHECK
    ========================== */
    if (
        !isset($valid_transitions[$current_status]) ||
        $valid_transitions[$current_status] !== $new_status
    ) {
        throw new Exception("Invalid status transition");
    }

    /* =========================
       UPDATE ITEM STATUS
    ========================== */
    $update = $conn->prepare("
        UPDATE order_items
        SET status = ?
        WHERE order_item_id = ?
    ");

    $update->bind_param("si", $new_status, $order_item_id);
    $update->execute();

    /* =========================
       UPDATE ORDER STATUS
       (keep system consistent)
    ========================== */
    if ($new_status === 'ready_for_pickup') {

        $flag = $conn->prepare("
            UPDATE orders
            SET status = 'processing',
                zone_id = zone_id
            WHERE order_id = ?
        ");

        $flag->bind_param("i", $order_id);
        $flag->execute();
    }

    /* =========================
       NOTIFY BUYER
    ========================== */
    $message = "📦 Your order #{$order_id} is now ready for pickup";

    $notify = $conn->prepare("
        INSERT INTO notifications (user_id, message, type, priority)
        VALUES (?, ?, 'order_update', 'medium')
    ");

    $notify->bind_param("is", $buyer_id, $message);
    $notify->execute();

    $conn->commit();

    header("Location: ../pages/farmer_orders.php");
    exit();

} catch (Exception $e) {

    $conn->rollback();

    echo "
    <h3 style='color:red;text-align:center;margin-top:50px;'>
        ERROR: " . htmlspecialchars($e->getMessage()) . "
    </h3>
    ";
}
?>