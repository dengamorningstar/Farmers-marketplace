<?php
session_start();
include("../includes/db.php");

/* =========================
   VALIDATE PRODUCT ID
========================= */
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Product not found";
    header("Location: products.php");
    exit();
}

$product_id = intval($_GET['id']);

if ($product_id <= 0) {
    $_SESSION['error'] = "Invalid product";
    header("Location: products.php");
    exit();
}

/* =========================
   FETCH PRODUCT (UPDATED ONLY)
========================= */
$stmt = $conn->prepare("
    SELECT 
        p.product_id,
        p.name,
        p.description,
        p.price,
        p.quantity,
        p.unit,
        p.image,
        p.created_at,
        u.name AS farmer_name
    FROM products p
    INNER JOIN users u ON p.farmer_id = u.user_id
    WHERE p.product_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("System error");
}

$stmt->bind_param("i", $product_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error'] = "Product not found";
    header("Location: products.php");
    exit();
}

$product = $result->fetch_assoc();

/* =========================
   AVAILABILITY LOGIC (UNCHANGED)
========================= */
$is_available = ($product['quantity'] > 0);
?>

<!DOCTYPE html>
<html>
<head>
<title><?php echo htmlspecialchars($product['name']); ?></title>

<style>
body{
    font-family:Arial;
    background:#f5f6f8;
    margin:0;
    padding:30px;
}

.wrapper{
    max-width:1000px;
    margin:auto;
    background:white;
    border-radius:12px;
    box-shadow:0 6px 18px rgba(0,0,0,0.08);
    overflow:hidden;
    display:grid;
    grid-template-columns:1fr 1fr;
}

.image-box{
    background:#eee;
}

.image-box img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.details{
    padding:30px;
}

h1{
    margin-top:0;
    color:#222;
}

.price{
    color:#27ae60;
    font-size:28px;
    font-weight:bold;
    margin:15px 0;
}

.stock{
    color:#555;
    margin-bottom:10px;
}

.desc{
    color:#666;
    line-height:1.6;
    margin-bottom:20px;
}

.farmer{
    color:#444;
    font-size:14px;
    margin-bottom:10px;
}

input[type=number]{
    width:80px;
    padding:8px;
    margin-right:10px;
}

button{
    background:#27ae60;
    color:white;
    border:none;
    padding:10px 18px;
    border-radius:6px;
    cursor:pointer;
}

button:hover{
    background:#219150;
}

button:disabled{
    background:gray;
    cursor:not-allowed;
}

.back{
    display:inline-block;
    margin-bottom:20px;
    text-decoration:none;
    color:#007bff;
}

.note{
    font-size:13px;
    color:#777;
    margin-top:10px;
}
</style>
</head>

<body>

<a href="products.php" class="back">← Back to Products</a>

<div class="wrapper">

<div class="image-box">

<?php if (!empty($product['image'])): ?>
    <img src="../uploads/<?php echo htmlspecialchars($product['image']); ?>">
<?php else: ?>
    <div style="padding:50px;text-align:center;">No Image</div>
<?php endif; ?>

</div>

<div class="details">

<h1><?php echo htmlspecialchars($product['name']); ?></h1>

<!-- ADDED FARMER INFO ONLY -->
<div class="farmer">
    👨‍🌾 Farmer: <?php echo htmlspecialchars($product['farmer_name']); ?>
</div>

<div class="price">
KES <?php echo number_format($product['price'],2); ?>
</div>

<div class="stock">
Available Stock: 
<?php echo intval($product['quantity']) . ' ' . htmlspecialchars($product['unit']); ?>
</div>

<div class="desc">
<?php echo nl2br(htmlspecialchars($product['description'])); ?>
</div>

<?php if ($is_available): ?>

<form action="../actions/add_to_cart.php" method="POST">

<input type="hidden"
name="product_id"
value="<?php echo $product['product_id']; ?>">

<input type="number"
name="quantity"
value="1"
min="1"
max="<?php echo intval($product['quantity']); ?>"
required>

<button type="submit">Add to Cart</button>

</form>

<p class="note">Stock is validated at checkout for safety.</p>

<?php else: ?>

<button disabled>Out of Stock</button>

<?php endif; ?>

</div>

</div>

</body>
</html>