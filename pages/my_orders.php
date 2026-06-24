<?php
session_start();
include("../includes/db.php");

/* =========================
   PROTECT PAGE
========================= */
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user']);

/* =========================
   GET USER ORDERS (ZONE INCLUDED)
========================= */
$stmt = $conn->prepare("
    SELECT 
        o.order_id,
        o.total_amount,
        o.status,
        o.zone,
        o.created_at,
        o.updated_at,

        COALESCE(p.payment_status, 'pending') AS payment_status,
        p.transaction_ref,
        p.paid_at

    FROM orders o

    LEFT JOIN payments p
        ON p.order_id = o.order_id

    WHERE o.user_id = ?

    ORDER BY o.order_id DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$orders = $stmt->get_result();

/* =========================
   SAFE BADGE CLASS
========================= */
function safeBadge($value)
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($value));
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders</title>

    <style>

        body{
            font-family:Arial;
            background:#f4f6f9;
            margin:0;
        }

        .container{
            max-width:1100px;
            margin:30px auto;
            padding:20px;
        }

        h2{
            margin-top:0;
            color:#111827;
        }

        .order-card{
            background:white;
            padding:20px;
            margin-bottom:25px;
            border-radius:12px;
            box-shadow:0 2px 10px rgba(0,0,0,0.08);
        }

        .order-header{
            display:flex;
            justify-content:space-between;
            flex-wrap:wrap;
            gap:10px;
            margin-bottom:15px;
        }

        .order-info p{
            margin:6px 0;
        }

        .badge{
            display:inline-block;
            padding:6px 12px;
            border-radius:20px;
            color:white;
            font-size:12px;
            font-weight:bold;
            text-transform:capitalize;
        }

        /* PAYMENT */
        .paid{ background:#16a34a; }
        .pending{ background:#f59e0b; }
        .failed{ background:#dc2626; }

        /* ORDER STATUS */
        .confirmed{ background:#2563eb; }
        .processing{ background:#0891b2; }
        .completed{ background:#16a34a; }
        .cancelled{ background:#dc2626; }

        /* DELIVERY */
        .available{ background:#6b7280; }
        .assigned{ background:#2563eb; }
        .accepted{ background:#0ea5e9; }
        .picked_up{ background:#f59e0b; }
        .in_transit{ background:#7c3aed; }
        .delivered{ background:#16a34a; }

        .item-box{
            border-top:1px solid #e5e7eb;
            padding:15px 0;
        }

        .item-title{
            font-size:17px;
            font-weight:bold;
            margin-bottom:8px;
            color:#111827;
        }

        .item-meta{
            font-size:14px;
            color:#374151;
            margin-bottom:10px;
        }

        .section-label{
            display:block;
            margin-top:8px;
            margin-bottom:5px;
            font-weight:bold;
            color:#111827;
        }

        .empty{
            background:white;
            padding:25px;
            border-radius:10px;
            text-align:center;
            box-shadow:0 2px 10px rgba(0,0,0,0.08);
        }

        .btn{
            display:inline-block;
            margin-top:15px;
            padding:10px 18px;
            background:#2563eb;
            color:white;
            text-decoration:none;
            border-radius:8px;
            font-weight:bold;
        }

        .btn:hover{
            background:#1d4ed8;
        }

        .receipt-box{
            margin-top:10px;
            font-size:14px;
            color:#374151;
        }

        .zone-box{
            margin-top:6px;
            font-size:13px;
            color:#065f46;
            background:#ecfdf5;
            padding:6px 10px;
            border-radius:6px;
            display:inline-block;
        }

    </style>
</head>

<body>

<?php include("../includes/navbar.php"); ?>

<div class="container">

    <h2>📦 My Orders</h2>

    <?php if ($orders->num_rows > 0): ?>

        <?php while ($order = $orders->fetch_assoc()): ?>

            <?php
            $payment_status = strtolower($order['payment_status'] ?? 'pending');
            $order_status   = strtolower($order['status'] ?? 'confirmed');

            // zone fallback (updated logic)
            $zone_label = $order['zone_label'] ?? $order['zone'];
            ?>

            <div class="order-card">

                <div class="order-header">

                    <div class="order-info">

                        <p><strong>Order #<?php echo $order['order_id']; ?></strong></p>

                        <p>
                            Total:
                            <strong>KES <?php echo number_format($order['total_amount'], 2); ?></strong>
                        </p>

                        <div class="zone-box">
                            📍 Zone: <?php echo htmlspecialchars($zone_label); ?>
                        </div>

                        <p>Created: <?php echo htmlspecialchars($order['created_at']); ?></p>

                        <?php if (!empty($order['updated_at'])): ?>
                            <p>Updated: <?php echo htmlspecialchars($order['updated_at']); ?></p>
                        <?php endif; ?>

                        <?php if (!empty($order['transaction_ref'])): ?>
                            <div class="receipt-box">
                                MPESA Ref: <strong><?php echo htmlspecialchars($order['transaction_ref']); ?></strong>
                            </div>
                        <?php endif; ?>

                    </div>

                    <div>

                        <p>
                            <strong>Payment</strong><br>
                            <span class="badge <?php echo safeBadge($payment_status); ?>">
                                <?php echo ucwords($payment_status); ?>
                            </span>
                        </p>

                        <p>
                            <strong>Order Status</strong><br>
                            <span class="badge <?php echo safeBadge($order_status); ?>">
                                <?php echo ucwords($order_status); ?>
                            </span>
                        </p>

                    </div>

                </div>

                <hr>

                <h3>Items</h3>

                <?php
                $stmt_items = $conn->prepare("
                    SELECT
                        oi.order_item_id,
                        oi.quantity,
                        oi.price,
                        oi.status AS item_status,
                        p.name,

                        da.status AS delivery_status,
                        dz.zone_label,
                        dz.zone_key

                    FROM order_items oi
                    INNER JOIN products p ON oi.product_id = p.product_id

                    LEFT JOIN delivery_assignments da 
                        ON da.order_id = oi.order_id

                    LEFT JOIN delivery_zones dz
                        ON da.zone_id = dz.zone_id

                    WHERE oi.order_id = ?

                    ORDER BY oi.order_item_id DESC
                ");

                $stmt_items->bind_param("i", $order['order_id']);
                $stmt_items->execute();

                $items = $stmt_items->get_result();
                ?>

                <?php while ($item = $items->fetch_assoc()): ?>

                    <?php
                    $subtotal = $item['price'] * $item['quantity'];
                    $prep_status = strtolower($item['item_status'] ?? 'pending');
                    $delivery_status = strtolower($item['delivery_status'] ?? 'available');
                    ?>

                    <div class="item-box">

                        <div class="item-title">
                            <?php echo htmlspecialchars($item['name']); ?>
                        </div>

                        <div class="item-meta">
                            Qty: <?php echo intval($item['quantity']); ?> |
                            Price: KES <?php echo number_format($item['price'], 2); ?> |
                            Subtotal: KES <?php echo number_format($subtotal, 2); ?>
                        </div>

                        <span class="section-label">🧑‍🌾 Item Status</span>
                        <span class="badge <?php echo safeBadge($prep_status); ?>">
                            <?php echo ucwords($prep_status); ?>
                        </span>

                        <span class="section-label">🚚 Delivery Status</span>
                        <span class="badge <?php echo safeBadge($delivery_status); ?>">
                            <?php echo ucwords($delivery_status); ?>
                        </span>

                    </div>

                <?php endwhile; ?>

                <?php if ($payment_status === 'pending'): ?>
                    <a href="payment_pending.php?order_id=<?php echo $order['order_id']; ?>" class="btn">
                        💳 Complete Payment
                    </a>
                <?php elseif ($payment_status === 'failed'): ?>
                    <a href="payment_failed.php?order_id=<?php echo $order['order_id']; ?>" class="btn" style="background:#dc2626;">
                        ❌ View Failed Payment
                    </a>
                <?php else: ?>
                    <a href="payment_success.php?order_id=<?php echo $order['order_id']; ?>" class="btn" style="background:#16a34a;">
                        ✅ View Receipt
                    </a>
                <?php endif; ?>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="empty">
            <h3>No Orders Found</h3>
            <p>You have not placed any orders yet.</p>
            <a href="view_products.php" class="btn">🛍 Start Shopping</a>
        </div>

    <?php endif; ?>

</div>

</body>
</html>