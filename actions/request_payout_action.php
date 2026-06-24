<?php
session_start();
include("../includes/db.php");

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_id = intval($_SESSION['user']);
$role    = trim($_SESSION['role'] ?? '');

$allowed_roles = ['farmer', 'delivery_person'];

if (!in_array($role, $allowed_roles)) {
    die("Unauthorized role");
}

/* =========================
   VALIDATE AMOUNT
========================= */
$amount = floatval($_POST['amount'] ?? 0);

if ($amount <= 0) {
    die("Invalid payout amount");
}

/* =========================
   START TRANSACTION
========================= */
$conn->begin_transaction();

try {

    /* =========================
       GET AVAILABLE BALANCE
    ========================== */
    if ($role === 'farmer') {

        $balanceStmt = $conn->prepare("
            SELECT
                COALESCE(SUM(net_amount),0) AS balance
            FROM earnings
            WHERE farmer_id = ?
              AND status = 'active'
        ");

        $balanceStmt->bind_param("i", $user_id);
    }

    elseif ($role === 'delivery_person') {

        $balanceStmt = $conn->prepare("
            SELECT
                COALESCE(SUM(amount),0) AS balance
            FROM delivery_earnings
            WHERE delivery_person_id = ?
              AND status = 'active'
        ");

        $balanceStmt->bind_param("i", $user_id);
    }

    else {
        throw new Exception("Invalid account role");
    }

    $balanceStmt->execute();

    $balance = floatval(
        $balanceStmt
            ->get_result()
            ->fetch_assoc()['balance'] ?? 0
    );

    /* =========================
       BALANCE VALIDATION
    ========================== */
    if ($amount > $balance) {
        throw new Exception(
            "Insufficient available balance"
        );
    }

    /* =========================
       PREVENT MULTIPLE REQUESTS
    ========================== */
    $check = $conn->prepare("
        SELECT payout_id
        FROM payout_requests
        WHERE user_id = ?
          AND status IN ('pending','approved')
        LIMIT 1
    ");

    $check->bind_param("i", $user_id);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        throw new Exception(
            "You already have an active payout request"
        );
    }

    /* =========================
       CREATE PAYOUT REQUEST
       FIX 1: ROLE ADDED HERE
    ========================== */
    $create = $conn->prepare("
        INSERT INTO payout_requests (
            user_id,
            role,
            amount,
            status,
            created_at
        )
        VALUES (
            ?, ?, ?, 'pending', NOW()
        )
    ");

    $create->bind_param(
        "isd",
        $user_id,
        $role,
        $amount
    );

    $create->execute();

    $payout_id = $conn->insert_id;

    /* =========================
       LOCK FARMER EARNINGS
    ========================== */
    if ($role === 'farmer') {

        $lock = $conn->prepare("
            UPDATE earnings
            SET
                status = 'locked',
                payout_id = ?,
                updated_at = NOW()
            WHERE farmer_id = ?
              AND status = 'active'
        ");

        $lock->bind_param(
            "ii",
            $payout_id,
            $user_id
        );

        $lock->execute();
    }

    /* =========================
       LOCK DELIVERY EARNINGS
       (FIXED: STATUS FLOW)
    ========================== */
    elseif ($role === 'delivery_person') {

        $lock = $conn->prepare("
            UPDATE delivery_earnings
            SET
                status = 'locked',
                payout_id = ?,
                updated_at = NOW()
            WHERE delivery_person_id = ?
              AND status = 'active'
        ");

        $lock->bind_param(
            "ii",
            $payout_id,
            $user_id
        );

        $lock->execute();
    }

    /* =========================
       NOTIFICATION
    ========================== */
    $message =
        "💰 Your payout request of KES " .
        number_format($amount, 2) .
        " has been submitted successfully.";

    $notify = $conn->prepare("
        INSERT INTO notifications (
            user_id,
            message,
            type,
            is_read,
            created_at
        )
        VALUES (
            ?, ?, 'payout', 0, NOW()
        )
    ");

    $notify->bind_param(
        "is",
        $user_id,
        $message
    );

    $notify->execute();

    /* =========================
       COMMIT
    ========================== */
    $conn->commit();

    header("Location: ../pages/wallet.php?success=1");
    exit();

} catch (Exception $e) {

    $conn->rollback();

    die(
        "Payout request failed: " .
        $e->getMessage()
    );
}
?>