<?php
session_start();
include("../includes/db.php");

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
   GET ORDER
========================= */
$stmt = $conn->prepare("
    SELECT order_id, total_amount
    FROM orders
    WHERE order_id = ? AND user_id = ?
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Invalid order.");
}

/* =========================
   GET LATEST PAYMENT (SOURCE OF TRUTH)
========================= */
$stmt = $conn->prepare("
    SELECT payment_status, amount
    FROM payments
    WHERE order_id = ?
    ORDER BY payment_id DESC
    LIMIT 1
");
$stmt->bind_param("i", $order_id);
$stmt->execute();

$payment = $stmt->get_result()->fetch_assoc();

$payment_status = strtolower($payment['payment_status'] ?? 'pending');
?>

<!DOCTYPE html>
<html>
<head>
<title>Payment</title>

<style>
body{
    font-family: Arial;
    background:#f4f6f9;
    margin:0;
}

.container{
    max-width:500px;
    margin:50px auto;
    background:white;
    padding:25px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,0.1);
}

h2{text-align:center;}

.amount{
    text-align:center;
    font-size:22px;
    font-weight:bold;
    color:#16a34a;
    margin:15px 0;
}

input,button{
    width:100%;
    padding:12px;
    margin-top:10px;
    border-radius:6px;
    border:1px solid #ccc;
}

button{
    background:#16a34a;
    color:white;
    border:none;
    font-weight:bold;
    cursor:pointer;
}

button:hover{
    background:#15803d;
}

.note{
    font-size:13px;
    color:#666;
    text-align:center;
    margin-top:10px;
}

.status{
    text-align:center;
    margin-top:10px;
    padding:10px;
    border-radius:6px;
    font-weight:bold;
}

.pending { background:#fff3cd; color:#856404; }
.paid { background:#d1fae5; color:#065f46; }
.failed { background:#fee2e2; color:#991b1b; }
</style>

</head>

<body>

<?php include("../includes/navbar.php"); ?>

<div class="container">

<h2>💳 Complete Payment</h2>

<div class="amount">
    Order #<?php echo $order['order_id']; ?><br>
    KES <?php echo number_format($order['total_amount'], 2); ?>
</div>

<!-- =========================
     PAYMENT STATUS (REAL SOURCE)
========================= -->
<div class="status <?php echo $payment_status; ?>">
    Payment Status: <?php echo strtoupper($payment_status); ?>
</div>

<!-- =========================
     STK FORM
========================= -->
<?php if ($payment_status !== 'paid') { ?>

<form action="../actions/initiate_stk.php" method="POST">

    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">

    <label>Phone Number (M-Pesa)</label>
    <input 
        type="text" 
        name="phone" 
        placeholder="2547XXXXXXXX"
        required
    >

    <button type="submit">
        📲 Pay with M-Pesa STK Push
    </button>

</form>

<?php } else { ?>

    <p style="text-align:center; color:green; font-weight:bold;">
        ✔ This order has already been paid successfully.
    </p>

<?php } ?>

<p class="note">
You will receive an M-Pesa prompt on your phone to complete payment.
</p>

</div>

</body>
</html>