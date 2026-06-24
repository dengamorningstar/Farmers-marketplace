<?php
session_start();
include("../includes/db.php");

/* =========================
   OPTIONAL: ALLOW VIEW WITHOUT LOGIN
========================= */
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Marketplace</title>

<style>
body{
    font-family: Arial;
    background:#f5f6f8;
    margin:0;
    padding:20px;
}

.header{
    text-align:center;
    margin-bottom:25px;
}

.header h1{
    margin:0;
    color:#2c3e50;
}

.header p{
    color:#666;
    margin-top:8px;
}

.grid{
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));
    gap:20px;
}

.card{
    background:#fff;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
    transition:0.2s;
}

.card:hover{
    transform:translateY(-4px);
}

.card a{
    text-decoration:none;
}

.card img{
    width:100%;
    height:180px;
    object-fit:cover;
    display:block;
}

.no-image{
    width:100%;
    height:180px;
    background:#ddd;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#666;
}

.card-body{
    padding:15px;
}

.title{
    font-size:18px;
    margin:0 0 8px 0;
    color:#333;
}

.price{
    color:#27ae60;
    font-weight:bold;
    margin-bottom:8px;
    font-size:17px;
}

.stock{
    font-size:13px;
    color:#777;
    margin-bottom:10px;
}

.out{
    color:red;
    font-weight:bold;
    margin-bottom:10px;
}

.farmer{
    font-size:13px;
    color:#444;
    margin-bottom:10px;
}

.actions{
    display:flex;
    gap:8px;
    margin-top:10px;
}

.details-btn{
    flex:1;
    background:#007bff;
    color:white;
    text-align:center;
    padding:8px;
    border-radius:5px;
    font-size:14px;
}

form{
    flex:1;
}

input[type=number]{
    width:55px;
    padding:6px;
}

button{
    background:#27ae60;
    color:white;
    border:none;
    padding:8px 10px;
    cursor:pointer;
    border-radius:5px;
    font-size:14px;
}

button:disabled{
    background:gray;
    cursor:not-allowed;
}

.empty{
    text-align:center;
    color:gray;
    padding:40px;
}
</style>
</head>

<body>

<div class="header">
    <h1>Fresh Marketplace</h1>
    <p>Buy fresh produce directly from farmers</p>
</div>

<div class="grid">

<?php
$stmt = $conn->prepare("
    SELECT 
        p.product_id,
        p.name,
        p.price,
        p.quantity,
        p.unit,
        p.image,
        p.farmer_id,
        u.name AS farmer_name
    FROM products p
    INNER JOIN users u ON p.farmer_id = u.user_id
    ORDER BY p.created_at DESC
");

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0):

while ($row = $result->fetch_assoc()):
?>

<div class="card">

    <!-- CLICKABLE IMAGE -->
    <a href="view_products.php?id=<?php echo $row['product_id']; ?>">

        <?php if (!empty($row['image'])): ?>
            <img src="../uploads/<?php echo $row['image']; ?>">
        <?php else: ?>
            <div class="no-image">No Image</div>
        <?php endif; ?>

    </a>

    <div class="card-body">

        <!-- CLICKABLE TITLE -->
        <a href="view_products.php?id=<?php echo $row['product_id']; ?>">
            <h3 class="title">
                <?php echo htmlspecialchars($row['name']); ?>
            </h3>
        </a>

        <!-- FARMER NAME ADDED -->
        <div class="farmer">
            👨‍🌾 Farmer: <?php echo htmlspecialchars($row['farmer_name']); ?>
        </div>

        <div class="price">
            KES <?php echo number_format($row['price'], 2); ?>
        </div>

        <?php if ($row['quantity'] > 0): ?>
            <div class="stock">
                In Stock: 
                <?php echo intval($row['quantity']) . ' ' . htmlspecialchars($row['unit']); ?>
            </div>
        <?php else: ?>
            <div class="out">
                Out of Stock
            </div>
        <?php endif; ?>

        <div class="actions">

            <!-- VIEW DETAILS -->
            <a class="details-btn"
               href="view_products.php?id=<?php echo $row['product_id']; ?>">
               View
            </a>

            <!-- ADD TO CART -->
            <?php if ($row['quantity'] > 0): ?>

            <form action="../actions/add_to_cart.php" method="POST">

                <input type="hidden"
                       name="product_id"
                       value="<?php echo $row['product_id']; ?>">

                <input type="number"
                       name="quantity"
                       min="1"
                       max="<?php echo $row['quantity']; ?>"
                       value="1"
                       required>

                <button type="submit">Cart</button>

            </form>

            <?php else: ?>

                <button disabled>Unavailable</button>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php
endwhile;

else:
?>

<p class="empty">No products available right now.</p>

<?php endif; ?>

</div>

</body>
</html>