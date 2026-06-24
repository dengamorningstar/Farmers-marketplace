<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include("../includes/db.php");

/* =========================
   LOGGING
========================= */
function logMessage($message)
{
    file_put_contents(
        __DIR__ . '/callback_log.txt',
        "[" . date("Y-m-d H:i:s") . "] " . $message . PHP_EOL,
        FILE_APPEND
    );
}

/* =========================
   READ CALLBACK
========================= */
$raw = file_get_contents('php://input');
logMessage("RAW CALLBACK: " . $raw);

$data = json_decode($raw, true);

if (!$data || !isset($data['Body']['stkCallback'])) {
    logMessage("INVALID CALLBACK");
    echo json_encode(["ResultCode" => 0, "ResultDesc" => "Accepted"]);
    exit();
}

$stk = $data['Body']['stkCallback'];

$resultCode = $stk['ResultCode'] ?? null;
$resultDesc = $stk['ResultDesc'] ?? null;
$checkoutRequestID = $stk['CheckoutRequestID'] ?? null;

if (!$checkoutRequestID) {
    logMessage("Missing CheckoutRequestID");
    echo json_encode(["ResultCode" => 0, "ResultDesc" => "Accepted"]);
    exit();
}

/* =========================
   FIND PAYMENT
========================= */
$stmt = $conn->prepare("
    SELECT payment_id, order_id, payment_status
    FROM payments
    WHERE checkout_request_id = ?
    LIMIT 1
");

$stmt->bind_param("s", $checkoutRequestID);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    logMessage("Payment not found");
    echo json_encode(["ResultCode" => 0, "ResultDesc" => "Accepted"]);
    exit();
}

$order_id = (int)$payment['order_id'];

/* =========================
   IDEMPOTENCY CHECK
========================= */
if (strtolower($payment['payment_status']) === 'paid') {
    logMessage("Already processed");
    echo json_encode(["ResultCode" => 0, "ResultDesc" => "Already processed"]);
    exit();
}

/* =========================
   FAILED PAYMENT
========================= */
if ($resultCode != 0) {

    $stmt = $conn->prepare("
        UPDATE payments
        SET payment_status = 'failed',
            callback_result_code = ?,
            callback_result_desc = ?
        WHERE checkout_request_id = ?
    ");

    $stmt->bind_param("sss", $resultCode, $resultDesc, $checkoutRequestID);
    $stmt->execute();

    logMessage("Payment FAILED");

    echo json_encode(["ResultCode" => 0, "ResultDesc" => "Accepted"]);
    exit();
}

/* =========================
   SUCCESS DATA EXTRACTION
========================= */
$items = $stk['CallbackMetadata']['Item'] ?? [];

$mpesaReceipt = null;
$phone = null;

foreach ($items as $item) {

    if (($item['Name'] ?? '') === "MpesaReceiptNumber") {
        $mpesaReceipt = $item['Value'] ?? null;
    }

    if (($item['Name'] ?? '') === "PhoneNumber") {
        $phone = $item['Value'] ?? null;
    }
}

$payment_ref = "ORDER_" . $order_id;

/* =========================
   TRANSACTION START
========================= */
$conn->begin_transaction();

try {

    /* =========================
       1. UPDATE PAYMENT
    ========================= */
    $stmt = $conn->prepare("
        UPDATE payments
        SET payment_status = 'paid',
            transaction_ref = ?,
            payment_reference = ?,
            callback_result_code = ?,
            callback_result_desc = ?,
            phone = ?,
            paid_at = NOW()
        WHERE checkout_request_id = ?
    ");

    $stmt->bind_param(
        "ssssss",
        $mpesaReceipt,
        $payment_ref,
        $resultCode,
        $resultDesc,
        $phone,
        $checkoutRequestID
    );

    $stmt->execute();

    logMessage("Payment updated");

    /* =========================
       2. UPDATE ORDER
    ========================= */
    $stmt = $conn->prepare("
        UPDATE orders
        SET payment_status = 'paid',
            status = 'confirmed'
        WHERE order_id = ?
    ");

    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    logMessage("Order confirmed");

    /* =========================
       3. UPDATE ORDER ITEMS
    ========================= */
    $stmt = $conn->prepare("
        UPDATE order_items
        SET status = 'processing'
        WHERE order_id = ?
    ");

    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    /* =========================
       4. NOTIFICATIONS
    ========================= */
    $infoStmt = $conn->prepare("
        SELECT o.user_id, p.farmer_id
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN products p ON oi.product_id = p.product_id
        WHERE o.order_id = ?
        LIMIT 1
    ");

    $infoStmt->bind_param("i", $order_id);
    $infoStmt->execute();
    $info = $infoStmt->get_result()->fetch_assoc();

    $buyer_id = $info['user_id'] ?? null;
    $farmer_id = $info['farmer_id'] ?? null;

    if ($buyer_id) {
        $msg = "Your order #$order_id has been confirmed.";
        $n = $conn->prepare("
            INSERT INTO notifications (user_id, message, type)
            VALUES (?, ?, 'order')
        ");
        $n->bind_param("is", $buyer_id, $msg);
        $n->execute();
    }

    if ($farmer_id) {
        $msg = "New paid order #$order_id received.";
        $n = $conn->prepare("
            INSERT INTO notifications (user_id, message, type)
            VALUES (?, ?, 'order')
        ");
        $n->bind_param("is", $farmer_id, $msg);
        $n->execute();
    }

    /* =========================
       5. STOCK DEDUCTION (SAFE)
    ========================= */
    $itemsStmt = $conn->prepare("
        SELECT product_id, quantity
        FROM order_items
        WHERE order_id = ?
    ");

    $itemsStmt->bind_param("i", $order_id);
    $itemsStmt->execute();
    $itemsRes = $itemsStmt->get_result();

    while ($row = $itemsRes->fetch_assoc()) {

        $qty = (int)$row['quantity'];
        $pid = (int)$row['product_id'];

        $stmtStock = $conn->prepare("
            UPDATE products
            SET quantity = quantity - ?
            WHERE product_id = ?
            AND quantity >= ?
        ");

        $stmtStock->bind_param("iii", $qty, $pid, $qty);
        $stmtStock->execute();
    }

    $conn->commit();

    logMessage("TRANSACTION SUCCESS");

} catch (Exception $e) {

    $conn->rollback();

    logMessage("ERROR: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        "ResultCode" => 1,
        "ResultDesc" => "Callback failed"
    ]);
    exit();
}

/* =========================
   RESPONSE TO M-PESA
========================= */
echo json_encode([
    "ResultCode" => 0,
    "ResultDesc" => "Accepted"
]);