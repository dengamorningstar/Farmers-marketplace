<?php
session_start();
include("../includes/db.php");

/* =========================
   ADMIN ONLY
========================= */
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

/* =========================
   PAYMENT SUMMARY (SOURCE OF TRUTH)
========================= */
$payment_summary = $conn->query("
    SELECT 
        COUNT(*) AS total_payments,
        
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END),0) AS total_revenue,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END),0) AS paid_count,
        COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END),0) AS pending_count,
        COALESCE(SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END),0) AS failed_count
    FROM payments
")->fetch_assoc();

/* =========================
   PAYOUT SUMMARY (SEPARATE FINANCIAL LAYER)
========================= */
$payout_summary = $conn->query("
    SELECT 
        COUNT(*) AS total_payouts,
        COALESCE(SUM(CASE WHEN LOWER(TRIM(status)) = 'paid' THEN 1 ELSE 0 END),0) AS paid_payouts,
        COALESCE(SUM(CASE WHEN LOWER(TRIM(status)) = 'pending' THEN 1 ELSE 0 END),0) AS pending_payouts,
        COALESCE(SUM(CASE WHEN LOWER(TRIM(status)) = 'rejected' THEN 1 ELSE 0 END),0) AS rejected_payouts
    FROM payout_requests
")->fetch_assoc();

/* =========================
   RECENT PAYMENTS
========================= */
$stmt = $conn->prepare("
    SELECT 
        p.payment_id,
        p.order_id,
        p.amount,
        p.payment_method,
        p.payment_reference,
        p.payment_status,
        p.created_at,
        u.name AS buyer_name
    FROM payments p
    INNER JOIN users u ON p.user_id = u.user_id
    ORDER BY p.payment_id DESC
    LIMIT 50
");

$stmt->execute();
$result = $stmt->get_result();

/* =========================
   SAFE OUTPUT
========================= */
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function badgeClass($status) {
    return match (strtolower(trim($status))) {
        'paid' => 'paid',
        'pending' => 'pending',
        'failed' => 'failed',
        default => 'pending'
    };
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Payments</title>

<style>
body{
    font-family:Arial;
    margin:0;
    background:#f4f6f9;
}

.container{
    padding:20px;
}

.summary{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:15px;
    margin-bottom:20px;
}

.box{
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,.08);
}

.box h3{
    margin:0;
    font-size:16px;
    color:#555;
}

.box p{
    font-size:24px;
    font-weight:bold;
    margin-top:10px;
}

.card{
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,.08);
    overflow:auto;
}

table{
    width:100%;
    border-collapse:collapse;
}

th,td{
    padding:12px;
    border-bottom:1px solid #eee;
    text-align:left;
}

th{
    background:#333;
    color:white;
}

.badge{
    padding:5px 10px;
    border-radius:20px;
    color:white;
    font-size:12px;
}

.paid{ background:green; }
.pending{ background:orange; }
.failed{ background:red; }

.mpesa{ background:#28a745; }
.wallet{ background:#007bff; }
</style>
</head>

<body>

<?php include("../includes/navbar.php"); ?>

<div class="container">

<h2>💳 Admin Payments Dashboard</h2>

<!-- =========================
     PAYMENT SUMMARY
========================= -->
<div class="summary">

<div class="box">
<h3>Total Revenue</h3>
<p>KES <?php echo number_format($payment_summary['total_revenue'] ?? 0); ?></p>
</div>

<div class="box">
<h3>Paid Payments</h3>
<p><?php echo $payment_summary['paid_count'] ?? 0; ?></p>
</div>

<div class="box">
<h3>Pending Payments</h3>
<p><?php echo $payment_summary['pending_count'] ?? 0; ?></p>
</div>

<div class="box">
<h3>Failed Payments</h3>
<p><?php echo $payment_summary['failed_count'] ?? 0; ?></p>
</div>

</div>

<!-- =========================
     PAYOUT SUMMARY
========================= -->
<div class="summary">

<div class="box">
<h3>Total Payouts</h3>
<p><?php echo $payout_summary['total_payouts'] ?? 0; ?></p>
</div>

<div class="box">
<h3>Paid Payouts</h3>
<p><?php echo $payout_summary['paid_payouts'] ?? 0; ?></p>
</div>

<div class="box">
<h3>Pending Payouts</h3>
<p><?php echo $payout_summary['pending_payouts'] ?? 0; ?></p>
</div>

<div class="box">
<h3>Rejected Payouts</h3>
<p><?php echo $payout_summary['rejected_payouts'] ?? 0; ?></p>
</div>

</div>

<!-- =========================
     PAYMENTS TABLE
========================= -->
<div class="card">

<h3>Recent Payments</h3>

<table>

<tr>
    <th>ID</th>
    <th>Order</th>
    <th>Buyer</th>
    <th>Amount</th>
    <th>Method</th>
    <th>Reference</th>
    <th>Status</th>
    <th>Date</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>

<tr>
    <td><?php echo $row['payment_id']; ?></td>
    <td>#<?php echo $row['order_id']; ?></td>
    <td><?php echo e($row['buyer_name']); ?></td>
    <td>KES <?php echo number_format($row['amount']); ?></td>

    <td>
        <span class="badge <?php echo strtolower($row['payment_method']); ?>">
            <?php echo strtoupper($row['payment_method']); ?>
        </span>
    </td>

    <td><?php echo e($row['payment_reference'] ?? '-'); ?></td>

    <td>
        <span class="badge <?php echo badgeClass($row['payment_status']); ?>">
            <?php echo ucfirst($row['payment_status']); ?>
        </span>
    </td>

    <td><?php echo $row['created_at']; ?></td>
</tr>

<?php endwhile; ?>

</table>

</div>

</div>

</body>
</html>