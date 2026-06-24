<?php
session_start();
include("../includes/db.php");

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'farmer') {
    header("Location: login.php");
    exit();
}

$farmer_id = intval($_SESSION['user']);

/* =========================
   CACHE FIX (ADDED ONLY)
========================= */
header("Cache-Control: no-cache, must-revalidate");

/* =========================
   FETCH PRODUCTS
========================= */
$stmt = $conn->prepare("
    SELECT 
        product_id,
        name,
        price,
        quantity,
        image,
        created_at
    FROM products
    WHERE farmer_id = ?
    ORDER BY created_at DESC
");

$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$result = $stmt->get_result();

/* =========================
   PRODUCT STATS (FIXED SAFETY)
========================= */
$stats = $conn->prepare("
    SELECT 
        COUNT(*) as total_products,
        COALESCE(SUM(quantity), 0) as total_stock,
        SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN quantity > 0 AND quantity <= 5 THEN 1 ELSE 0 END) as low_stock
    FROM products
    WHERE farmer_id = ?
");

$stats->bind_param("i", $farmer_id);
$stats->execute();
$summary = $stats->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
<title>My Products</title>

<style>
body{
    font-family: Arial;
    background:#f5f6f8;
    margin:0;
}

.header{
    text-align:center;
    padding:20px;
}

.stats{
    display:flex;
    justify-content:center;
    gap:15px;
    flex-wrap:wrap;
    margin-bottom:20px;
}

.stat-box{
    background:white;
    padding:12px 18px;
    border-radius:8px;
    box-shadow:0 4px 10px rgba(0,0,0,0.08);
    font-size:14px;
}

.add-btn{
    display:block;
    width:200px;
    margin:10px auto 30px;
    text-align:center;
    padding:10px;
    background:#28a745;
    color:white;
    text-decoration:none;
    border-radius:6px;
}

.container{
    width:90%;
    margin:auto;
}

.grid{
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(250px, 1fr));
    gap:20px;
}

.card{
    background:white;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 6px 16px rgba(0,0,0,0.08);
    transition:0.2s;
}

.card:hover{
    transform:translateY(-3px);
}

.card img{
    width:100%;
    height:160px;
    object-fit:cover;
}

.content{
    padding:15px;
}

.name{
    font-size:18px;
    font-weight:bold;
    color:#333;
}

.price{
    color:#27ae60;
    font-weight:bold;
    margin:5px 0;
}

.qty{
    font-size:13px;
    margin:5px 0;
}

.badge{
    display:inline-block;
    padding:4px 8px;
    border-radius:5px;
    font-size:12px;
    color:white;
    margin-top:5px;
}

.green{ background:#28a745; }
.orange{ background:#f39c12; }
.red{ background:#e74c3c; }

.actions{
    display:flex;
    gap:10px;
    margin-top:10px;
}

.btn{
    flex:1;
    padding:8px;
    text-align:center;
    border-radius:5px;
    text-decoration:none;
    font-size:13px;
    color:white;
}

.edit{ background:#007bff; }
.delete{ background:#dc3545; }

.empty{
    text-align:center;
    color:gray;
    margin-top:40px;
}
</style>
</head>

<body>

<div class="header">
    <h2>My Products Dashboard</h2>

    <div class="stats">
        <div class="stat-box">📦 Products: <?php echo $summary['total_products'] ?? 0; ?></div>
        <div class="stat-box">📊 Stock: <?php echo $summary['total_stock'] ?? 0; ?></div>
        <div class="stat-box">⚠️ Low Stock: <?php echo $summary['low_stock'] ?? 0; ?></div>
        <div class="stat-box">❌ Out of Stock: <?php echo $summary['out_of_stock'] ?? 0; ?></div>
    </div>
</div>

<a class="add-btn" href="add_product.php">+ Add New Product</a>

<div class="container">
<div class="grid">

<?php if ($result->num_rows > 0): ?>

<?php while($row = $result->fetch_assoc()): ?>

<?php
$qty = intval($row['quantity']);

if ($qty <= 0) {
    $statusClass = "red";
    $statusText = "Out of Stock";
} elseif ($qty <= 5) {
    $statusClass = "orange";
    $statusText = "Low Stock";
} else {
    $statusClass = "green";
    $statusText = "In Stock";
}

/* =========================
   FIX: SAFE IMAGE HANDLING
========================= */
$image = trim($row['image'] ?? '');
?>

<div class="card">

<?php if (!empty($image)): ?>
    <img src="../uploads/<?php echo urlencode($image); ?>?v=<?php echo time(); ?>">
<?php else: ?>
    <div style="height:160px;display:flex;align-items:center;justify-content:center;background:#ddd;">
        No Image
    </div>
<?php endif; ?>

<div class="content">

    <div class="name">
        <?php echo htmlspecialchars($row['name']); ?>
    </div>

    <div class="price">
        KES <?php echo number_format($row['price'], 2); ?>
    </div>

    <div class="qty">
        Quantity: <?php echo $qty; ?>
    </div>

    <div class="badge <?php echo $statusClass; ?>">
        <?php echo $statusText; ?>
    </div>

    <div class="actions">

        <a class="btn edit"
           href="edit_product.php?id=<?php echo $row['product_id']; ?>">
            Edit
        </a>

        <a class="btn delete"
           href="../actions/delete_product.php?id=<?php echo $row['product_id']; ?>"
           onclick="return confirm('Delete this product?');">
            Delete
        </a>

    </div>

</div>

</div>

<?php endwhile; ?>

<?php else: ?>
    <p class="empty">You have no products yet.</p>
<?php endif; ?>

</div>
</div>

</body>
</html>