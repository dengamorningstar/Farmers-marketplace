<?php
session_start();
include("../includes/db.php");

header("Content-Type: application/json");

/* =========================
   SYSTEM DISABLED NOTICE
   FCFS DELIVERY SYSTEM REMOVED
========================= */
echo json_encode([
    "status" => "disabled",
    "message" => "FCFS delivery system is disabled. Use admin assignment system instead."
]);

exit();

/* =========================================================
   ❌ OLD CODE BELOW (DISABLED - DO NOT EXECUTE)
   KEPT FOR REFERENCE ONLY
========================================================= */

/*
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized"
    ]);
    exit();
}

if (!isset($_POST['order_item_id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Order item ID required"
    ]);
    exit();
}

$order_item_id = intval($_POST['order_item_id']);

if ($order_item_id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid order item ID"
    ]);
    exit();
}

$conn->begin_transaction();

try {

    $stmt = $conn->prepare("
        SELECT 
            oi.order_item_id,
            oi.delivery_status,
            oi.payment_status,
            o.user_id,
            u.location AS buyer_location
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.order_id
        INNER JOIN users u ON o.user_id = u.user_id
        WHERE oi.order_item_id = ?
        FOR UPDATE
    ");

    $stmt->bind_param("i", $order_item_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Order item not found");
    }

    $row = $result->fetch_assoc();

    if ($row['payment_status'] !== 'paid') {
        throw new Exception("Payment not completed");
    }

    if ($row['delivery_status'] !== 'pending') {
        throw new Exception("Delivery already posted or invalid state");
    }

    $allowed_zones = ['Githurai', 'Ruiru', 'Juja'];

    if (empty($row['buyer_location']) || !in_array($row['buyer_location'], $allowed_zones)) {
        throw new Exception("Delivery only allowed in Kiambu zones");
    }

    $zoneStmt = $conn->prepare("
        SELECT zone_id
        FROM delivery_zones
        WHERE zone_name = ?
        LIMIT 1
    ");

    $zoneStmt->bind_param("s", $row['buyer_location']);
    $zoneStmt->execute();
    $zoneRes = $zoneStmt->get_result();

    if ($zoneRes->num_rows === 0) {
        throw new Exception("Delivery zone not configured");
    }

    $zone_id = $zoneRes->fetch_assoc()['zone_id'];

    $check = $conn->prepare("
        SELECT delivery_id
        FROM deliveries
        WHERE order_item_id = ?
        LIMIT 1
    ");

    $check->bind_param("i", $order_item_id);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        throw new Exception("Delivery job already exists");
    }

    $insert = $conn->prepare("
        INSERT INTO deliveries (
            order_item_id,
            zone_id,
            status,
            expires_at,
            created_at
        )
        VALUES (
            ?, ?, 'available',
            DATE_ADD(NOW(), INTERVAL 10 MINUTE),
            NOW()
        )
    ");

    $insert->bind_param("ii", $order_item_id, $zone_id);
    $insert->execute();

    $update = $conn->prepare("
        UPDATE order_items
        SET delivery_status = 'offered'
        WHERE order_item_id = ?
    ");

    $update->bind_param("i", $order_item_id);
    $update->execute();

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Delivery job posted successfully"
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
*/
?>