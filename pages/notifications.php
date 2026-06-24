<?php
session_start();
include("../includes/db.php");

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user']);

/* =========================
   MARK SINGLE NOTIFICATION AS READ
========================= */
if (isset($_GET['read_id'])) {

    $nid = intval($_GET['read_id']);

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE notification_id = ? AND user_id = ?
    ");

    $stmt->bind_param("ii", $nid, $user_id);
    $stmt->execute();

    header("Location: notifications.php");
    exit();
}

/* =========================
   MARK ALL AS READ
========================= */
if (isset($_GET['mark_all']) && $_GET['mark_all'] == 1) {

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = ?
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    header("Location: notifications.php");
    exit();
}

/* =========================
   FETCH NOTIFICATIONS
========================= */
$stmt = $conn->prepare("
    SELECT 
        notification_id,
        message,
        type,
        is_read,
        created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

/* =========================
   UNREAD COUNT
========================= */
$count_stmt = $conn->prepare("
    SELECT COUNT(*) AS unread
    FROM notifications
    WHERE user_id = ? AND is_read = 0
");

$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$unread_count = $count_stmt->get_result()->fetch_assoc()['unread'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
<title>Notifications</title>

<style>
body{
    font-family: Arial;
    margin: 0;
    background: #f4f6f9;
}

.container{
    padding: 20px;
    max-width: 750px;
    margin: auto;
}

.top-bar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:15px;
}

.card{
    background: white;
    padding: 15px;
    margin-bottom: 12px;
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0,0,0,.08);
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.unread{
    border-left: 5px solid #f59e0b;
}

.meta{
    font-size: 12px;
    color: #777;
    margin-top: 6px;
}

.type{
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 20px;
    background: #007bff;
    color: white;
    text-transform: uppercase;
    display: inline-block;
    margin-top: 6px;
}

.read-btn{
    font-size: 12px;
    text-decoration: none;
    color: #007bff;
    white-space: nowrap;
}

.actions a{
    font-size: 13px;
    margin-left: 10px;
    text-decoration: none;
    color: #007bff;
}

.actions a:hover{
    text-decoration: underline;
}
</style>
</head>

<body>

<?php include("../includes/navbar.php"); ?>

<div class="container">

<div class="top-bar">
    <h2>🔔 Notifications</h2>

    <div class="actions">
        <span>Unread: <?php echo $unread_count; ?></span>

        <?php if ($unread_count > 0): ?>
            <a href="?mark_all=1">Mark all as read</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($result->num_rows > 0): ?>

    <?php while($row = $result->fetch_assoc()): ?>

        <div class="card <?php echo $row['is_read'] ? '' : 'unread'; ?>">

            <div>

                <p style="margin:0;">
                    <?php echo htmlspecialchars($row['message']); ?>
                </p>

                <div class="meta">
                    <?php echo htmlspecialchars($row['created_at']); ?>
                </div>

                <?php if (!empty($row['type'])): ?>
                    <span class="type">
                        <?php echo htmlspecialchars($row['type']); ?>
                    </span>
                <?php endif; ?>

            </div>

            <?php if (!$row['is_read']): ?>
                <a class="read-btn"
                   href="?read_id=<?php echo (int)$row['notification_id']; ?>">
                    Mark read
                </a>
            <?php endif; ?>

        </div>

    <?php endwhile; ?>

<?php else: ?>

    <p>No notifications yet.</p>

<?php endif; ?>

</div>

</body>
</html>