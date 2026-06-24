<?php
session_start();
include("../includes/db.php");

/* =========================
   AUTH PROTECTION
========================= */
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

$farmer_id = intval($_SESSION['user']);

if (!isset($_GET['id'])) {
    die("Product ID missing");
}

$product_id = intval($_GET['id']);

/* =========================
   FETCH PRODUCT (OWNERSHIP LOCK)
========================= */
$stmt = $conn->prepare("
    SELECT * FROM products 
    WHERE product_id = ? AND farmer_id = ?
");

$stmt->bind_param("ii", $product_id, $farmer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Unauthorized access to product");
}

$product = $result->fetch_assoc();

/* =========================
   UPDATE LOGIC
========================= */
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name        = trim($_POST['name']);
    $description = trim($_POST['description'] ?? '');
    $price       = floatval($_POST['price']);
    $quantity    = intval($_POST['quantity']);

    /* =========================
       VALIDATION
    ========================= */
    if ($name === '') {
        $error = "Product name is required";
    } elseif ($price <= 0) {
        $error = "Price must be greater than 0";
    } elseif ($quantity < 0) {
        $error = "Quantity cannot be negative";
    } else {

        $image = $product['image'];

        /* =========================
           IMAGE UPDATE (OPTIONAL)
        ========================= */
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0) {

            $allowed = ["jpg", "jpeg", "png", "webp"];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {

                $image = time() . "_" . bin2hex(random_bytes(5)) . "." . $ext;

                $upload_dir = __DIR__ . "/../uploads/";

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $upload_path = $upload_dir . $image;

                if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $error = "Image upload failed";
                }
            } else {
                $error = "Invalid image format";
            }
        }

        /* =========================
           UPDATE DB (SAFE + OWNERSHIP LOCK)
        ========================= */
        if (empty($error)) {

            $stmt = $conn->prepare("
                UPDATE products 
                SET name = ?, description = ?, price = ?, quantity = ?, image = ?
                WHERE product_id = ? AND farmer_id = ?
            ");

            $stmt->bind_param(
                "ssdissi",
                $name,
                $description,
                $price,
                $quantity,
                $image,
                $product_id,
                $farmer_id
            );

            if ($stmt->execute()) {
                header("Location: farmer_products.php?updated=1");
                exit();
            } else {
                $error = "Update failed. Try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Edit Product</title>

<style>
body{
    font-family: Arial;
    background:#eef2f7;
    margin:0;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}

.container{
    display:flex;
    gap:20px;
    background:white;
    padding:20px;
    border-radius:12px;
    box-shadow:0 10px 25px rgba(0,0,0,0.1);
    width:750px;
}

.left, .right{
    width:50%;
}

img{
    width:100%;
    height:220px;
    object-fit:cover;
    border-radius:10px;
    margin-bottom:10px;
}

input, textarea{
    width:100%;
    padding:10px;
    margin:8px 0;
    border:1px solid #ddd;
    border-radius:6px;
}

button{
    width:100%;
    padding:10px;
    background:#16a34a;
    color:white;
    border:none;
    border-radius:6px;
    cursor:pointer;
}

button:hover{
    background:#15803d;
}

.error{
    color:red;
    font-size:13px;
}
</style>
</head>

<body>

<div class="container">

    <!-- LEFT -->
    <div class="left">

        <h3>Current Product</h3>

        <?php if (!empty($product['image'])): ?>
            <img src="../uploads/<?php echo $product['image']; ?>">
        <?php else: ?>
            <div style="background:#ddd;height:220px;display:flex;align-items:center;justify-content:center;">
                No Image
            </div>
        <?php endif; ?>

        <p><strong>Name:</strong> <?php echo htmlspecialchars($product['name']); ?></p>
        <p><strong>Price:</strong> KES <?php echo $product['price']; ?></p>
        <p><strong>Stock:</strong> <?php echo $product['quantity']; ?></p>

    </div>

    <!-- RIGHT -->
    <div class="right">

        <h3>Edit Product</h3>

        <?php if (!empty($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <input type="text" name="name"
                   value="<?php echo htmlspecialchars($product['name']); ?>"
                   required>

            <textarea name="description"><?php echo htmlspecialchars($product['description']); ?></textarea>

            <input type="number" step="0.01" name="price"
                   value="<?php echo $product['price']; ?>" required>

            <input type="number" name="quantity"
                   value="<?php echo $product['quantity']; ?>" required>

            <label>Change Image</label>
            <input type="file" name="image">

            <button type="submit">Save Changes</button>

        </form>

    </div>

</div>

</body>
</html>