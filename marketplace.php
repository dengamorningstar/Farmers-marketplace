<?php
session_start();

/* =========================
   ERROR REPORTING (DEV ONLY)
========================= */
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once(__DIR__ . "/includes/db.php");

/* =========================
   DB SAFETY CHECK
========================= */
if (!isset($conn)) {
    die("Database connection failed");
}

/* =========================
   CHECK STATUS COLUMN
========================= */
$statusColumnExists = false;

$checkCol = $conn->query("SHOW COLUMNS FROM products LIKE 'status'");
if ($checkCol && $checkCol->num_rows > 0) {
    $statusColumnExists = true;
}

/* =========================
   FETCH PRODUCTS
========================= */
if ($statusColumnExists) {
    $query = "
        SELECT 
            p.product_id,
            p.name,
            p.price,
            p.quantity,
            p.image,
            p.status,
            u.name AS farmer_name
        FROM products p
        JOIN users u ON p.farmer_id = u.user_id
        WHERE p.quantity > 0 
          AND p.status = 'active'
        ORDER BY p.product_id DESC
    ";
} else {
    $query = "
        SELECT 
            p.product_id,
            p.name,
            p.price,
            p.quantity,
            p.image,
            'active' AS status,
            u.name AS farmer_name
        FROM products p
        JOIN users u ON p.farmer_id = u.user_id
        WHERE p.quantity > 0 
        ORDER BY p.product_id DESC
    ";
}

$result = $conn->query($query);

if (!$result) {
    die("QUERY FAILED: " . $conn->error);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Marketplace</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        *{box-sizing:border-box;}

        body{
            margin:0;
            font-family:Arial, sans-serif;
            background:#f4f6f9;
        }

        .navbar{
            background:#16a34a;
            color:white;
            padding:15px 30px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
        }

        .logo{
            font-size:24px;
            font-weight:bold;
        }

        .nav-links{
            display:flex;
            gap:15px;
            flex-wrap:wrap;
        }

        .nav-links a{
            color:white;
            text-decoration:none;
            font-weight:bold;
        }

        .nav-links a:hover{
            text-decoration:underline;
        }

        .hero{
            background:linear-gradient(rgba(0,0,0,.5), rgba(0,0,0,.5)),
            url('https://images.unsplash.com/photo-1501004318641-b39e6451bec6?q=80&w=1600');
            background-size:cover;
            background-position:center;
            color:white;
            text-align:center;
            padding:90px 20px;
        }

        .hero h1{font-size:48px;margin-bottom:15px;}
        .hero p{font-size:18px;max-width:700px;margin:auto;}

        .hero-btn{
            display:inline-block;
            margin-top:25px;
            background:#16a34a;
            color:white;
            padding:14px 28px;
            border-radius:8px;
            text-decoration:none;
            font-weight:bold;
        }

        .container{padding:40px 20px;}

        .section-title{
            text-align:center;
            margin-bottom:10px;
            font-size:32px;
        }

        .warning{
            text-align:center;
            color:#b45309;
            font-weight:bold;
            margin-bottom:25px;
        }

        .grid{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
            gap:20px;
        }

        .card{
            background:white;
            border-radius:12px;
            overflow:hidden;
            box-shadow:0 0 12px rgba(0,0,0,0.08);
        }

        .product-image{
            width:100%;
            height:220px;
            object-fit:cover;
        }

        .card-body{padding:18px;}

        .product-name{
            font-size:20px;
            font-weight:bold;
        }

        .price{
            color:#16a34a;
            font-size:22px;
            font-weight:bold;
            margin:8px 0;
        }

        .stock{color:#555;}

        .farmer{
            color:#777;
            font-size:14px;
            margin-bottom:15px;
        }

        .status{
            display:inline-block;
            padding:4px 10px;
            border-radius:6px;
            font-size:12px;
            color:white;
            margin-bottom:10px;
        }

        .active{background:green;}
        .inactive{background:red;}

        .btn{
            width:100%;
            display:block;
            text-align:center;
            background:#16a34a;
            color:white;
            padding:12px;
            border-radius:8px;
            text-decoration:none;
            font-weight:bold;
        }

        .btn:hover{
            background:#15803d;
        }

        footer{
            background:#111827;
            color:white;
            text-align:center;
            padding:25px;
            margin-top:40px;
        }
    </style>
</head>

<body>

<div class="navbar">
    <div class="logo">🌾 Farm Market</div>

    <div class="nav-links">
        <a href="/myformapp/index.php">Home</a>
        <a href="/myformapp/marketplace.php">Marketplace</a>

        <?php if(isset($_SESSION['user']) && isset($_SESSION['role'])): ?>

            <!-- FIXED: SINGLE ROLE-BASED DASHBOARD -->
            <a href="/myformapp/pages/dashboard.php">Dashboard</a>

            <a href="/myformapp/pages/logout.php">Logout</a>

        <?php else: ?>

            <a href="/myformapp/pages/login.php">Login</a>
            <a href="/myformapp/pages/register.php">Register</a>

        <?php endif; ?>
    </div>
</div>

<div class="hero">
    <h1>Fresh Farm Products Delivered</h1>
    <p>Buy directly from trusted farmers.</p>
    <a href="#products" class="hero-btn">🛒 Explore Marketplace</a>
</div>

<div class="container" id="products">

    <h2 class="section-title">🌱 Available Products</h2>

    <?php if(!isset($_SESSION['user'])): ?>
        <p class="warning">
            ⚠️ You are browsing as a guest. You can only view products. Login to purchase.
        </p>
    <?php endif; ?>

    <div class="grid">

        <?php if($result->num_rows > 0): ?>

            <?php while($row = $result->fetch_assoc()): ?>

                <div class="card">

                    <?php
                    $image = (!empty($row['image']))
                        ? "/myformapp/uploads/" . $row['image']
                        : "https://via.placeholder.com/400x300?text=Farm+Product";

                    $status = $row['status'] ?? 'active';
                    ?>

                    <img src="<?php echo htmlspecialchars($image); ?>" class="product-image">

                    <div class="card-body">

                        <div class="product-name">
                            <?php echo htmlspecialchars($row['name']); ?>
                        </div>

                        <div class="price">
                            KES <?php echo number_format($row['price'],2); ?>
                        </div>

                        <div class="stock">
                            Stock: <?php echo intval($row['quantity']); ?>
                        </div>

                        <span class="status <?php echo $status; ?>">
                            <?php echo strtoupper($status); ?>
                        </span>

                        <div class="farmer">
                            Farmer: <?php echo htmlspecialchars($row['farmer_name']); ?>
                        </div>

                        <?php if(isset($_SESSION['user']) && $_SESSION['role'] === 'buyer'): ?>

                            <a href="/myformapp/pages/view_products.php?id=<?php echo $row['product_id']; ?>" class="btn">
                                View & Buy
                            </a>

                        <?php elseif(isset($_SESSION['user'])): ?>

                            <a href="#" class="btn" style="background:#555; cursor:not-allowed;">
                                View Only
                            </a>

                        <?php else: ?>

                            <a href="/myformapp/pages/login.php" class="btn">
                                Login To Buy
                            </a>

                        <?php endif; ?>

                    </div>
                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p style="text-align:center;">No products available.</p>

        <?php endif; ?>

    </div>
</div>

<footer>
    <h3>🌾 Farm Market</h3>
    <p>Connecting Farmers & Buyers Digitally</p>
    <p>© <?php echo date("Y"); ?></p>
</footer>

</body>
</html>