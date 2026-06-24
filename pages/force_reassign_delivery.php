<?php
session_start();
include("../includes/db.php");

/* =========================
   SECURITY CHECK (ADMIN ONLY)
========================= */
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    exit("Unauthorized");
}

/* =========================
   VALIDATE INPUT
========================= */
if (!isset($_POST['order_item_id'], $_POST['delivery_person_id'])) {
    http_response_code(400);
    exit("Invalid request");
}

$order_item_id = intval($_POST['order_item_id']);
$new_rider_id = intval($_POST['delivery_person_id']);

/* =========================
   VERIFY RIDER EXISTS
========================= */
$checkRider = $conn->prepare("
    SELECT user_id 
    FROM users 
    WHERE user_id = ? AND role = 'delivery_person'
");
$checkRider->bind_param("i", $new_rider_id);
$checkRider->execute();

if ($checkRider->get_result()->num_rows === 0) {
    exit("Invalid rider");
}

/* =========================
   START TRANSACTION
========================= */
$conn->begin_transaction();

try {

    /* =========================
       LOCK ORDER ITEM
    ========================= */
    $stmt = $conn->prepare("
        SELECT delivery_status, status, delivery_person_id
        FROM order_items
        WHERE order_item_id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $order_item_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        throw new Exception("Order not found");
    }

    /* =========================
       STRICT RULES
    ========================= */

    if ($order['status'] !== 'completed') {
        throw new Exception("Order not ready for delivery");
    }

    if ($order['delivery_status'] === 'delivered') {
        throw new Exception("Cannot reassign completed delivery");
    }

    /* =========================
       SAFE REASSIGNMENT
    ========================= */
    $update = $conn->prepare("
        UPDATE order_items
        SET delivery_person_id = ?,
            delivery_status = 'assigned',
            assigned_at = NOW(),
            assignment_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
        WHERE order_item_id = ?
    ");

    $update->bind_param("ii", $new_rider_id, $order_item_id);
    $update->execute();

    if ($update->affected_rows === 0) {
        throw new Exception("Reassignment failed");
    }

    /* =========================
       NOTIFY NEW RIDER
    ========================= */
    $msg = "🚚 You have been reassigned a delivery.";

    $notify = $conn->prepare("
        INSERT INTO notifications (user_id, message)
        VALUES (?, ?)
    ");
    $notify->bind_param("is", $new_rider_id, $msg);
    $notify->execute();

    $conn->commit();

    echo "success";

} catch (Exception $e) {

    $conn->rollback();
    echo "error: " . $e->getMessage();
}
?>