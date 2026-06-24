<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("../includes/db.php");

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'delivery_person') {
    http_response_code(403);
    die("Unauthorized");
}

$delivery_person_id = intval($_SESSION['user']);

/* =========================
   INPUT VALIDATION
========================= */
if (!isset($_POST['assignment_id'])) {
    die("Invalid request");
}

$assignment_id = intval($_POST['assignment_id']);

if ($assignment_id <= 0) {
    die("Invalid assignment ID");
}

/* =========================
   START TRANSACTION
========================= */
$conn->begin_transaction();

try {

    /* =========================
       LOCK ASSIGNMENT (ORDER LEVEL ONLY)
    ========================= */
    $stmt = $conn->prepare("
        SELECT
            da.assignment_id,
            da.order_id,
            da.status,
            da.delivery_person_id,

            o.user_id AS buyer_id,
            o.status AS order_status,

            u.name AS buyer_name

        FROM delivery_assignments da

        INNER JOIN orders o
            ON da.order_id = o.order_id

        INNER JOIN users u
            ON o.user_id = u.user_id

        WHERE da.assignment_id = ?
          AND da.delivery_person_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->bind_param("ii", $assignment_id, $delivery_person_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Assignment not found");
    }

    $assignment = $result->fetch_assoc();

    $current_status = strtolower(trim($assignment['status']));

    /* =========================
       DELIVERY FLOW (ORDER LEVEL)
    ========================= */
    $flow = [
        'assigned'   => 'accepted',
        'accepted'   => 'picked_up',
        'picked_up'  => 'in_transit',
        'in_transit' => 'delivered'
    ];

    if (!isset($flow[$current_status])) {
        throw new Exception("Delivery already completed or invalid state");
    }

    $next_status = $flow[$current_status];

    /* =========================
       UPDATE DELIVERY ASSIGNMENT
    ========================= */
    $update = $conn->prepare("
        UPDATE delivery_assignments
        SET
            status = ?,
            updated_at = NOW()
        WHERE assignment_id = ?
          AND delivery_person_id = ?
          AND status = ?
    ");

    $update->bind_param(
        "siis",
        $next_status,
        $assignment_id,
        $delivery_person_id,
        $current_status
    );

    $update->execute();

    if ($update->affected_rows <= 0) {
        throw new Exception("Failed to update delivery status");
    }

    /* =========================
       UPDATE ORDER STATUS (GLOBAL)
    ========================= */
    $orderUpdate = $conn->prepare("
        UPDATE orders
        SET status = ?
        WHERE order_id = ?
    ");

    $orderUpdate->bind_param("si", $next_status, $assignment['order_id']);
    $orderUpdate->execute();

    /* =========================
       BUYER NOTIFICATION
    ========================= */
    $buyer_message = "";

    switch ($next_status) {

        case 'accepted':
            $buyer_message = "🚚 Your order #{$assignment['order_id']} has been accepted by the delivery rider.";
            break;

        case 'picked_up':
            $buyer_message = "📦 Your order #{$assignment['order_id']} has been picked up.";
            break;

        case 'in_transit':
            $buyer_message = "🛵 Your order #{$assignment['order_id']} is on the way.";
            break;

        case 'delivered':
            $buyer_message = "✅ Your order #{$assignment['order_id']} has been delivered.";
            break;
    }

    if (!empty($buyer_message)) {

        $notify = $conn->prepare("
            INSERT INTO notifications (
                user_id,
                message,
                type,
                priority,
                created_at
            )
            VALUES (
                ?, ?, 'delivery_update', 'medium', NOW()
            )
        ");

        $notify->bind_param("is", $assignment['buyer_id'], $buyer_message);
        $notify->execute();
    }

    /* =========================
       FINAL COMPLETION HOOK
       (OPTIONAL FUTURE EARNINGS PROCESSING)
    ========================= */
    if ($next_status === 'delivered') {

        // You will later aggregate earnings per order here
        // NO order_item logic anymore

        $earnCheck = $conn->prepare("
            SELECT delivery_earning_id
            FROM delivery_earnings
            WHERE assignment_id = ?
            LIMIT 1
        ");

        $earnCheck->bind_param("i", $assignment_id);
        $earnCheck->execute();

        $exists = $earnCheck->get_result();

        if ($exists->num_rows === 0) {

            $amountStmt = $conn->prepare("
                SELECT SUM(price * quantity) AS total
                FROM order_items
                WHERE order_id = ?
            ");

            $amountStmt->bind_param("i", $assignment['order_id']);
            $amountStmt->execute();

            $total = $amountStmt->get_result()->fetch_assoc()['total'] ?? 0;

            $delivery_amount = round($total * 0.05, 2);

            $insertEarn = $conn->prepare("
                INSERT INTO delivery_earnings (
                    delivery_person_id,
                    order_id,
                    assignment_id,
                    amount,
                    status,
                    created_at
                )
                VALUES (
                    ?, ?, ?, ?, 'active', NOW()
                )
            ");

            $insertEarn->bind_param(
                "iiid",
                $delivery_person_id,
                $assignment['order_id'],
                $assignment_id,
                $delivery_amount
            );

            $insertEarn->execute();
        }
    }

    $conn->commit();

    header("Location: ../pages/delivery_orders.php");
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