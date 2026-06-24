<?php
session_start();
include("../includes/db.php");

header("Content-Type: application/json");

/* =========================
   ADMIN AUTH CHECK
========================= */
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized access"
    ]);
    exit();
}

/* =========================
   VALIDATE INPUT
========================= */
if (!isset($_POST['order_id'], $_POST['delivery_person_id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing required fields"
    ]);
    exit();
}

$order_id = intval($_POST['order_id']);
$delivery_person_id = intval($_POST['delivery_person_id']);

/* =========================
   NOTIFICATION HELPER
========================= */
function notify($conn, $user_id, $message, $priority = 'medium')
{
    $stmt = $conn->prepare("
        INSERT INTO notifications
        (user_id, message, type, priority, created_at)
        VALUES (?, ?, 'delivery', ?, NOW())
    ");

    $stmt->bind_param("iss", $user_id, $message, $priority);
    $stmt->execute();
}

/* =========================
   START TRANSACTION
========================= */
$conn->begin_transaction();

try {

    /* =========================
       LOCK ORDER
    ========================= */
    $stmt = $conn->prepare("
        SELECT
            o.order_id,
            o.user_id AS buyer_id,
            o.zone_id,
            o.status,
            o.payment_status
        FROM orders o
        WHERE o.order_id = ?
        FOR UPDATE
    ");

    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        throw new Exception("Order not found");
    }

    if (strtolower(trim($order['payment_status'])) !== 'paid') {
        throw new Exception("Payment not completed");
    }

    if (!$order['zone_id']) {
        throw new Exception("Order zone missing");
    }

    $buyer_id = (int)$order['buyer_id'];
    $zone_id = (int)$order['zone_id'];

    /* =========================
       VERIFY DELIVERY PERSON
    ========================= */
    $stmt = $conn->prepare("
        SELECT
            user_id,
            name,
            zone_id,
            account_status
        FROM users
        WHERE user_id = ?
          AND role = 'delivery_person'
        LIMIT 1
    ");

    $stmt->bind_param("i", $delivery_person_id);
    $stmt->execute();
    $delivery = $stmt->get_result()->fetch_assoc();

    if (!$delivery) {
        throw new Exception("Invalid delivery person");
    }

    if (strtolower(trim($delivery['account_status'])) !== 'active') {
        throw new Exception("Delivery person inactive");
    }

    if ((int)$delivery['zone_id'] !== $zone_id) {
        throw new Exception("Zone mismatch for delivery person");
    }

    /* =========================
       CHECK IF ORDER ALREADY ASSIGNED (IMPORTANT FIX)
    ========================= */
    $stmt = $conn->prepare("
        SELECT assignment_id
        FROM delivery_assignments
        WHERE order_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $existing = $stmt->get_result();

    if ($existing->num_rows > 0) {
        throw new Exception("This order is already assigned to a delivery person");
    }

    /* =========================
       CREATE ASSIGNMENT (ORDER LEVEL ONLY)
    ========================= */
    $stmt = $conn->prepare("
        INSERT INTO delivery_assignments
        (
            order_id,
            delivery_person_id,
            assigned_by,
            zone_id,
            status,
            created_at
        )
        VALUES
        (?, ?, ?, ?, 'assigned', NOW())
    ");

    $admin_id = intval($_SESSION['user']);

    $stmt->bind_param(
        "iiii",
        $order_id,
        $delivery_person_id,
        $admin_id,
        $zone_id
    );

    $stmt->execute();

    $assignment_id = $conn->insert_id;

    if (!$assignment_id) {
        throw new Exception("Assignment failed");
    }

    /* =========================
       UPDATE ALL ORDER ITEMS
    ========================= */
    $stmt = $conn->prepare("
        UPDATE order_items
        SET status = 'assigned'
        WHERE order_id = ?
    ");

    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    /* =========================
       UPDATE ORDER STATUS
    ========================= */
    $stmt = $conn->prepare("
        UPDATE orders
        SET status = 'assigned'
        WHERE order_id = ?
    ");

    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    /* =========================
       NOTIFICATIONS
    ========================= */
    notify(
        $conn,
        $delivery_person_id,
        "🚚 New delivery assigned for Order #$order_id",
        "high"
    );

    notify(
        $conn,
        $buyer_id,
        "📦 Your order #$order_id has been assigned for delivery",
        "medium"
    );

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Delivery assigned successfully",
        "assignment_id" => $assignment_id,
        "order_id" => $order_id
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>