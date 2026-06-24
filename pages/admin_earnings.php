<?php
session_start();
include("../includes/db.php");

/* =========================
   ADMIN AUTH CHECK
========================= */
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

/* =========================
   FARMER EARNINGS (SOURCE OF TRUTH)
========================= */
$farmer_stats = $conn->query("
    SELECT 
        COUNT(*) AS total_records,
        COALESCE(SUM(amount),0) AS gross_sales,
        COALESCE(SUM(commission),0) AS total_commission,
        COALESCE(SUM(net_amount),0) AS farmer_payouts
    FROM earnings
")->fetch_assoc() ?? [
    'total_records' => 0,
    'gross_sales' => 0,
    'total_commission' => 0,
    'farmer_payouts' => 0
];

/* =========================
   DELIVERY EARNINGS
========================= */
$delivery_stats = $conn->query("
    SELECT
        COUNT(*) AS total_deliveries,

        COALESCE(SUM(amount),0) AS total_delivery_earnings,

        COALESCE(SUM(
            CASE WHEN status='pending'
            THEN amount ELSE 0 END
        ),0) AS pending_delivery_earnings,

        COALESCE(SUM(
            CASE WHEN status='active'
            THEN amount ELSE 0 END
        ),0) AS active_delivery_earnings,

        COALESCE(SUM(
            CASE WHEN status='locked'
            THEN amount ELSE 0 END
        ),0) AS locked_delivery_earnings,

        COALESCE(SUM(
            CASE WHEN status='paid_out'
            THEN amount ELSE 0 END
        ),0) AS paid_delivery_earnings,

        COALESCE(SUM(
            CASE WHEN status='rejected'
            THEN amount ELSE 0 END
        ),0) AS rejected_delivery_earnings

    FROM delivery_earnings
")->fetch_assoc() ?? [
    'total_deliveries' => 0,
    'total_delivery_earnings' => 0,
    'pending_delivery_earnings' => 0,
    'active_delivery_earnings' => 0,
    'locked_delivery_earnings' => 0,
    'paid_delivery_earnings' => 0,
    'rejected_delivery_earnings' => 0
];

/* =========================
   RECENT FARMER EARNINGS
========================= */
$farmer_earnings = $conn->query("
    SELECT 
        e.*,
        u.name AS farmer_name
    FROM earnings e
    LEFT JOIN users u
        ON e.farmer_id = u.user_id
    ORDER BY e.created_at DESC
    LIMIT 15
");

/* =========================
   RECENT DELIVERY EARNINGS
========================= */
$delivery_earnings = $conn->query("
    SELECT 
        de.*,
        u.name AS delivery_person_name
    FROM delivery_earnings de
    LEFT JOIN users u
        ON de.delivery_person_id = u.user_id
    ORDER BY de.created_at DESC
    LIMIT 15
");

/* =========================
   SAFE OUTPUT FUNCTION
========================= */
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function n($v) {
    return number_format((float)($v ?? 0), 2);
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Earnings Dashboard</title>

<style>

body{
    font-family:Arial;
    margin:0;
    background:#f4f6f9;
}

.container{
    padding:20px;
}

.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    gap:15px;
    margin-bottom:20px;
}

.card{
    background:white;
    padding:18px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,0.08);
}

.big{
    font-size:24px;
    font-weight:bold;
    margin-top:10px;
}

.green{ color:green; }
.blue{ color:#007BFF; }
.orange{ color:#ff9800; }
.red{ color:red; }
.purple{ color:#7c3aed; }

table{
    width:100%;
    border-collapse:collapse;
    background:white;
    border-radius:10px;
    overflow:hidden;
    margin-top:15px;
}

th, td{
    padding:12px;
    border-bottom:1px solid #eee;
    text-align:left;
}

th{
    background:#333;
    color:white;
}

.section-title{
    margin-top:30px;
}

.badge{
    padding:5px 10px;
    border-radius:20px;
    color:white;
    font-size:12px;
    text-transform:uppercase;
}

.pending{ background:#f59e0b; }
.active{ background:#16a34a; }
.locked{ background:#2563eb; }
.paid_out{ background:#7c3aed; }
.rejected{ background:#dc2626; }

</style>
</head>

<body>

<?php include("../includes/navbar.php"); ?>

<div class="container">

<h2>💰 Admin Earnings Dashboard</h2>

<!-- =========================
     SUMMARY
========================= -->
<div class="grid">

    <div class="card">
        <div>Total Marketplace Sales</div>
        <div class="big">
            KES <?php echo number_format($farmer_stats['gross_sales']); ?>
        </div>
    </div>

    <div class="card">
        <div>Platform Commission</div>
        <div class="big green">
            KES <?php echo number_format($farmer_stats['total_commission']); ?>
        </div>
    </div>

    <div class="card">
        <div>Farmer Payouts</div>
        <div class="big blue">
            KES <?php echo number_format($farmer_stats['farmer_payouts']); ?>
        </div>
    </div>

    <div class="card">
        <div>Total Delivery Earnings</div>
        <div class="big orange">
            KES <?php echo number_format($delivery_stats['total_delivery_earnings']); ?>
        </div>
    </div>

    <div class="card">
        <div>Pending Delivery Earnings</div>
        <div class="big red">
            KES <?php echo number_format($delivery_stats['pending_delivery_earnings']); ?>
        </div>
    </div>

    <div class="card">
        <div>Active Delivery Earnings</div>
        <div class="big green">
            KES <?php echo number_format($delivery_stats['active_delivery_earnings']); ?>
        </div>
    </div>

    <div class="card">
        <div>Locked Delivery Earnings</div>
        <div class="big blue">
            KES <?php echo number_format($delivery_stats['locked_delivery_earnings']); ?>
        </div>
    </div>

    <div class="card">
        <div>Paid Out Delivery Earnings</div>
        <div class="big purple">
            KES <?php echo number_format($delivery_stats['paid_delivery_earnings']); ?>
        </div>
    </div>

    <div class="card">
        <div>Rejected Delivery Earnings</div>
        <div class="big red">
            KES <?php echo number_format($delivery_stats['rejected_delivery_earnings']); ?>
        </div>
    </div>

    <div class="card">
        <div>Total Earnings Records</div>
        <div class="big">
            <?php echo $farmer_stats['total_records']; ?>
        </div>
    </div>

</div>

<!-- =========================
     FARMER EARNINGS
========================= -->
<div class="card">

<h3 class="section-title">
🌾 Recent Farmer Earnings (LIVE SOURCE)
</h3>

<table>

<tr>
    <th>ID</th>
    <th>Farmer</th>
    <th>Order</th>
    <th>Gross</th>
    <th>Commission</th>
    <th>Net</th>
    <th>Status</th>
    <th>Date</th>
</tr>

<?php if ($farmer_earnings && $farmer_earnings->num_rows > 0): ?>

    <?php while($row = $farmer_earnings->fetch_assoc()): ?>

<tr>

    <td><?php echo $row['earning_id']; ?></td>

    <td><?php echo e($row['farmer_name']); ?></td>

    <td>#<?php echo $row['order_id']; ?></td>

    <td>KES <?php echo n($row['amount']); ?></td>

    <td>KES <?php echo n($row['commission']); ?></td>

    <td>KES <?php echo n($row['net_amount']); ?></td>

    <td>
        <span class="badge <?php echo e($row['status']); ?>">
            <?php echo strtoupper($row['status']); ?>
        </span>
    </td>

    <td><?php echo $row['created_at']; ?></td>

</tr>

    <?php endwhile; ?>

<?php else: ?>

<tr>
    <td colspan="8">No farmer earnings found</td>
</tr>

<?php endif; ?>

</table>

</div>

<!-- =========================
     DELIVERY EARNINGS
========================= -->
<div class="card">

<h3 class="section-title">
🚚 Recent Delivery Earnings
</h3>

<table>

<tr>
    <th>ID</th>
    <th>Delivery Person</th>
    <th>Order</th>
    <th>Amount</th>
    <th>Status</th>
    <th>Date</th>
</tr>

<?php if ($delivery_earnings && $delivery_earnings->num_rows > 0): ?>

    <?php while($row = $delivery_earnings->fetch_assoc()): ?>

<tr>

    <td><?php echo $row['delivery_earning_id']; ?></td>

    <td><?php echo e($row['delivery_person_name']); ?></td>

    <td>#<?php echo $row['order_id']; ?></td>

    <td>KES <?php echo n($row['amount']); ?></td>

    <td>
        <span class="badge <?php echo e($row['status']); ?>">
            <?php echo strtoupper($row['status']); ?>
        </span>
    </td>

    <td><?php echo $row['created_at']; ?></td>

</tr>

    <?php endwhile; ?>

<?php else: ?>

<tr>
    <td colspan="6">No delivery earnings found</td>
</tr>

<?php endif; ?>

</table>

</div>

</div>

</body>
</html>