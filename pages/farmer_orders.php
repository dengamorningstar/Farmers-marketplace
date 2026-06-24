<?php
session_start();
include("../includes/db.php");

/* =========================
   PROTECT FARMER ONLY
========================= */
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'farmer') {
    header("Location: login.php");
    exit();
}

$farmer_id = intval($_SESSION['user']);

/* =========================
   FETCH FARMER ORDERS
========================= */
$stmt = $conn->prepare("
    SELECT 
        oi.order_item_id,
        oi.order_id,
        oi.product_id,
        oi.quantity,
        oi.price,
        oi.status AS item_status,

        p.name AS product_name,
        p.image,

        o.status AS order_status,
        o.created_at AS order_created_at,

        u.name AS buyer_name,

        pay.payment_status AS payment_status

    FROM order_items oi

    INNER JOIN products p 
        ON oi.product_id = p.product_id

    INNER JOIN orders o 
        ON oi.order_id = o.order_id

    INNER JOIN users u 
        ON o.user_id = u.user_id

    LEFT JOIN payments pay
        ON pay.order_id = o.order_id

    WHERE p.farmer_id = ?

    ORDER BY o.created_at DESC
");

$stmt->bind_param("i", $farmer_id);
$stmt->execute();

$result = $stmt->get_result();

$total_sales = 0;
$total_orders = 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Farmer Orders</title>

    <style>
        body{
            font-family:Arial;
            background:#f4f6f9;
            margin:0;
        }

        h2{
            text-align:center;
            padding:20px;
            margin:0;
        }

        .container{
            width:90%;
            margin:auto;
            padding-bottom:30px;
        }

        .card{
            background:white;
            padding:18px;
            margin-bottom:15px;
            border-radius:10px;
            box-shadow:0 0 10px rgba(0,0,0,0.08);
        }

        .product-row{
            display:flex;
            gap:15px;
            align-items:center;
            margin-bottom:15px;
        }

        .product-image{
            width:80px;
            height:80px;
            object-fit:cover;
            border-radius:8px;
            background:#eee;
        }

        .status, .payment{
            padding:5px 10px;
            border-radius:5px;
            color:white;
            font-size:12px;
            text-transform:capitalize;
        }

        .pending{ background:orange; }
        .processing{ background:#007bff; }
        .ready_for_pickup{ background:purple; }
        .paid{ background:#28a745; }
        .failed{ background:#dc3545; }

        .btn{
            padding:10px;
            margin-top:15px;
            border:none;
            border-radius:5px;
            color:white;
            cursor:pointer;
            width:100%;
            font-weight:bold;
        }

        .btn-process{ background:#007bff; }
        .btn-pickup{ background:purple; }
        .btn-disabled{ background:#ccc; cursor:not-allowed; }

        .info{
            background:#d1ecf1;
            padding:10px;
            border-radius:5px;
            margin-top:15px;
            font-size:13px;
            color:#0c5460;
        }

        .warning{
            background:#fff3cd;
            padding:10px;
            border-radius:5px;
            margin-top:15px;
            font-size:13px;
            color:#856404;
        }
    </style>
</head>

<body>

<?php include("../includes/navbar.php"); ?>

<h2>📦 Farmer Orders Dashboard</h2>

<div class="container">

<?php if ($result->num_rows > 0): ?>

    <?php while($row = $result->fetch_assoc()): ?>

        <?php
        $subtotal = $row['price'] * $row['quantity'];

        $item_status = strtolower(trim($row['item_status'] ?? 'pending'));
        $payment = strtolower(trim($row['payment_status'] ?? 'pending'));

        $total_sales += $subtotal;
        $total_orders++;

        $next_status = null;
        $button_label = null;
        $button_class = "";
        $blocked = false;

        if ($payment !== 'paid') {
            $blocked = true;
        }

        /* =========================
           ITEM WORKFLOW ENGINE
        ========================= */
        switch ($item_status) {

            case 'pending':
                $next_status = 'processing';
                $button_label = 'Start Processing';
                $button_class = 'btn-process';
                break;

            case 'processing':
                $next_status = 'ready_for_pickup';
                $button_label = 'Ready For Pickup';
                $button_class = 'btn-pickup';
                break;

            case 'ready_for_pickup':
                $button_label = 'Waiting Rider Pickup';
                break;

            default:
                $button_label = 'Completed';
                break;
        }
        ?>

        <div class="card">

            <div class="product-row">

                <?php if(!empty($row['image'])): ?>
                    <img src="../uploads/<?php echo htmlspecialchars($row['image']); ?>" class="product-image">
                <?php endif; ?>

                <div>
                    <h3><?php echo htmlspecialchars($row['product_name']); ?></h3>
                    <p>Buyer: <strong><?php echo htmlspecialchars($row['buyer_name']); ?></strong></p>
                    <p>Order #<?php echo $row['order_id']; ?></p>
                </div>

            </div>

            <p><strong>Item Status:</strong>
                <span class="status <?php echo $item_status; ?>">
                    <?php echo ucwords(str_replace('_', ' ', $item_status)); ?>
                </span>
            </p>

            <p><strong>Payment:</strong>
                <span class="payment <?php echo $payment; ?>">
                    <?php echo ucwords($payment); ?>
                </span>
            </p>

            <?php if($blocked): ?>
                <div class="warning">
                    ⚠ Cannot process until payment is confirmed.
                </div>
            <?php endif; ?>

            <?php if($item_status === 'ready_for_pickup'): ?>
                <div class="info">
                    🚚 Waiting for rider pickup.
                </div>
            <?php endif; ?>

            <?php if($next_status): ?>

                <form action="../actions/update_order_status.php" method="POST">

                    <input type="hidden" name="order_item_id" value="<?php echo $row['order_item_id']; ?>">
                    <input type="hidden" name="status" value="<?php echo $next_status; ?>">

                    <button class="btn <?php echo $button_class; ?>" <?php echo $blocked ? 'disabled' : ''; ?>>
                        <?php echo $blocked ? 'Waiting Payment' : $button_label; ?>
                    </button>

                </form>

            <?php else: ?>

                <button class="btn btn-disabled" disabled>
                    <?php echo $button_label; ?>
                </button>

            <?php endif; ?>

        </div>

    <?php endwhile; ?>

<?php else: ?>

    <div class="warning">
        No farmer orders found yet.
    </div>

<?php endif; ?>

</div>

</body>
</html>