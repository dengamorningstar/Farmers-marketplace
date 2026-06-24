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
    die("Invalid request");
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
        p.payment_method

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
    die("Payment record not found");
}

/* SECURITY: ensure user owns payment */
if (intval($payment['user_id']) !== $user_id) {
    die("Unauthorized access");
}

/* =========================
   PAYMENT STATUS CHECK
========================= */
$payment_status = strtolower(trim($payment['payment_status'] ?? 'pending'));

if ($payment_status !== 'paid') {
    header("Location: payment_pending.php?order_id=" . $order_id);
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
    <title>Payment Successful</title>

    <style>
        body{
            font-family:Arial;
            background:#f4f6f9;
            margin:0;
        }

        .container{
            max-width:520px;
            margin:60px auto;
            background:white;
            padding:30px;
            border-radius:12px;
            box-shadow:0 0 15px rgba(0,0,0,0.1);
            text-align:center;
        }

        .success-icon{
            font-size:60px;
            color:#16a34a;
        }

        h2{
            color:#16a34a;
            margin-bottom:10px;
        }

        .subtitle{
            color:#374151;
            margin-bottom:20px;
        }

        .details{
            text-align:left;
            margin-top:20px;
            background:#f9fafb;
            padding:15px;
            border-radius:8px;
            font-size:14px;
        }

        .details p{
            margin:8px 0;
        }

        .btn{
            display:inline-block;
            margin-top:20px;
            padding:12px 18px;
            background:#16a34a;
            color:white;
            text-decoration:none;
            border-radius:8px;
            font-weight:bold;
        }

        .btn:hover{
            background:#15803d;
        }

        .secondary-btn{
            background:#2563eb;
        }

        .secondary-btn:hover{
            background:#1d4ed8;
        }
    </style>

</head>

<body>

<div class="container">

    <div class="success-icon">✅</div>

    <h2>Payment Successful</h2>

    <div class="subtitle">
        Your order has been confirmed and is being processed.
    </div>

    <div class="details">

        <p><strong>Order ID:</strong> #<?php echo e($payment['order_id']); ?></p>

        <p><strong>Amount Paid:</strong> 
            KES <?php echo number_format($payment['amount'], 2); ?>
        </p>

        <p><strong>Payment Method:</strong> 
            <?php echo e($payment['payment_method']); ?>
        </p>

        <p><strong>Transaction Ref:</strong> 
            <?php echo e($payment['transaction_ref'] ?? 'N/A'); ?>
        </p>

        <p><strong>Paid At:</strong> 
            <?php echo e($payment['paid_at'] ?? 'N/A'); ?>
        </p>

        <p><strong>Status:</strong> 
            <?php echo ucfirst(e($payment_status)); ?>
        </p>

    </div>

    <a href="dashboard.php" class="btn">
        🏠 Go to Dashboard
    </a>

    <a href="my_orders.php" class="btn secondary-btn">
        📦 View My Orders
    </a>

</div>

</body>
</html>