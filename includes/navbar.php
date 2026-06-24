<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   SAFE DB INCLUDE
========================= */
$rootPath = __DIR__ . "/../includes/db.php";
if (file_exists($rootPath)) {
    include_once($rootPath);
}

/* =========================
   LOAD CONFIG (IMPORTANT FIX)
========================= */
$configPath = __DIR__ . "/../includes/config.php";
if (file_exists($configPath)) {
    include_once($configPath);
}

/* =========================
   USER CHECK
========================= */
$role = $_SESSION['role'] ?? null;
$user_id = $_SESSION['user'] ?? null;

/* =========================
   DEFAULT VALUES
========================= */
$cart_count = 0;
$wallet_balance = 0;
$notif_count = 0;

/* =========================
   CART (BUYER)
========================= */
if ($role === 'buyer' && isset($conn)) {

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(quantity),0) AS total
        FROM cart
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
}

/* =========================
   WALLET (FARMER)
========================= */
if ($role === 'farmer' && isset($conn)) {

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(net_amount),0) AS balance
        FROM earnings
        WHERE farmer_id = ? AND status = 'paid'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $wallet_balance = $stmt->get_result()->fetch_assoc()['balance'] ?? 0;
}

/* =========================
   NOTIFICATIONS
========================= */
if ($role && isset($conn)) {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM notifications
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $notif_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
}
?>

<style>
.navbar {
    background: #111827;
    padding: 14px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    font-family: Arial;
}

.navbar a {
    color: #e5e7eb;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 14px;
}

.navbar a:hover {
    background: #374151;
    color: #fff;
}

.brand {
    font-weight: bold;
    color: #10b981 !important;
}

.spacer {
    margin-left: auto;
}

.badge {
    background: red;
    color: white;
    border-radius: 12px;
    padding: 2px 6px;
    font-size: 11px;
    margin-left: 4px;
}
</style>

<div class="navbar">

    <a href="/myformapp/index.php" class="brand">🌾 AgroMarket</a>

    <?php if ($role): ?>
        <a href="/myformapp/pages/dashboard.php">Dashboard</a>
        <a href="/myformapp/pages/profile.php">👤 Profile</a>

        <a href="/myformapp/pages/notifications.php">
            🔔 Notifications
            <?php if ($notif_count > 0): ?>
                <span class="badge"><?php echo $notif_count; ?></span>
            <?php endif; ?>
        </a>
    <?php endif; ?>

    <!-- BUYER -->
    <?php if ($role === 'buyer'): ?>

        <a href="/myformapp/pages/products.php">🛒 View Products</a>

        <a href="/myformapp/pages/my_orders.php">Orders</a>

    <!-- FARMER -->
    <?php elseif ($role === 'farmer'): ?>

        <a href="/myformapp/pages/farmer_products.php">My Products</a>

        <a href="/myformapp/pages/farmer_orders.php">Orders</a>

        <a href="/myformapp/pages/wallet.php">
            💰 Wallet (KES <?php echo number_format($wallet_balance, 0); ?>)
        </a>

    <!-- DELIVERY PERSON -->
    <?php elseif ($role === 'delivery_person'): ?>

        <a href="/myformapp/pages/delivery_orders.php">🚚 My Assignments</a>

        <a href="/myformapp/pages/wallet.php">💰 Wallet</a>

    <!-- ADMIN -->
    <?php elseif ($role === 'admin'): ?>

        <a href="/myformapp/pages/manage_users.php">Users</a>
        <a href="/myformapp/pages/manage_approvals.php">Approvals</a>
        <a href="/myformapp/pages/assign_delivery.php">🚚 Delivery Assignments</a>
        <a href="/myformapp/pages/admin_payments.php">Payments</a>
        <a href="/myformapp/pages/admin_payouts.php">Payouts</a>

    <?php endif; ?>

    <div class="spacer"></div>

    <?php if ($role): ?>
        <a href="/myformapp/pages/logout.php">Logout</a>
    <?php else: ?>
        <a href="/myformapp/pages/login.php">Login</a>
    <?php endif; ?>

</div>