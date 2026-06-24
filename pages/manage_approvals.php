<?php 
session_start();
include("../includes/db.php");

/* =========================
   PROTECT ADMIN ONLY
========================= */
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') {
    header("Location: /myformapp/pages/login.php");
    exit();
}

/* =========================
   CSRF TOKEN
========================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =========================
   FILTERS
========================= */
$status_filter = $_GET['status'] ?? 'all';
$role_filter   = $_GET['role'] ?? 'all';

/* =========================
   BUILD QUERY (ARCHITECTURE SAFE)
========================= */
$query = "
    SELECT 
        u.user_id,
        u.name,
        u.email,
        u.role,
        u.account_status,
        u.created_at,
        u.specific_location,
        u.zone_id,
        z.zone_label,
        z.county
    FROM users u
    LEFT JOIN delivery_zones z ON u.zone_id = z.zone_id
    WHERE 1=1
";

$params = [];
$types  = "";

/* STATUS FILTER */
if ($status_filter !== 'all') {
    $query .= " AND u.account_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

/* ROLE FILTER */
if ($role_filter !== 'all') {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

/* =========================
   ORDERING
========================= */
$query .= "
    ORDER BY
        CASE u.account_status
            WHEN 'pending' THEN 1
            WHEN 'active' THEN 2
            WHEN 'suspended' THEN 3
            ELSE 4
        END,
        u.user_id DESC
";

/* =========================
   PREPARE
========================= */
$stmt = $conn->prepare($query);

if (!$stmt) {
    die("Query failed: " . $conn->error);
}

/* =========================
   BIND DYNAMIC PARAMS
========================= */
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

/* =========================
   DASHBOARD COUNTS
========================= */
$total_users = $conn->query("
    SELECT COUNT(*) total FROM users
")->fetch_assoc()['total'];

$total_pending = $conn->query("
    SELECT COUNT(*) total
    FROM users
    WHERE account_status = 'pending'
")->fetch_assoc()['total'];

$total_active = $conn->query("
    SELECT COUNT(*) total
    FROM users
    WHERE account_status = 'active'
")->fetch_assoc()['total'];

$total_suspended = $conn->query("
    SELECT COUNT(*) total
    FROM users
    WHERE account_status = 'suspended'
")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Approvals</title>

<style>
body{
    font-family:Arial;
    background:#f4f6f9;
    margin:0;
}

.container{ padding:20px; }

.top-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:15px;
    margin-bottom:20px;
}

.stat-box{
    background:white;
    padding:18px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,0.08);
}

.stat-box h4{ margin:0; color:#666; font-size:14px; }

.stat-box p{
    font-size:28px;
    margin:10px 0 0;
    font-weight:bold;
    color:#007BFF;
}

.card{
    background:white;
    padding:18px;
    margin-bottom:15px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,0.08);
}

.badge{
    padding:6px 12px;
    border-radius:20px;
    color:white;
    font-size:12px;
    text-transform:capitalize;
    display:inline-block;
}

.pending{ background:#ff9800; }
.active{ background:#28a745; }
.suspended{ background:#dc3545; }

.btn{
    padding:9px 12px;
    border:none;
    cursor:pointer;
    border-radius:6px;
    margin-right:5px;
    color:white;
    font-weight:bold;
}

.approve{ background:#28a745; }
.suspend{ background:#ff9800; }
.reactivate{ background:#007bff; }

.row{
    display:flex;
    justify-content:space-between;
    flex-wrap:wrap;
    gap:15px;
}

.filters{ margin-bottom:15px; }

.filters a{
    margin-right:8px;
    text-decoration:none;
    padding:8px 12px;
    border-radius:6px;
    background:#e5e7eb;
    color:#111;
    display:inline-block;
    margin-bottom:8px;
}

.filters a.active{
    background:#10b981;
    color:white;
}

.alert{
    padding:12px;
    border-radius:6px;
    margin-bottom:15px;
}

.success{ background:#d1fae5; color:#065f46; }
.error{ background:#fee2e2; color:#7f1d1d; }

.meta{
    color:#666;
    font-size:14px;
    margin-top:5px;
}

.location{
    background:#eef2ff;
    color:#3730a3;
    padding:4px 10px;
    border-radius:20px;
    display:inline-block;
    font-size:12px;
    margin-top:5px;
}
</style>
</head>

<body>

<?php include("../includes/navbar.php"); ?>

<div class="container">

<h2>🛡️ User Approval Dashboard</h2>

<div class="top-grid">
    <div class="stat-box"><h4>Total Users</h4><p><?php echo $total_users; ?></p></div>
    <div class="stat-box"><h4>Pending Approvals</h4><p><?php echo $total_pending; ?></p></div>
    <div class="stat-box"><h4>Active Users</h4><p><?php echo $total_active; ?></p></div>
    <div class="stat-box"><h4>Suspended Users</h4><p><?php echo $total_suspended; ?></p></div>
</div>

<?php if (!empty($_SESSION['success'])): ?>
<div class="alert success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
<div class="alert error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>

<div class="filters">
    <a href="?status=all&role=<?php echo urlencode($role_filter); ?>" class="<?php echo $status_filter=='all'?'active':''; ?>">All</a>
    <a href="?status=pending&role=<?php echo urlencode($role_filter); ?>" class="<?php echo $status_filter=='pending'?'active':''; ?>">Pending</a>
    <a href="?status=active&role=<?php echo urlencode($role_filter); ?>" class="<?php echo $status_filter=='active'?'active':''; ?>">Active</a>
    <a href="?status=suspended&role=<?php echo urlencode($role_filter); ?>" class="<?php echo $status_filter=='suspended'?'active':''; ?>">Suspended</a>
</div>

<div class="filters">
    <a href="?status=<?php echo urlencode($status_filter); ?>&role=all" class="<?php echo $role_filter=='all'?'active':''; ?>">All Roles</a>
    <a href="?status=<?php echo urlencode($status_filter); ?>&role=buyer" class="<?php echo $role_filter=='buyer'?'active':''; ?>">Buyers</a>
    <a href="?status=<?php echo urlencode($status_filter); ?>&role=farmer" class="<?php echo $role_filter=='farmer'?'active':''; ?>">Farmers</a>
    <a href="?status=<?php echo urlencode($status_filter); ?>&role=delivery_person" class="<?php echo $role_filter=='delivery_person'?'active':''; ?>">Delivery</a>
</div>

<?php if ($result->num_rows > 0): ?>
<?php while($user = $result->fetch_assoc()): ?>

<div class="card">

<div class="row">

<div>
<h3><?php echo htmlspecialchars($user['name']); ?></h3>
<div class="meta"><?php echo htmlspecialchars($user['email']); ?></div>
<div class="meta">Role: <b><?php echo ucfirst(str_replace('_',' ', $user['role'])); ?></b></div>

<?php if (!empty($user['zone_id']) || !empty($user['specific_location'])): ?>
<div class="location">

<?php if (!empty($user['zone_label'])): ?>
🗺️ Zone: <?php echo htmlspecialchars($user['zone_label']); ?>
<?php else: ?>
🗺️ Zone: Unknown
<?php endif; ?>

<?php if (!empty($user['specific_location'])): ?>
| 📍 <?php echo htmlspecialchars($user['specific_location']); ?>
<?php endif; ?>

| 📍 Kiambu

</div>
<?php endif; ?>

<div class="meta">
Joined: <?php echo date("d M Y", strtotime($user['created_at'])); ?>
</div>

</div>

<div>
<span class="badge <?php echo $user['account_status']; ?>">
<?php echo ucfirst($user['account_status']); ?>
</span>
</div>

</div>

<br>

<form method="POST" action="../actions/update_user_status.php">
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
<input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">

<?php if ($user['account_status'] == 'pending'): ?>
<button class="btn approve" name="status" value="active">✅ Approve</button>
<button class="btn suspend" name="status" value="suspended">❌ Reject</button>
<?php elseif ($user['account_status'] == 'active'): ?>
<button class="btn suspend" name="status" value="suspended">⛔ Suspend</button>
<?php elseif ($user['account_status'] == 'suspended'): ?>
<button class="btn reactivate" name="status" value="active">♻ Reactivate</button>
<?php endif; ?>

</form>

</div>

<?php endwhile; ?>
<?php else: ?>
<div class="card">No users found for selected filters.</div>
<?php endif; ?>

</div>

</body>
</html>