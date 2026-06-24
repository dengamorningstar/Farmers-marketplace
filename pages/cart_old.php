<?php
session_start();

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Cart</title>

    <style>
        body {
            font-family: Arial;
            background: #f4f6f9;
            padding: 20px;
        }

        table {
            width: 80%;
            margin: auto;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 10px;
            text-align: center;
            border: 1px solid #ddd;
        }

        h2 {
            text-align: center;
        }

        .total {
            text-align: center;
            margin-top: 20px;
        }

        .btn {
            padding: 6px 10px;
            background: red;
            color: white;
            text-decoration: none;
        }

        .update-btn {
            padding: 5px 10px;
            background: #007BFF;
            color: white;
            border: none;
            cursor: pointer;
        }

        .checkout {
            display: block;
            width: 220px;
            margin: 20px auto;
            padding: 10px;
            text-align: center;
            background: green;
            color: white;
            text-decoration: none;
        }

        input[type="number"] {
            width: 60px;
            padding: 5px;
        }
    </style>
</head>

<body>

<h2>Your Cart</h2>

<?php if (empty($_SESSION['cart'])): ?>

    <p style="text-align:center;">Your cart is empty</p>

<?php else: ?>

<table>
    <tr>
        <th>Product</th>
        <th>Price</th>
        <th>Quantity</th>
        <th>Total</th>
        <th>Action</th>
    </tr>

    <?php
    $grand_total = 0;

    foreach ($_SESSION['cart'] as $product_id => $item):
        $total = $item['price'] * $item['quantity'];
        $grand_total += $total;
    ?>

    <tr>
        <td><?php echo htmlspecialchars($item['name']); ?></td>

        <td>KES <?php echo $item['price']; ?></td>

        <!-- ✅ UPDATED QUANTITY FORM -->
        <td>
            <form action="../actions/update_cart.php" method="POST" style="display:flex; gap:5px; justify-content:center;">
                
                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">

                <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" required>

                <button type="submit" class="update-btn">🔄</button>

            </form>
        </td>

        <td>KES <?php echo $total; ?></td>

        <td>
            <a class="btn" href="../actions/remove_from_cart.php?id=<?php echo $product_id; ?>">❌ Remove</a>
        </td>
    </tr>

    <?php endforeach; ?>

</table>

<div class="total">
    <h3>Grand Total: KES <?php echo $grand_total; ?></h3>
</div>

<a class="checkout" href="../actions/checkout.php">💳 Proceed to Checkout</a>

<?php endif; ?>

</body>
</html>