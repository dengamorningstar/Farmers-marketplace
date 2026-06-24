<?php
session_start();
include("includes/db.php");

/* =========================
   FEATURED PRODUCTS
========================= */
$products = [];

$query = "
    SELECT 
        p.product_id,
        p.name,
        p.price,
        p.image,
        u.name AS farmer_name
    FROM products p
    JOIN users u ON p.farmer_id = u.user_id
    ORDER BY p.product_id DESC
    LIMIT 8
";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Farm Marketplace</title>

<style>
/* (UNCHANGED STYLES - NO MODIFICATION) */
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:Arial, sans-serif;
    background:#f4f6f9;
    color:#222;
}

/* =========================
   NAVBAR
========================= */
.navbar{
    background:white;
    padding:15px 40px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 2px 10px rgba(0,0,0,0.08);
    position:sticky;
    top:0;
    z-index:1000;
}

.logo{
    font-size:24px;
    font-weight:bold;
    color:#16a34a;
}

.nav-links{
    display:flex;
    gap:20px;
    align-items:center;
}

.nav-links a{
    text-decoration:none;
    color:#333;
    font-weight:500;
}

.nav-links a:hover{
    color:#16a34a;
}

.btn{
    padding:10px 18px;
    border-radius:6px;
    text-decoration:none;
    color:white;
    font-weight:bold;
}

.btn-login{
    background:#16a34a;
}

.btn-register{
    background:#222;
}

/* =========================
   HERO (UNCHANGED)
========================= */
.hero{
    min-height:85vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:60px 40px;
    background:linear-gradient(to right,#16a34a,#15803d);
    color:white;
}

.hero-content{
    max-width:700px;
    text-align:center;
}

.hero h1{
    font-size:58px;
    margin-bottom:20px;
    line-height:1.1;
}

.hero p{
    font-size:20px;
    margin-bottom:30px;
    line-height:1.6;
}

.hero-buttons{
    display:flex;
    justify-content:center;
    gap:15px;
    flex-wrap:wrap;
}

.hero-buttons a{
    padding:14px 25px;
    border-radius:8px;
    text-decoration:none;
    font-weight:bold;
}

.shop-btn{
    background:white;
    color:#16a34a;
}

.join-btn{
    background:#222;
    color:white;
}

/* OTHER SECTIONS UNCHANGED */
.section{padding:70px 40px;}
.section-title{text-align:center;margin-bottom:50px;}
.section-title h2{font-size:36px;margin-bottom:10px;}
.section-title p{color:#666;}

.features{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
}

.feature-card{
    background:white;
    padding:30px;
    border-radius:12px;
    text-align:center;
    box-shadow:0 0 10px rgba(0,0,0,0.06);
}

.products-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:20px;
}

.product-card{
    background:white;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 0 10px rgba(0,0,0,0.08);
}

.product-image{
    width:100%;
    height:220px;
    object-fit:cover;
}

.product-content{padding:15px;}
.price{color:#16a34a;font-size:22px;font-weight:bold;}

.steps{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:20px;
}

.step-card{
    background:white;
    padding:30px;
    border-radius:12px;
    text-align:center;
}

.step-number{
    width:60px;
    height:60px;
    background:#16a34a;
    color:white;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:auto;
    font-size:24px;
    font-weight:bold;
}

.cta{
    background:#222;
    color:white;
    text-align:center;
    padding:80px 20px;
}

.footer{
    background:#111;
    color:white;
    padding:40px 20px;
    text-align:center;
}
</style>
</head>

<body>

<!-- NAVBAR -->
<div class="navbar">

    <div class="logo">
        Farm Marketplace
    </div>

    <div class="nav-links">

        <a href="/myformapp/index.php">Home</a>

        <!-- FIXED -->
        <a href="/myformapp/marketplace.php">Marketplace</a>

        <a href="/myformapp/pages/login.php" class="btn btn-login">
            Login
        </a>

        <a href="/myformapp/pages/register.php" class="btn btn-register">
            Register
        </a>

    </div>

</div>

<!-- HERO -->
<section class="hero">

    <div class="hero-content">

        <h1>Fresh Farm Products Delivered Fast</h1>

        <p>
            Buy directly from verified farmers across Kenya.
        </p>

        <div class="hero-buttons">

            <!-- FIXED -->
            <a href="/myformapp/marketplace.php" class="shop-btn">
                🛒 Shop Now
            </a>

            <a href="/myformapp/pages/register.php" class="join-btn">
                🌱 Become a Farmer
            </a>

        </div>

    </div>

</section>

<!-- PRODUCTS SECTION -->
<section class="section">

    <div class="section-title">
        <h2>Featured Products</h2>
    </div>

    <div class="products-grid">

        <?php if(count($products) > 0): ?>

            <?php foreach($products as $product): ?>

                <div class="product-card">

                    <?php
                    /* FIXED IMAGE PATH */
                    $image = !empty($product['image'])
                        ? "/myformapp/uploads/" . $product['image']
                        : "https://via.placeholder.com/300x220?text=No+Image";
                    ?>

                    <img src="<?php echo $image; ?>" class="product-image">

                    <div class="product-content">

                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>

                        <p><?php echo htmlspecialchars($product['farmer_name']); ?></p>

                        <div class="price">
                            KES <?php echo number_format($product['price'],2); ?>
                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <p>No products available.</p>

        <?php endif; ?>

    </div>

</section>

<!-- CTA -->
<section class="cta">

    <h2>Join The Marketplace Today</h2>

    <a href="/myformapp/pages/register.php">
        Create Account
    </a>

</section>

<!-- FOOTER -->
<footer class="footer">

    <h3>Farm Marketplace</h3>

    <p>© <?php echo date("Y"); ?></p>

</footer>

</body>
</html>