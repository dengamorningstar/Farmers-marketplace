<?php

session_start();
header('Content-Type: application/json');

include("../includes/db.php");
include("../includes/config.php");

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = intval($_SESSION['user']);
$order_id = intval($_POST['order_id'] ?? 0);
$phone = trim($_POST['phone'] ?? '');

if ($order_id <= 0 || empty($phone)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request"]);
    exit();
}

/* =========================
   PHONE CLEANING
========================= */
$phone = preg_replace('/[^0-9]/', '', $phone);

if (substr($phone, 0, 1) === "0") {
    $phone = "254" . substr($phone, 1);
}

if (substr($phone, 0, 3) !== "254") {
    $phone = "254" . $phone;
}

if (!preg_match('/^2547\d{8}$/', $phone)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid phone format"]);
    exit();
}

/* =========================
   GET ORDER (SOURCE OF TRUTH)
========================= */
$stmt = $conn->prepare("
    SELECT o.order_id, o.payment_status,
           COALESCE(SUM(oi.price * oi.quantity),0) AS total_amount
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.order_id
    WHERE o.order_id = ? AND o.user_id = ?
    GROUP BY o.order_id
");

$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    http_response_code(404);
    echo json_encode(["error" => "Order not found"]);
    exit();
}

if (strtolower($order['payment_status']) === 'paid') {
    echo json_encode(["error" => "Order already paid"]);
    exit();
}

$amount = (float)$order['total_amount'];

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid order amount"]);
    exit();
}

/* =========================
   ENSURE PAYMENT ROW EXISTS (CLEAN DESIGN)
========================= */
$stmt = $conn->prepare("
    SELECT payment_id 
    FROM payments 
    WHERE order_id=? AND user_id=?
    LIMIT 1
");

$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$paymentExists = $stmt->get_result()->fetch_assoc();

if (!$paymentExists) {

    $stmt = $conn->prepare("
        INSERT INTO payments
        (order_id, user_id, amount, payment_method, payment_status, created_at)
        VALUES (?, ?, ?, 'mpesa', 'pending', NOW())
    ");

    $stmt->bind_param("iid", $order_id, $user_id, $amount);
    $stmt->execute();
}

/* =========================
   GET TOKEN
========================= */
$credentials = base64_encode(CONSUMER_KEY . ":" . CONSUMER_SECRET);

$ch = curl_init(TOKEN_URL);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => ["Authorization: Basic $credentials"],
    CURLOPT_RETURNTRANSFER => true,
]);

$response = curl_exec($ch);
curl_close($ch);

$token_result = json_decode($response, true);

if (!isset($token_result['access_token'])) {
    echo json_encode(["error" => "Token failed"]);
    exit();
}

$token = $token_result['access_token'];

$timestamp = date('YmdHis');
$password = base64_encode(SHORTCODE . PASSKEY . $timestamp);

/* =========================
   STK PUSH
========================= */
$stkPayload = [
    "BusinessShortCode" => SHORTCODE,
    "Password" => $password,
    "Timestamp" => $timestamp,
    "TransactionType" => "CustomerPayBillOnline",
    "Amount" => round($amount),
    "PartyA" => $phone,
    "PartyB" => SHORTCODE,
    "PhoneNumber" => $phone,
    "CallBackURL" => CALLBACK_URL,
    "AccountReference" => "ORDER_" . $order_id,
    "TransactionDesc" => "Marketplace Payment"
];

$ch = curl_init(STK_PUSH_URL);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer $token"
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($stkPayload),
    CURLOPT_RETURNTRANSFER => true,
]);

$response = curl_exec($ch);
curl_close($ch);

$res = json_decode($response, true);

if (!$res || ($res['ResponseCode'] ?? null) != "0") {
    echo json_encode([
        "error" => "STK failed",
        "raw" => $res
    ]);
    exit();
}

$checkout_id = $res['CheckoutRequestID'];
$merchant_request_id = $res['MerchantRequestID'] ?? null;

/* =========================
   UPDATE PAYMENT (NO ZONE - CLEAN ARCHITECTURE)
========================= */
$stmt = $conn->prepare("
    UPDATE payments
    SET checkout_request_id=?,
        merchant_request_id=?,
        amount=?,
        payment_status='pending'
    WHERE order_id=? AND user_id=?
");

$stmt->bind_param(
    "ssdii",
    $checkout_id,
    $merchant_request_id,
    $amount,
    $order_id,
    $user_id
);

$stmt->execute();

/* =========================
   RESPONSE
========================= */
echo json_encode([
    "success" => true,
    "checkout_id" => $checkout_id,
    "merchant_request_id" => $merchant_request_id
]);

exit();
?>