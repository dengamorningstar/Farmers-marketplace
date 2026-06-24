<?php
session_start();
include("../includes/db.php");
require_once(__DIR__ . "/../engines/earnings_engine.php");

/* =========================
   AUTH CHECK (FARMER + DELIVERY)
========================= */
if (!isset($_SESSION['user']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user']);
$role    = $_SESSION['role'];

/* =========================
   SINGLE SOURCE OF TRUTH (ENGINE)
========================= */
$balance = EarningsEngine::getAvailableBalance($conn, $user_id, $role);
?>

<!DOCTYPE html>
<html>
<head>
<title>Request Payout</title>

<style>
body{
    font-family:Arial;
    background:#f4f6f9;
    margin:0;
}

.container{
    padding:20px;
    max-width:500px;
    margin:auto;
}

.card{
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,0.08);
}

input,button{
    width:100%;
    padding:12px;
    margin-top:10px;
    border-radius:6px;
    border:1px solid #ccc;
    box-sizing:border-box;
}

input:focus{
    outline:none;
    border-color:#16a34a;
}

button{
    background:#16a34a;
    color:white;
    border:none;
    cursor:pointer;
    font-weight:bold;
}

button:hover{
    background:#15803d;
}

.balance{
    font-size:22px;
    font-weight:bold;
    color:#059669;
    margin-bottom:15px;
}

.warning{
    background:#fef3c7;
    color:#92400e;
    padding:10px;
    border-radius:6px;
    margin-top:15px;
    font-size:13px;
}

.note{
    font-size:13px;
    color:#555;
    margin-top:15px;
    line-height:1.5;
}

.badge{
    display:inline-block;
    padding:5px 10px;
    border-radius:20px;
    font-size:12px;
    color:white;
    margin-top:5px;
}

.active{ background:#16a34a; }
.locked{ background:#2563eb; }
.paid{ background:#7c3aed; }
.pending{ background:#f59e0b; }
</style>
</head>

<body>

<?php include("../includes/navbar.php"); ?>

<div class="container">

<div class="card">

<h2>
💰 Request Withdrawal
(<?php echo ucfirst($role); ?>)
</h2>

<p class="balance">
Available Balance: KES <?php echo number_format($balance, 2); ?>
</p>

<!-- =========================
     NO BALANCE
========================= -->
<?php if ($balance <= 0): ?>

    <div class="warning">
        You currently have no <b>withdrawable earnings</b>.
    </div>

<?php else: ?>

    <form action="../actions/request_payout_action.php" method="POST">

        <label>Withdrawal Amount (KES)</label>

        <input
            type="number"
            name="amount"
            min="1"
            max="<?php echo $balance; ?>"
            step="0.01"
            required
        >

        <button type="submit">
            Request Payout
        </button>

    </form>

<?php endif; ?>

<!-- =========================
     EXPLANATION
========================= -->

<p class="note">

This withdrawal system works for both:
<ul>
    <li><b>Farmers</b> → crop/product earnings</li>
    <li><b>Delivery persons</b> → delivery commissions</li>
</ul>

Process:
<ul>
    <li>Earnings become <b>active</b> (available)</li>
    <li>Request → becomes <b>locked</b></li>
    <li>Admin approves → payout processed</li>
    <li>Final payout → marked <b>paid</b></li>
</ul>

</p>

</div>

</div>

</body>
</html>