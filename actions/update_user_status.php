<?php
session_start();
include("../includes/db.php");

/* =========================
   ADMIN ONLY
========================= */
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: /myformapp/pages/login.php");
    exit();
}

/* =========================
   METHOD CHECK
========================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method";
    header("Location: /myformapp/pages/manage_approvals.php");
    exit();
}

/* =========================
   CSRF CHECK
========================= */
if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    $_POST['csrf_token'] !== $_SESSION['csrf_token']
) {
    $_SESSION['error'] = "Invalid CSRF token";
    header("Location: /myformapp/pages/manage_approvals.php");
    exit();
}

/* =========================
   INPUT
========================= */
$user_id = intval($_POST['user_id'] ?? 0);
$status  = trim($_POST['status'] ?? '');

$allowed = ['active', 'suspended'];

if ($user_id <= 0 || !in_array($status, $allowed)) {
    $_SESSION['error'] = "Invalid request data";
    header("Location: /myformapp/pages/manage_approvals.php");
    exit();
}

/* =========================
   PREVENT SELF ACTION
========================= */
if ($user_id === intval($_SESSION['user'])) {
    $_SESSION['error'] = "You cannot modify your own account.";
    header("Location: /myformapp/pages/manage_approvals.php");
    exit();
}

/* =========================
   TRANSACTION START
========================= */
$conn->begin_transaction();

try {

    /* LOCK USER */
    $stmt = $conn->prepare("
        SELECT account_status, name
        FROM users
        WHERE user_id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        throw new Exception("User not found");
    }

    $user = $res->fetch_assoc();

    if ($user['account_status'] === $status) {
        throw new Exception("No change needed");
    }

    /* UPDATE STATUS */
    $stmt = $conn->prepare("
        UPDATE users
        SET account_status = ?, updated_at = NOW()
        WHERE user_id = ?
    ");
    $stmt->bind_param("si", $status, $user_id);
    $stmt->execute();

    /* NOTIFICATION */
    $message = match($status) {
        'active' => "Your account has been approved.",
        'suspended' => "Your account has been suspended.",
        default => "Your account status has changed."
    };

    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, message, type, created_at)
        VALUES (?, ?, 'account', NOW())
    ");
    $stmt->bind_param("is", $user_id, $message);
    $stmt->execute();

    $conn->commit();

    $_SESSION['success'] = "User status updated successfully.";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
}

header("Location: /myformapp/pages/manage_approvals.php");
exit();
?>