<?php
include("../includes/db.php");

header("Content-Type: text/plain");

echo "=============================\n";
echo " FINANCIAL SYSTEM AUDIT\n";
echo "=============================\n\n";

/* ======================================================
   1. FARMER EARNINGS AUDIT
====================================================== */

echo "🔍 FARMER EARNINGS ISSUES\n";

$res = $conn->query("
    SELECT earning_id, farmer_id, status, payout_id
    FROM earnings
");

while ($row = $res->fetch_assoc()) {

    if ($row['status'] === 'locked' && empty($row['payout_id'])) {
        echo "❌ Locked without payout_id (earning_id: {$row['earning_id']})\n";
    }

    if ($row['status'] === 'active' && !empty($row['payout_id'])) {
        echo "⚠ Active but has payout_id (earning_id: {$row['earning_id']})\n";
    }

    if ($row['status'] === 'paid_out' && empty($row['payout_id'])) {
        echo "❌ Paid_out without payout reference (earning_id: {$row['earning_id']})\n";
    }
}

echo "\n";

/* ======================================================
   2. DELIVERY EARNINGS AUDIT
====================================================== */

echo "🔍 DELIVERY EARNINGS ISSUES\n";

$res = $conn->query("
    SELECT delivery_earning_id, delivery_person_id, status, payout_id
    FROM delivery_earnings
");

while ($row = $res->fetch_assoc()) {

    if ($row['status'] === 'locked' && empty($row['payout_id'])) {
        echo "❌ Locked without payout_id (delivery_earning_id: {$row['delivery_earning_id']})\n";
    }

    if ($row['status'] === 'active' && !empty($row['payout_id'])) {
        echo "⚠ Active but has payout_id (delivery_earning_id: {$row['delivery_earning_id']})\n";
    }

    if ($row['status'] === 'paid_out' && empty($row['payout_id'])) {
        echo "❌ Paid_out without payout reference (delivery_earning_id: {$row['delivery_earning_id']})\n";
    }
}

echo "\n";

/* ======================================================
   3. PAYOUT REQUEST AUDIT
====================================================== */

echo "🔍 PAYOUT REQUEST ISSUES\n";

$res = $conn->query("
    SELECT payout_id, user_id, status
    FROM payout_requests
");

while ($row = $res->fetch_assoc()) {

    // check if approved but no locked earnings exist
    $check = $conn->prepare("
        SELECT COUNT(*) as c
        FROM earnings
        WHERE payout_id = ?
          AND status = 'locked'
    ");
    $check->bind_param("i", $row['payout_id']);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();

    $check2 = $conn->prepare("
        SELECT COUNT(*) as c
        FROM delivery_earnings
        WHERE payout_id = ?
          AND status = 'locked'
    ");
    $check2->bind_param("i", $row['payout_id']);
    $check2->execute();
    $result2 = $check2->get_result()->fetch_assoc();

    $total_locked = $result['c'] + $result2['c'];

    if ($row['status'] === 'approved' && $total_locked === 0) {
        echo "❌ Approved payout with NO locked earnings (payout_id: {$row['payout_id']})\n";
    }

    if ($row['status'] === 'paid') {
        if ($total_locked === 0) {
            echo "⚠ Paid payout with no locked earnings (payout_id: {$row['payout_id']})\n";
        }
    }
}

echo "\n";

/* ======================================================
   4. DUPLICATE LOCK DETECTION
====================================================== */

echo "🔍 DUPLICATE LOCK CHECK\n";

$dup = $conn->query("
    SELECT payout_id, COUNT(*) as c
    FROM earnings
    WHERE payout_id IS NOT NULL
    GROUP BY payout_id
    HAVING c > 1
");

while ($row = $dup->fetch_assoc()) {
    echo "❌ Multiple earnings linked to same payout_id: {$row['payout_id']}\n";
}

$dup2 = $conn->query("
    SELECT payout_id, COUNT(*) as c
    FROM delivery_earnings
    WHERE payout_id IS NOT NULL
    GROUP BY payout_id
    HAVING c > 1
");

while ($row = $dup2->fetch_assoc()) {
    echo "❌ Multiple delivery earnings linked to same payout_id: {$row['payout_id']}\n";
}

echo "\n";

/* ======================================================
   FINAL RESULT
====================================================== */

echo "=============================\n";
echo " AUDIT COMPLETE\n";
echo "=============================\n";
?>