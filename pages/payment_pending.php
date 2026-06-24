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
    die("Invalid order");
}

/* =========================
   FETCH ORDER + PAYMENT
   PAYMENT SOURCE OF TRUTH
========================= */
$stmt = $conn->prepare("
    SELECT
        o.order_id,
        o.total_amount,
        o.status AS order_status,

        p.payment_id,
        p.payment_status,
        p.transaction_ref,
        p.checkout_request_id,
        p.created_at AS payment_created

    FROM orders o

    LEFT JOIN payments p
        ON p.order_id = o.order_id

    WHERE o.order_id = ?
    AND o.user_id = ?

    LIMIT 1
");

$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Order not found");
}

/* =========================
   NORMALIZE PAYMENT STATUS
========================= */
$payment_status = strtolower(
    trim($order['payment_status'] ?? 'pending')
);

/* =========================
   ALREADY PAID
========================= */
if ($payment_status === 'paid') {

    header(
        "Location: payment_success.php?order_id=" .
        $order_id
    );

    exit();
}
?>

<!DOCTYPE html>
<html>
<head>

<title>Payment</title>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>

body{
    font-family:Arial;
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

h2{
    text-align:center;
    margin-top:0;
}

.amount{
    text-align:center;
    font-size:22px;
    font-weight:bold;
    color:#16a34a;
    margin:15px 0;
}

.order-meta{
    text-align:center;
    margin-bottom:20px;
    color:#374151;
    font-size:14px;
}

input,
button{
    width:100%;
    padding:12px;
    margin-top:10px;
    border-radius:6px;
    border:1px solid #ccc;
    box-sizing:border-box;
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

button:disabled{
    background:gray;
    cursor:not-allowed;
}

.status{
    margin-top:20px;
    text-align:center;
    font-weight:bold;
    padding:12px;
    border-radius:6px;
    line-height:1.5;
}

.pending{
    background:#fff3cd;
    color:#856404;
}

.paid{
    background:#d1fae5;
    color:#065f46;
}

.failed{
    background:#fee2e2;
    color:#991b1b;
}

.info-box{
    background:#eff6ff;
    color:#1e40af;
    padding:12px;
    border-radius:6px;
    margin-top:15px;
    font-size:14px;
    line-height:1.5;
}

.retry-btn{
    background:#dc2626;
}

.retry-btn:hover{
    background:#b91c1c;
}

</style>

</head>

<body>

<div class="container">

    <h2>💳 Complete Payment</h2>

    <div class="amount">
        Order #<?php echo $order['order_id']; ?><br>
        KES <?php echo number_format($order['total_amount'], 2); ?>
    </div>

    <div class="order-meta">
        Payment Status:
        <strong>
            <?php echo ucfirst($payment_status); ?>
        </strong>
    </div>

    <form id="stkForm">

        <input
            type="hidden"
            name="order_id"
            value="<?php echo $order['order_id']; ?>"
        >

        <input
            type="text"
            name="phone"
            placeholder="Enter phone (2547XXXXXXXX)"
            required
            pattern="254[0-9]{9}"
        >

        <button type="submit" id="payBtn">
            📲 Pay with M-Pesa STK Push
        </button>

    </form>

    <div class="info-box">
        Enter your Safaricom number in format:
        <strong>2547XXXXXXXX</strong>
    </div>

    <div id="response" class="status pending">
        ⏳ Waiting for payment initiation...
    </div>

</div>

<script>

let orderId = <?php echo $order['order_id']; ?>;

let paymentStarted = false;

let pollingStarted = false;

/* =========================
   RESPONSE BOX
========================= */
function updateStatus(type, message) {

    let box = document.getElementById("response");

    box.className = "status " + type;

    box.innerHTML = message;
}

/* =========================
   DISABLE PAYMENT BUTTON
========================= */
function disableButton(text = "Processing...") {

    let btn = document.getElementById("payBtn");

    btn.disabled = true;

    btn.innerText = text;
}

/* =========================
   ENABLE PAYMENT BUTTON
========================= */
function enableButton() {

    let btn = document.getElementById("payBtn");

    btn.disabled = false;

    btn.innerText = "📲 Pay with M-Pesa STK Push";
}

/* =========================
   START PAYMENT POLLING
========================= */
function startPolling() {

    if (pollingStarted) return;

    pollingStarted = true;

    setInterval(checkPayment, 5000);
}

/* =========================
   STK PUSH REQUEST
========================= */
document
.getElementById("stkForm")
.addEventListener("submit", function(e){

    e.preventDefault();

    if (paymentStarted) return;

    paymentStarted = true;

    disableButton("Sending STK...");

    updateStatus(
        "pending",
        "📲 Sending STK Push..."
    );

    let formData = new FormData(this);

    fetch("../actions/initiate_stk.php", {

        method: "POST",

        body: formData

    })
    .then(res => res.json())

    .then(data => {

        if (data.success) {

            updateStatus(
                "pending",
                "📲 STK sent successfully.<br>Please check your phone and enter your M-Pesa PIN."
            );

            disableButton("Waiting for Confirmation...");

            startPolling();

        } else {

            paymentStarted = false;

            enableButton();

            updateStatus(
                "failed",
                "❌ " + (
                    data.error ||
                    "Failed to initiate payment"
                )
            );
        }

    })

    .catch(error => {

        console.error(error);

        paymentStarted = false;

        enableButton();

        updateStatus(
            "failed",
            "❌ Request failed. Please try again."
        );
    });
});

/* =========================
   PAYMENT POLLING
   SOURCE OF TRUTH:
   payments.payment_status
========================= */
function checkPayment() {

    fetch(
        "../actions/check_payment_status.php?order_id=" +
        orderId
    )

    .then(res => res.json())

    .then(data => {

        if (!data.status) {
            return;
        }

        if (data.status === "paid") {

            updateStatus(
                "paid",
                "✅ Payment Successful! Redirecting..."
            );

            disableButton("Payment Complete");

            setTimeout(() => {

                window.location.href =
                    "payment_success.php?order_id=" +
                    orderId;

            }, 2000);

        }

        else if (data.status === "failed") {

            paymentStarted = false;

            enableButton();

            updateStatus(
                "failed",
                "❌ Payment failed or cancelled.<br>Please try again."
            );
        }

        else {

            updateStatus(
                "pending",
                "⏳ Waiting for M-Pesa confirmation..."
            );
        }

    })

    .catch(error => {

        console.error(error);
    });
}

/* =========================
   AUTO START POLLING
   IF PAYMENT ALREADY PENDING
========================= */
<?php if ($payment_status === 'pending'): ?>

startPolling();

<?php endif; ?>

</script>

</body>
</html>