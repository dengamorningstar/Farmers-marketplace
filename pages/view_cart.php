<?php
session_start();
include("../includes/db.php");

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user']);

/* =========================
   FETCH CART ITEMS (UPDATED)
========================= */
$stmt = $conn->prepare("
    SELECT 
        c.product_id,
        c.quantity,
        p.name,
        p.price,
        p.image,
        p.quantity AS stock,
        u.name AS farmer_name
    FROM cart c
    INNER JOIN products p 
        ON c.product_id = p.product_id
    INNER JOIN users u
        ON p.farmer_id = u.user_id
    WHERE c.user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$total = 0;
$has_invalid_stock = false;

/* =========================
   SAFE OUTPUT
========================= */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Cart</title>

<style>
body{
    font-family: Arial;
    background:#f4f6f9;
    margin:0;
}

.container{
    max-width:1000px;
    margin:30px auto;
    padding:20px;
}

h2{ margin-bottom:20px; }

.cart-item{
    background:white;
    border-radius:10px;
    padding:15px;
    margin-bottom:20px;
    display:flex;
    gap:20px;
    box-shadow:0 0 10px rgba(0,0,0,0.08);
    align-items:center;
}

.cart-image img{
    width:120px;
    height:120px;
    object-fit:cover;
    border-radius:8px;
}

.cart-details{ flex:1; }

.price{
    color:#16a34a;
    font-weight:bold;
}

/* NEW */
.farmer{
    font-size:14px;
    color:#555;
    margin-top:5px;
}

.stock{
    margin-top:5px;
    font-size:14px;
}

.in-stock{ color:green; }
.low-stock{ color:orange; }
.out-stock{ color:red; font-weight:bold; }

input[type="number"]{
    width:80px;
    padding:8px;
    margin-top:10px;
}

.btn{
    padding:10px 14px;
    border:none;
    border-radius:6px;
    cursor:pointer;
    text-decoration:none;
    display:inline-block;
    font-size:14px;
}

.btn-update{ background:#2563eb; color:white; }
.btn-remove{ background:#dc2626; color:white; margin-left:10px; }

.summary{
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,0.08);
    margin-top:20px;
}

.checkout-btn{
    width:100%;
    background:#16a34a;
    color:white;
    padding:14px;
    border:none;
    border-radius:8px;
    font-size:16px;
    font-weight:bold;
    cursor:pointer;
    margin-top:15px;
}

.checkout-btn:hover{ background:#15803d; }

.disabled-btn{
    background:gray !important;
    cursor:not-allowed;
}

.empty-cart{
    background:white;
    padding:40px;
    text-align:center;
    border-radius:10px;
}
</style>
</head>

<body>

<?php include("../includes/navbar.php"); ?>

<div class="container">

<h2>🛒 Your Cart</h2>

<?php if ($result->num_rows <= 0): ?>

    <div class="empty-cart">
        <h3>Your cart is empty</h3>
        <a href="marketplace.php" class="btn btn-update">Continue Shopping</a>
    </div>

<?php else: ?>

<?php while ($row = $result->fetch_assoc()): ?>

<?php
$price = floatval($row['price']);
$qty   = intval($row['quantity']);
$stock = intval($row['stock']);

$subtotal = $price * $qty;

/* STRICT VALIDATION */
if ($stock <= 0 || $qty <= 0 || $qty > $stock) {
    $has_invalid_stock = true;
} else {
    $total += $subtotal;
}
?>

<div class="cart-item">

    <div class="cart-image">
        <img src="../uploads/<?php echo e($row['image']); ?>" alt="">
    </div>

    <div class="cart-details">

        <h3><?php echo e($row['name']); ?></h3>

        <!-- NEW -->
        <div class="farmer">
            👨‍🌾 Farmer: <?php echo e($row['farmer_name']); ?>
        </div>

        <p class="price">
            KES <?php echo number_format($price, 2); ?>
        </p>

        <?php if ($stock <= 0): ?>
            <p class="stock out-stock">Out of stock</p>

        <?php elseif ($stock <= 5): ?>
            <p class="stock low-stock">Low stock: <?php echo $stock; ?></p>

        <?php else: ?>
            <p class="stock in-stock">In stock: <?php echo $stock; ?></p>
        <?php endif; ?>

        <form action="../actions/update_cart.php" method="POST">

            <input type="hidden" name="product_id" value="<?php echo intval($row['product_id']); ?>">

            <input type="number"
                   name="quantity"
                   value="<?php echo $qty; ?>"
                   min="1"
                   max="<?php echo $stock; ?>"
                   <?php echo ($stock <= 0) ? 'disabled' : ''; ?>>

            <button type="submit"
                    class="btn btn-update"
                    <?php echo ($stock <= 0) ? 'disabled' : ''; ?>>
                Update
            </button>

            <a href="../actions/remove_from_cart.php?id=<?php echo intval($row['product_id']); ?>"
               class="btn btn-remove">
               Remove
            </a>

        </form>

        <p>
            Subtotal:
            <strong>KES <?php echo number_format($subtotal, 2); ?></strong>
        </p>

    </div>

</div>

<?php endwhile; ?>

<div class="summary">

    <h3>Cart Summary</h3>

    <p><strong>Total:</strong> KES <?php echo number_format($total, 2); ?></p>

    <?php if ($has_invalid_stock): ?>

        <p class="out-stock">
            Some items are invalid or exceed stock. Fix before checkout.
        </p>

        <button class="checkout-btn disabled-btn" disabled>
            Checkout Disabled
        </button>

    <?php else: ?>

        <form action="../actions/checkout.php" method="POST">
            <button type="submit" class="checkout-btn">
                Proceed to Checkout
            </button>
        </form>

    <?php endif; ?>

</div>

<?php endif; ?>

</div>

</body>
</html>