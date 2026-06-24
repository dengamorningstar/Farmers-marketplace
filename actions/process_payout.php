<?php
session_start();
include("../includes/db.php");
include("../engines/earnings_engine.php");
include("../engines/payout_engine.php");

/* =========================
   ADMIN AUTH
========================= */
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    die("Unauthorized access");
}

/* =========================
   INPUT VALIDATION
========================= */
$payout_id = intval($_POST['payout_id'] ?? 0);
$action    = trim($_POST['action'] ?? '');

$allowed_actions = ['approve', 'reject', 'pay'];

if ($payout_id <= 0 || !in_array($action, $allowed_actions)) {
    die("Invalid request");
}

/* =========================
   FETCH PAYOUT + USER
========================= */
$stmt = $conn->prepare("
    SELECT pr.*, u.role
    FROM payout_requests pr
    INNER JOIN users u ON pr.user_id = u.user_id
    WHERE pr.payout_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $payout_id);
$stmt->execute();

$payout = $stmt->get_result()->fetch_assoc();

if (!$payout) {
    die("Payout not found");
}

/* =========================
   STRICT STATE CONTROL
========================= */
if (in_array($payout['status'], ['paid', 'paid_out'])) {
    die("Already paid");
}

if (in_array($payout['status'], ['rejected', 'paid']) && $action !== 'approve') {
    die("Invalid payout state transition");
}

/* =========================
   PAY SAFETY CHECK
========================= */
if ($action === 'pay' && $payout['status'] !== 'approved') {
    die("Only approved payouts can be paid");
}

/* =========================
   START TRANSACTION
========================= */
$conn->begin_transaction();

try {

    $user_id = (int)$payout['user_id'];

    if ($user_id <= 0) {
        throw new Exception("Invalid payout user");
    }

    $amount  = (float)$payout['amount'];

    /* =========================
       ENGINE CALLS ONLY
    ========================== */

    if ($action === 'approve') {

        $ok = PayoutEngine::approve($conn, $payout);
        if ($ok === false) {
            throw new Exception("Approval failed");
        }

        $message = "Your payout of KES " . number_format($amount, 2) . " was approved.";
    }

    elseif ($action === 'reject') {

        $ok = PayoutEngine::reject($conn, $payout);
        if ($ok === false) {
            throw new Exception("Rejection failed");
        }

        $message = "Your payout request was rejected.";
    }

    elseif ($action === 'pay') {

        $ok = PayoutEngine::pay($conn, $payout);
        if ($ok === false) {
            throw new Exception("Payment processing failed");
        }

        $message = "Payout of KES " . number_format($amount, 2) . " has been paid.";
    }

    /* =========================
       NOTIFICATION
    ========================== */
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, message, type, created_at)
        VALUES (?, ?, 'payout', NOW())
    ");

    $stmt->bind_param("is", $user_id, $message);
    $stmt->execute();

    $conn->commit();

    header("Location: ../pages/admin_payouts.php?success=1");
    exit();

} catch (Exception $e) {

    $conn->rollback();
    die("Payout error: " . $e->getMessage());
}
?>