<?php

session_start();
include("../includes/db.php");

header('Content-Type: application/json');

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode([
        "status" => "unauthorized"
    ]);
    exit();
}

$user_id = intval($_SESSION['user']);
$order_id = intval($_GET['order_id'] ?? 0);

if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "invalid_request"
    ]);
    exit();
}

/* =========================
   GET LATEST PAYMENT (UNCHANGED)
========================= */
$stmt = $conn->prepare("
    SELECT 
        payment_id,
        payment_status,
        amount,
        transaction_ref,
        payment_method,
        checkout_request_id,
        merchant_request_id,
        paid_at
    FROM payments
    WHERE order_id = ?
      AND user_id = ?
    ORDER BY payment_id DESC
    LIMIT 1
");

$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();

$payment = $stmt->get_result()->fetch_assoc();

/* =========================
   NO PAYMENT FOUND
========================= */
if (!$payment) {
    echo json_encode([
        "status" => "pending",
        "message" => "No payment initiated yet",
        "debug_state" => "no_payment_row"
    ]);
    exit();
}

/* =========================
   NORMALIZE STATUS
========================= */
$status = strtolower(trim($payment['payment_status'] ?? 'pending'));

/* =========================
   🔥 SAFE SYNC WITH ORDERS TABLE (NEW ADDITION ONLY)
   - Does NOT affect existing logic
   - Runs silently in background
========================= */
if ($status === 'paid') {

    $sync = $conn->prepare("
        UPDATE orders
        SET payment_status = 'paid'
        WHERE order_id = ?
          AND payment_status != 'paid'
    ");

    if ($sync) {
        $sync->bind_param("i", $order_id);
        $sync->execute();
    }
}

/* =========================
   DETECT STUCK PAYMENT (UNCHANGED)
========================= */
$is_stk_started = !empty($payment['checkout_request_id']);
$is_missing_callback = $is_stk_started && $status === 'pending';

/* =========================
   RESPONSE (UNCHANGED)
========================= */
$response = [
    "status" => $status,
    "amount" => $payment['amount'],
    "transaction_ref" => $payment['transaction_ref'],
    "method" => $payment['payment_method'],
    "checkout_request_id" => $payment['checkout_request_id'],
    "merchant_request_id" => $payment['merchant_request_id'],
    "paid_at" => $payment['paid_at'],

    "debug_state" => $is_missing_callback 
        ? "stk_sent_but_no_callback_yet" 
        : "normal"
];

/* =========================
   STATUS MESSAGES
========================= */
if ($status === 'paid') {
    $response["message"] = "Payment completed (callback confirmed)";
} elseif ($status === 'failed') {
    $response["message"] = "Payment failed";
} elseif ($is_missing_callback) {
    $response["message"] = "STK sent but awaiting M-Pesa callback";
} else {
    $response["message"] = "Awaiting payment initiation";
}

echo json_encode($response);
exit();
?>