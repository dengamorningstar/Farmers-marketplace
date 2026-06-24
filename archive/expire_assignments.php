<?php
include("../includes/db.php");

/* =========================
   EXPIRE OLD ASSIGNMENTS
   (RUN VIA CRON JOB)
========================= */

$stmt = $conn->prepare("
    UPDATE order_items
    SET 
        delivery_person_id = NULL,
        delivery_status = 'pending',
        assigned_at = NULL,
        assignment_expires_at = NULL
    WHERE delivery_status = 'assigned'
    AND assignment_expires_at IS NOT NULL
    AND assignment_expires_at < NOW()
");

$stmt->execute();

$affected = $stmt->affected_rows;

/* =========================
   OPTIONAL: LOGGING
========================= */
echo json_encode([
    "status" => "success",
    "expired_records" => $affected
]);
?>