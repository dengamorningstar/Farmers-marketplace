<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("../includes/db.php");

/* =========================
   AUTH CHECK
========================= */
if (
    !isset($_SESSION['user']) ||
    ($_SESSION['role'] ?? '') !== 'delivery_person'
) {
    header("Location: ../pages/login.php");
    exit();
}

$delivery_person_id = intval($_SESSION['user']);

/* =========================
   INPUT VALIDATION
========================= */
$assignment_id = intval($_POST['assignment_id'] ?? 0);

if ($assignment_id <= 0) {
    die("Invalid request");
}

/* =========================
   TRANSACTION START
========================= */
$conn->begin_transaction();

try {

    /* =========================
       FETCH RIDER (ZONE FIXED)
    ========================== */
    $riderStmt = $conn->prepare("
        SELECT
            user_id,
            zone_id,
            account_status
        FROM users
        WHERE user_id = ?
          AND role = 'delivery_person'
        LIMIT 1
    ");

    $riderStmt->bind_param("i", $delivery_person_id);
    $riderStmt->execute();

    $rider = $riderStmt->get_result()->fetch_assoc();

    if (!$rider) {
        throw new Exception("Delivery rider not found");
    }

    if (strtolower(trim($rider['account_status'])) !== 'active') {
        throw new Exception("Delivery rider account inactive");
    }

    $rider_zone_id = (int)$rider['zone_id'];

    /* =========================
       FETCH & LOCK ASSIGNMENT
    ========================== */
    $stmt = $conn->prepare("
        SELECT
            assignment_id,
            order_id,
            order_item_id,
            delivery_person_id,
            zone_id,
            status
        FROM delivery_assignments
        WHERE assignment_id = ?
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new Exception("DB error: " . $conn->error);
    }

    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();

    $assignment = $stmt->get_result()->fetch_assoc();

    if (!$assignment) {
        throw new Exception("Delivery assignment not found");
    }

    /* =========================
       SECURITY CHECK
    ========================== */
    if ((int)$assignment['delivery_person_id'] !== $delivery_person_id) {
        throw new Exception("Unauthorized assignment access");
    }

    /* =========================
       ZONE VALIDATION
    ========================== */
    if ((int)$assignment['zone_id'] !== $rider_zone_id) {
        throw new Exception("You cannot accept deliveries outside your zone");
    }

    /* =========================
       STATUS VALIDATION
    ========================== */
    if ($assignment['status'] !== 'assigned') {
        throw new Exception("Delivery already accepted or invalid state");
    }

    $order_id = (int)$assignment['order_id'];
    $order_item_id = (int)$assignment['order_item_id'];

    /* =========================
       ACCEPT DELIVERY (FIXED STATUS)
    ========================== */
    $update = $conn->prepare("
        UPDATE delivery_assignments
        SET
            status = 'accepted',
            updated_at = NOW()
        WHERE assignment_id = ?
          AND status = 'assigned'
    ");

    $update->bind_param("i", $assignment_id);
    $update->execute();

    if ($update->affected_rows <= 0) {
        throw new Exception("Failed to accept delivery (possibly already taken)");
    }

    /* =========================
       UPDATE ORDER ITEM STATUS
    ========================== */
    $itemUpdate = $conn->prepare("
        UPDATE order_items
        SET status = 'accepted'
        WHERE order_item_id = ?
    ");

    $itemUpdate->bind_param("i", $order_item_id);
    $itemUpdate->execute();

    /* =========================
       GET BUYER
    ========================== */
    $info = $conn->prepare("
        SELECT user_id
        FROM orders
        WHERE order_id = ?
        LIMIT 1
    ");

    $info->bind_param("i", $order_id);
    $info->execute();

    $order = $info->get_result()->fetch_assoc();

    /* =========================
       NOTIFY BUYER
    ========================== */
    if ($order) {

        $msg = "🚚 Delivery rider has accepted your order #{$order_id}";

        $notify = $conn->prepare("
            INSERT INTO notifications
            (user_id, message, type, priority, created_at)
            VALUES (?, ?, 'delivery_update', 'medium', NOW())
        ");

        $notify->bind_param("is", $order['user_id'], $msg);
        $notify->execute();
    }

    /* =========================
       COMMIT
    ========================== */
    $conn->commit();

    header("Location: ../pages/delivery_orders.php?success=accepted");
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