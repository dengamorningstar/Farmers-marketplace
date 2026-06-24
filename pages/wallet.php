<?php
session_start();
include("../includes/db.php");
require_once(__DIR__ . "/../engines/earnings_engine.php");

/* =========================
   DEBUG MODE (TEMP)
========================= */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* =========================
   AUTH CHECK
========================= */
if (
    !isset($_SESSION['user']) ||
    !in_array($_SESSION['role'], ['farmer', 'delivery_person'])
) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user']);
$role    = $_SESSION['role'];

/* =========================
   SINGLE SOURCE OF TRUTH (ENGINE)
========================= */
$wallet = EarningsEngine::getWalletSummary($conn, $user_id, $role);

$available      = $wallet['available'] ?? 0;
$locked_balance = $wallet['locked'] ?? 0;
$paid           = $wallet['paid'] ?? 0;
$total_earned   = $wallet['total'] ?? 0;

/* =========================
   RECENT PAYOUT REQUESTS
========================= */
$stmt = $conn->prepare("
    SELECT *
    FROM payout_requests
    WHERE user_id = ?
      AND role = ?
    ORDER BY payout_id DESC
    LIMIT 10
");

$stmt->bind_param("is", $user_id, $role);
$stmt->execute();

$payouts = $stmt->get_result();

/* =========================
   RECENT EARNINGS
========================= */
if ($role === 'farmer') {

    $stmt = $conn->prepare("
        SELECT
            earning_id,
            order_id,
            net_amount AS earning_amount,
            status,
            created_at
        FROM earnings
        WHERE farmer_id = ?
        ORDER BY earning_id DESC
        LIMIT 10
    ");

} else {

    $stmt = $conn->prepare("
        SELECT
            delivery_earning_id AS earning_id,
            order_id,
            amount AS earning_amount,
            status,
            created_at
        FROM delivery_earnings
        WHERE delivery_person_id = ?
        ORDER BY delivery_earning_id DESC
        LIMIT 10
    ");
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$recent_earnings = $stmt->get_result();

/* =========================
   SAFE OUTPUT
========================= */
function e($value)
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html>
<head>

<title>Wallet Dashboard</title>

<style>
body{
    font-family:Arial;
    background:#f4f6f9;
    margin:0;
}

.container{
    padding:20px;
}

h2{
    margin-bottom:20px;
}

.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:15px;
    margin-bottom:25px;
}

.card{
    background:white;
    padding:20px;
    border-radius:12px;
    box-shadow:0 0 10px rgba(0,0,0,0.08);
}

.card h3{
    margin:0;
    color:#666;
    font-size:15px;
}

.card p{
    font-size:26px;
    font-weight:bold;
    margin-top:12px;
}

.green{ color:#16a34a; }
.orange{ color:#f59e0b; }
.blue{ color:#2563eb; }
.purple{ color:#7c3aed; }

.section{
    background:white;
    padding:20px;
    border-radius:12px;
    margin-bottom:20px;
    box-shadow:0 0 10px rgba(0,0,0,0.08);
}

.row{
    border-bottom:1px solid #eee;
    padding:14px 0;
}

.row:last-child{
    border-bottom:none;
}

.badge{
    padding:5px 10px;
    border-radius:20px;
    color:white;
    font-size:12px;
    display:inline-block;
    margin-top:6px;
    text-transform:uppercase;
}

.pending{ background:#f59e0b; }
.active{ background:#16a34a; }
.locked{ background:#2563eb; }
.paid{ background:#7c3aed; }
.rejected{ background:red; }

.button{
    display:inline-block;
    background:#16a34a;
    color:white;
    text-decoration:none;
    padding:12px 18px;
    border-radius:8px;
    font-weight:bold;
    margin-bottom:20px;
}

.button:hover{
    opacity:0.9;
}

.empty{
    color:#777;
    padding:10px 0;
}

.small{
    font-size:13px;
    color:#666;
}
</style>

</head>

<body>

<?php include("../includes/navbar.php"); ?>

<div class="container">

<h2>
    💰 <?php echo ucfirst(str_replace('_',' ',$role)); ?> Wallet Dashboard
</h2>

<!-- =========================
     WALLET SUMMARY
========================= -->
<div class="grid">

    <div class="card">
        <h3>Available Balance</h3>
        <p class="green">
            KES <?php echo number_format($available, 2); ?>
        </p>
    </div>

    <div class="card">
        <h3>Locked Balance</h3>
        <p class="orange">
            KES <?php echo number_format($locked_balance, 2); ?>
        </p>
    </div>

    <div class="card">
        <h3>Total Paid Out</h3>
        <p class="blue">
            KES <?php echo number_format($paid, 2); ?>
        </p>
    </div>

    <div class="card">
        <h3>Lifetime Earnings</h3>
        <p class="purple">
            KES <?php echo number_format($total_earned, 2); ?>
        </p>
    </div>

</div>

<!-- =========================
     WITHDRAW BUTTON
========================= -->
<?php if ($available > 0): ?>
<form method="POST" action="../actions/request_payout_action.php">
    <input type="hidden" name="amount" value="<?php echo $available; ?>">
    <button type="submit" class="button">
        💸 Request Withdrawal
    </button>
</form>
<?php endif; ?>

<!-- =========================
     PAYOUT REQUESTS
========================= -->
<div class="section">

<h3>📤 Recent Payout Requests</h3>

<?php if ($payouts && $payouts->num_rows > 0): ?>

    <?php while($row = $payouts->fetch_assoc()): ?>

    <div class="row">

        <strong>
            KES <?php echo number_format($row['amount'], 2); ?>
        </strong>
        <br>

        <span class="badge <?php echo strtolower($row['status']); ?>">
            <?php echo strtoupper($row['status']); ?>
        </span>

        <div class="small">
            Requested:
            <?php echo e($row['created_at']); ?>
        </div>

        <?php if (!empty($row['paid_at'])): ?>
        <div class="small">
            Paid:
            <?php echo e($row['paid_at']); ?>
        </div>
        <?php endif; ?>

    </div>

    <?php endwhile; ?>

<?php else: ?>

<div class="empty">
    No payout requests yet.
</div>

<?php endif; ?>

</div>

<!-- =========================
     RECENT EARNINGS
========================= -->
<div class="section">

<h3>📈 Recent Earnings</h3>

<?php if ($recent_earnings && $recent_earnings->num_rows > 0): ?>

    <?php while($earning = $recent_earnings->fetch_assoc()): ?>

    <div class="row">

        <strong>
            Order #<?php echo $earning['order_id']; ?>
        </strong>
        <br>

        KES <?php echo number_format($earning['earning_amount'], 2); ?>
        <br>

        <span class="badge <?php echo strtolower($earning['status']); ?>">
            <?php echo strtoupper($earning['status']); ?>
        </span>

        <div class="small">
            <?php echo e($earning['created_at']); ?>
        </div>

    </div>

    <?php endwhile; ?>

<?php else: ?>

<div class="empty">
    No earnings yet.
</div>

<?php endif; ?>

</div>

</div>

</body>
</html>