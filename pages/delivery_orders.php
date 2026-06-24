<?php
session_start();
include("../includes/db.php");

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'delivery_person') {
    header("Location: login.php");
    exit();
}

$delivery_person_id = intval($_SESSION['user']);

/* =========================
   EARNINGS HISTORY
========================= */
$earnings_stmt = $conn->prepare("
    SELECT 
        delivery_earning_id,
        order_id,
        amount,
        status,
        created_at
    FROM delivery_earnings
    WHERE delivery_person_id = ?
    ORDER BY delivery_earning_id DESC
    LIMIT 20
");

$earnings_stmt->bind_param("i", $delivery_person_id);
$earnings_stmt->execute();
$earnings_result = $earnings_stmt->get_result();

/* =========================
   EARNINGS SUMMARY
========================= */
$earnings_summary = $conn->query("
    SELECT 
        COALESCE(SUM(amount),0) AS total_earned,
        COALESCE(SUM(CASE WHEN status='pending' THEN amount ELSE 0 END),0) AS pending_earnings,
        COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) AS paid_earnings
    FROM delivery_earnings
    WHERE delivery_person_id = $delivery_person_id
")->fetch_assoc();

/* =========================
   MY DELIVERY ASSIGNMENTS (ORDER-LEVEL ONLY)
========================= */
$stmt = $conn->prepare("
    SELECT 
        da.assignment_id,
        da.status AS delivery_status,
        da.zone_id,
        da.created_at,
        da.updated_at,

        o.order_id,
        o.user_id AS buyer_id,

        u.name AS buyer_name,
        u.specific_location AS buyer_specific_location,

        dz.zone_key,
        dz.zone_label

    FROM delivery_assignments da

    INNER JOIN orders o
        ON da.order_id = o.order_id

    INNER JOIN users u
        ON o.user_id = u.user_id

    INNER JOIN delivery_zones dz
        ON da.zone_id = dz.zone_id

    WHERE da.delivery_person_id = ?

    ORDER BY da.assignment_id DESC
");

$stmt->bind_param("i", $delivery_person_id);
$stmt->execute();
$result = $stmt->get_result();

/* =========================
   DELIVERY FLOW
========================= */
$flow = [
    'assigned'   => 'accepted',
    'accepted'   => 'picked_up',
    'picked_up'  => 'in_transit',
    'in_transit' => 'delivered'
];
?>

<!DOCTYPE html>
<html>
<head>
<title>Delivery Dashboard</title>

<style>
body{
    font-family:Arial;
    margin:0;
    background:#f4f6f9;
}
.container{padding:20px;}
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(330px,1fr));
    gap:15px;
}
.card{
    background:white;
    padding:18px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,0.08);
}
.status{
    padding:6px 10px;
    border-radius:5px;
    color:white;
    font-size:12px;
    text-transform:uppercase;
    display:inline-block;
}
.assigned{ background:#17a2b8; }
.accepted{ background:#007bff; }
.picked_up{ background:orange; }
.in_transit{ background:purple; }
.delivered{ background:green; }

button{
    width:100%;
    padding:11px;
    margin-top:12px;
    border:none;
    border-radius:6px;
    color:white;
    cursor:pointer;
    font-weight:bold;
}
.btn-green{ background:#28a745; }
.btn-green:hover{ background:#218838; }

.earn-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:15px;
    margin-bottom:25px;
}

.stat{
    background:white;
    padding:18px;
    border-radius:10px;
    text-align:center;
    box-shadow:0 0 10px rgba(0,0,0,0.08);
}

.stat h4{margin:0;color:#666;font-size:14px;}
.stat p{font-size:24px;font-weight:bold;margin-top:10px;}

.zone{
    background:#eef2ff;
    padding:6px 10px;
    border-radius:5px;
    display:inline-block;
    font-size:12px;
    margin-top:5px;
}

.location-box{
    background:#f9fafb;
    border-left:4px solid #16a34a;
    padding:10px;
    margin-top:10px;
    border-radius:6px;
    font-size:13px;
}

.progress{
    margin-top:15px;
    padding:10px;
    background:#f8f9fa;
    border-radius:6px;
    font-size:13px;
}
</style>
</head>

<body>

<?php include("../includes/navbar.php"); ?>

<div class="container">

<h2>🚚 Delivery Dashboard</h2>

<!-- EARNINGS SUMMARY -->
<h3>💰 Earnings Summary</h3>

<div class="earn-grid">

    <div class="stat">
        <h4>Total Earned</h4>
        <p>KES <?php echo number_format($earnings_summary['total_earned'] ?? 0,2); ?></p>
    </div>

    <div class="stat">
        <h4>Pending Earnings</h4>
        <p>KES <?php echo number_format($earnings_summary['pending_earnings'] ?? 0,2); ?></p>
    </div>

    <div class="stat">
        <h4>Paid Earnings</h4>
        <p>KES <?php echo number_format($earnings_summary['paid_earnings'] ?? 0,2); ?></p>
    </div>

</div>

<!-- ACTIVE DELIVERIES -->
<h3>📦 My Deliveries</h3>

<div class="grid">

<?php if ($result->num_rows > 0): ?>

    <?php while($row = $result->fetch_assoc()): ?>

        <?php
        $status = strtolower($row['delivery_status']);
        $next = $flow[$status] ?? null;
        ?>

        <div class="card">

            <h3>Order #<?php echo (int)$row['order_id']; ?></h3>

            <p><span class="label">Buyer:</span> <?php echo htmlspecialchars($row['buyer_name']); ?></p>

            <div class="zone">
                📍 Zone: <?php echo htmlspecialchars($row['zone_label']); ?>
            </div>

            <div class="location-box">
                <strong>Buyer Location:</strong><br>
                <?php echo htmlspecialchars($row['buyer_specific_location'] ?: 'Not provided'); ?>
            </div>

            <div class="progress">
                <p><span class="label">Status:</span></p>
                <span class="status <?php echo $status; ?>">
                    <?php echo strtoupper(str_replace('_',' ', $status)); ?>
                </span>
            </div>

            <p style="font-size:12px;color:#777;">
                Assigned: <?php echo $row['created_at']; ?>
            </p>

            <?php if ($next): ?>

                <form action="../actions/update_delivery_status.php" method="POST">
                    <input type="hidden" name="assignment_id" value="<?php echo $row['assignment_id']; ?>">
                    <button class="btn-green">
                        Mark as <?php echo strtoupper(str_replace('_',' ', $next)); ?>
                    </button>
                </form>

            <?php else: ?>

                <button disabled>✔ DELIVERY COMPLETED</button>

            <?php endif; ?>

        </div>

    <?php endwhile; ?>

<?php else: ?>

    <div class="card">No assigned deliveries available.</div>

<?php endif; ?>

</div>

<!-- EARNINGS HISTORY -->
<h3>📜 Earnings History</h3>

<div class="grid">

<?php if ($earnings_result->num_rows > 0): ?>

    <?php while($row = $earnings_result->fetch_assoc()): ?>

        <div class="card">

            <h3>Order #<?php echo (int)$row['order_id']; ?></h3>

            <p>KES <?php echo number_format($row['amount'],2); ?></p>

            <span class="status <?php echo strtolower($row['status']); ?>">
                <?php echo strtoupper($row['status']); ?>
            </span>

            <p style="font-size:12px;color:#777;">
                <?php echo $row['created_at']; ?>
            </p>

        </div>

    <?php endwhile; ?>

<?php else: ?>

    <div class="card">No earnings yet.</div>

<?php endif; ?>

</div>

</div>

</body>
</html>