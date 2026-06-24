<?php
session_start();
include("../includes/db.php");

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user']);
$order_id = intval($_GET['order_id'] ?? 0);

if ($order_id <= 0) {
    die("Invalid request: missing order.");
}

/* =========================
   GET PAYMENT (SOURCE OF TRUTH)
========================= */
$stmt = $conn->prepare("
    SELECT 
        p.payment_id,
        p.order_id,
        p.user_id,
        p.amount,
        p.payment_status,
        p.transaction_ref,
        p.paid_at,
        p.created_at

    FROM payments p

    WHERE p.order_id = ?
    ORDER BY p.payment_id DESC
    LIMIT 1
");

$stmt->bind_param("i", $order_id);
$stmt->execute();

$payment = $stmt->get_result()->fetch_assoc();

/* =========================
   VALIDATION
========================= */
if (!$payment) {
    die("Payment record not found.");
}

/* SECURITY CHECK */
if (intval($payment['user_id']) !== $user_id) {
    die("Unauthorized access.");
}

$status = strtolower(trim($payment['payment_status'] ?? 'failed'));

/* =========================
   REDIRECT IF ACTUALLY PAID
========================= */
if ($status === 'paid') {
    header("Location: payment_success.php?order_id=" . $order_id);
    exit();
}

/* =========================
   SAFE OUTPUT
========================= */
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Failed</title>

    <style>
        body {
            font-family: Arial;
            background: #f8f9fa;
            margin: 0;
            padding: 50px;
            text-align: center;
        }

        .card {
            background: white;
            padding: 30px;
            max-width: 520px;
            margin: auto;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .icon {
            font-size: 60px;
            color: #dc3545;
        }

        h1 {
            color: #dc3545;
            margin-bottom: 10px;
        }

        .info {
            margin-top: 10px;
            color: #6b7280;
            font-size: 14px;
        }

        .status-box {
            margin-top: 15px;
            padding: 10px;
            border-radius: 8px;
            background: #fee2e2;
            color: #991b1b;
            font-weight: bold;
        }

        .btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 18px;
            background: #16a34a;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
        }

        .btn:hover {
            background: #15803d;
        }

        .btn-red {
            background: #dc2626;
        }

        .btn-red:hover {
            background: #b91c1c;
        }

        .secondary {
            background: #2563eb;
        }

        .secondary:hover {
            background: #1d4ed8;
        }

    </style>

</head>

<body>

<div class="card">

    <div class="icon">❌</div>

    <h1>Payment Not Completed</h1>

    <p>
        Your payment for Order <strong>#<?php echo e($order_id); ?></strong> was not successful.
    </p>

    <div class="status-box">
        Status: <?php echo strtoupper(e($status)); ?>
    </div>

    <p class="info">
        If you already completed payment, please wait a few seconds for confirmation.
    </p>

    <?php if (!empty($payment['transaction_ref'])): ?>
        <p class="info">
            Transaction Ref: <strong><?php echo e($payment['transaction_ref']); ?></strong>
        </p>
    <?php endif; ?>

    <a class="btn" href="payment_pending.php?order_id=<?php echo $order_id; ?>">
        🔁 Retry Payment
    </a>

    <a class="btn btn-red" href="my_orders.php">
        📦 My Orders
    </a>

    <a class="btn secondary" href="payment_success.php?order_id=<?php echo $order_id; ?>">
        ✅ Check Payment Status
    </a>

</div>

</body>
</html>