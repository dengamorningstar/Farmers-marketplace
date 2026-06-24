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

$admin_id = $_SESSION['user'];

/* =========================
   SEARCH
========================= */
$search = trim($_GET['search'] ?? '');
$search_param = "%" . $search . "%";

/* =========================
   USER STATISTICS
========================= */
$stats = $conn->query("
    SELECT role, COUNT(*) AS total
    FROM users
    GROUP BY role
");

$role_counts = [
    'buyer' => 0,
    'farmer' => 0,
    'delivery_person' => 0,
    'admin' => 0
];

while ($row = $stats->fetch_assoc()) {
    $role_counts[$row['role']] = $row['total'];
}

/* =========================
   FETCH USERS
========================= */
if ($search != '') {

    $stmt = $conn->prepare("
        SELECT user_id, name, email, role, account_status
        FROM users
        WHERE name LIKE ?
           OR email LIKE ?
           OR role LIKE ?
        ORDER BY user_id DESC
    ");

    $stmt->bind_param("sss", $search_param, $search_param, $search_param);

} else {

    $stmt = $conn->prepare("
        SELECT user_id, name, email, role, account_status
        FROM users
        ORDER BY user_id DESC
    ");
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Manage Users</title>

<style>
body{
    font-family:Arial;
    margin:0;
    background:#f4f6f9;
}

.container{
    padding:20px;
}

.topbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:10px;
    margin-bottom:20px;
}

.search-box{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

input[type=text]{
    padding:10px;
    width:260px;
}

button{
    padding:10px 14px;
    border:none;
    border-radius:5px;
    cursor:pointer;
}

.btn-search{
    background:#007BFF;
    color:white;
}

.btn-reset{
    background:#6c757d;
    color:white;
    text-decoration:none;
    padding:10px 14px;
    border-radius:5px;
}

.stats{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:15px;
    margin-bottom:20px;
}

.stat-card{
    background:white;
    padding:18px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,0.08);
}

.stat-number{
    font-size:24px;
    font-weight:bold;
    margin-top:8px;
}

.card{
    background:white;
    border-radius:10px;
    overflow:hidden;
    box-shadow:0 0 10px rgba(0,0,0,0.08);
}

table{
    width:100%;
    border-collapse:collapse;
}

th,td{
    padding:12px;
    border-bottom:1px solid #eee;
    text-align:left;
}

th{
    background:#333;
    color:white;
}

.badge{
    padding:5px 10px;
    border-radius:20px;
    color:white;
    font-size:12px;
    text-transform:capitalize;
}

.buyer{background:#007BFF;}
.farmer{background:#28a745;}
.delivery_person{background:#fd7e14;}
.admin{background:#6f42c1;}

.active{
    background:#28a745;
}

.suspended{
    background:#dc3545;
}

select{
    padding:7px;
}

.small-form{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
    align-items:center;
}

.btn-update{
    background:#28a745;
    color:white;
}

.protected{
    color:#777;
    font-weight:bold;
}

.empty{
    padding:20px;
}
</style>
</head>

<body>

<?php include("../includes/navbar.php"); ?>

<div class="container">

<div class="topbar">
    <h2>👥 Manage Users</h2>

    <form method="GET" class="search-box">
        <input type="text"
               name="search"
               placeholder="Search name, email, role..."
               value="<?php echo htmlspecialchars($search); ?>">

        <button type="submit" class="btn-search">Search</button>

        <a href="manage_users.php" class="btn-reset">Reset</a>
    </form>
</div>

<!-- =========================
     USER STATS
========================= -->
<div class="stats">

<div class="stat-card">
    Buyers
    <div class="stat-number">
        <?php echo $role_counts['buyer']; ?>
    </div>
</div>

<div class="stat-card">
    Farmers
    <div class="stat-number">
        <?php echo $role_counts['farmer']; ?>
    </div>
</div>

<div class="stat-card">
    Delivery
    <div class="stat-number">
        <?php echo $role_counts['delivery_person']; ?>
    </div>
</div>

<div class="stat-card">
    Admins
    <div class="stat-number">
        <?php echo $role_counts['admin']; ?>
    </div>
</div>

</div>

<!-- =========================
     USERS TABLE
========================= -->
<div class="card">

<?php if ($result->num_rows > 0): ?>

<table>

<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Email</th>
    <th>Role</th>
    <th>Status</th>
    <th>Change Role</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>

<tr>

<td><?php echo $row['user_id']; ?></td>

<td><?php echo htmlspecialchars($row['name']); ?></td>

<td><?php echo htmlspecialchars($row['email']); ?></td>

<td>
    <span class="badge <?php echo $row['role']; ?>">
        <?php echo str_replace('_',' ', $row['role']); ?>
    </span>
</td>

<td>
    <span class="badge <?php echo $row['account_status']; ?>">
        <?php echo $row['account_status']; ?>
    </span>
</td>

<td>

<form action="../actions/update_user_role.php"
      method="POST"
      class="small-form">

<input type="hidden"
       name="user_id"
       value="<?php echo $row['user_id']; ?>">

<select name="role">

<option value="buyer"
<?php if($row['role']=='buyer') echo 'selected'; ?>>
Buyer
</option>

<option value="farmer"
<?php if($row['role']=='farmer') echo 'selected'; ?>>
Farmer
</option>

<option value="delivery_person"
<?php if($row['role']=='delivery_person') echo 'selected'; ?>>
Delivery Person
</option>

<option value="admin"
<?php if($row['role']=='admin') echo 'selected'; ?>>
Admin
</option>

</select>

<button type="submit" class="btn-update">
Update
</button>

</form>

</td>

</tr>

<?php endwhile; ?>

</table>

<?php else: ?>

<div class="empty">
    No users found.
</div>

<?php endif; ?>

</div>

</div>

</body>
</html>