<?php
session_start();
include("../includes/db.php");

/* =========================
   ADMIN ONLY
========================= */
if (
    !isset($_SESSION['user']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header("Location: login.php");
    exit();
}

/* =========================
   SUMMARY STATS
========================= */
$summary = $conn->query("
    SELECT 
        COUNT(*) AS total_requests,
        COALESCE(SUM(amount),0) AS total_amount,

        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count

    FROM payout_requests
")->fetch_assoc() ?? [];

/* =========================
   FARMER WALLET TOTAL
========================= */
$farmer_wallet = $conn->query("
    SELECT COALESCE(SUM(net_amount),0) AS total_balance
    FROM earnings
    WHERE status IN ('active','locked')
")->fetch_assoc() ?? [];

/* =========================
   DELIVERY WALLET TOTAL
========================= */
$delivery_wallet = $conn->query("
    SELECT COALESCE(SUM(amount),0) AS total_balance
    FROM delivery_earnings
    WHERE status IN ('active','locked')
")->fetch_assoc() ?? [];

/* =========================
   FETCH PAYOUT REQUESTS
========================= */
$stmt = $conn->prepare("
    SELECT 
        pr.*,
        u.name,
        u.role,

        CASE 
            WHEN u.role = 'farmer' THEN (
                SELECT COALESCE(SUM(e.net_amount),0)
                FROM earnings e
                WHERE e.farmer_id = pr.user_id
                  AND e.status IN ('active','locked')
            )

            WHEN u.role = 'delivery_person' THEN (
                SELECT COALESCE(SUM(de.amount),0)
                FROM delivery_earnings de
                WHERE de.delivery_person_id = pr.user_id
                  AND de.status IN ('active','locked')
            )

            ELSE 0
        END AS current_balance

    FROM payout_requests pr
    INNER JOIN users u ON pr.user_id = u.user_id
    ORDER BY pr.created_at DESC
");

$stmt->execute();
$result = $stmt->get_result();

/* =========================
   SAFE OUTPUT
========================= */
function e($v)
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Payout Dashboard</title>

<style>
body{
    font-family:Arial;
    background:#f4f6f9;
    margin:0;
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
    font-size:15px;
    color:#555;
}

.box p{
    font-size:24px;
    font-weight:bold;
    margin-top:10px;
}

.card{
    background:white;
    padding:18px;
    margin-bottom:15px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,.08);
}

.row{
    display:flex;
    justify-content:space-between;
    flex-wrap:wrap;
    gap:10px;
}

.badge{
    padding:5px 10px;
    border-radius:20px;
    color:white;
    font-size:12px;
    text-transform:capitalize;
    display:inline-block;
}

.pending{ background:orange; }
.approved{ background:#007bff; }
.paid{ background:green; }
.rejected{ background:red; }

.farmer{ background:#16a34a; }
.delivery_person{ background:#7c3aed; }

button{
    padding:10px 14px;
    border:none;
    border-radius:6px;
    color:white;
    cursor:pointer;
    margin-right:5px;
    margin-top:10px;
}

.approve{ background:green; }
.reject{ background:red; }
.pay{ background:#007bff; }

.meta{
    color:#666;
    font-size:14px;
    margin-top:6px;
}

.amount{
    font-size:22px;
    font-weight:bold;
}

.balance{
    color:#2563eb;
    font-weight:bold;
}

.disabled{
    background:#ccc !important;
    cursor:not-allowed;
}
</style>
</head>

<body>

<?php include("../includes/navbar.php"); ?>

<div class="container">

<h2>💰 Admin Payout Dashboard</h2>

<!-- SUMMARY -->
<div class="summary">

    <div class="box">
        <h3>Total Requests</h3>
        <p><?php echo $summary['total_requests'] ?? 0; ?></p>
    </div>

    <div class="box">
        <h3>Total Requested Amount</h3>
        <p>KES <?php echo number_format($summary['total_amount'] ?? 0, 2); ?></p>
    </div>

    <div class="box">
        <h3>Pending Requests</h3>
        <p><?php echo $summary['pending_count'] ?? 0; ?></p>
    </div>

    <div class="box">
        <h3>Approved Requests</h3>
        <p><?php echo $summary['approved_count'] ?? 0; ?></p>
    </div>

    <div class="box">
        <h3>Paid Requests</h3>
        <p><?php echo $summary['paid_count'] ?? 0; ?></p>
    </div>

    <div class="box">
        <h3>Rejected Requests</h3>
        <p><?php echo $summary['rejected_count'] ?? 0; ?></p>
    </div>

    <div class="box">
        <h3>Farmer Wallet Pool</h3>
        <p>KES <?php echo number_format($farmer_wallet['total_balance'] ?? 0, 2); ?></p>
    </div>

    <div class="box">
        <h3>Delivery Wallet Pool</h3>
        <p>KES <?php echo number_format($delivery_wallet['total_balance'] ?? 0, 2); ?></p>
    </div>

</div>

<!-- PAYOUT REQUESTS -->
<?php if ($result->num_rows > 0): ?>

    <?php while($row = $result->fetch_assoc()): ?>

        <div class="card">

            <div class="row">

                <div>
                    <h3><?php echo e($row['name']); ?></h3>

                    <div class="meta">
                        Request #<?php echo (int)$row['payout_id']; ?>
                    </div>

                    <div class="meta">
                        Created: <?php echo e($row['created_at']); ?>
                    </div>

                    <?php if (!empty($row['paid_at'])): ?>
                        <div class="meta">
                            Paid: <?php echo e($row['paid_at']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <span class="badge <?php echo e($row['role']); ?>">
                        <?php echo ucfirst(str_replace('_',' ',$row['role'])); ?>
                    </span>

                    <span class="badge <?php echo strtolower($row['status']); ?>">
                        <?php echo ucfirst($row['status']); ?>
                    </span>
                </div>

            </div>

            <hr style="margin:15px 0;border:0;border-top:1px solid #eee;">

            <div class="amount">
                KES <?php echo number_format($row['amount'],2); ?>
            </div>

            <div class="meta">
                Current Wallet Balance:
                <span class="balance">
                    KES <?php echo number_format($row['current_balance'] ?? 0,2); ?>
                </span>
            </div>

            <?php if ($row['status'] === 'pending'): ?>

                <form method="POST" action="../actions/process_payout.php">
                    <input type="hidden" name="payout_id" value="<?php echo (int)$row['payout_id']; ?>">

                    <button name="action" value="approve" class="approve">✅ Approve</button>
                    <button name="action" value="reject" class="reject">❌ Reject</button>
                </form>

            <?php elseif ($row['status'] === 'approved'): ?>

                <form method="POST" action="../actions/process_payout.php">
                    <input type="hidden" name="payout_id" value="<?php echo (int)$row['payout_id']; ?>">
                    <button name="action" value="pay" class="pay">💸 Mark As Paid</button>
                </form>

            <?php else: ?>

                <button class="disabled" disabled>
                    <?php echo strtoupper($row['status']); ?>
                </button>

            <?php endif; ?>

        </div>

    <?php endwhile; ?>

<?php else: ?>

    <div class="box">No payout requests found.</div>

<?php endif; ?>

</div>

</body>
</html>