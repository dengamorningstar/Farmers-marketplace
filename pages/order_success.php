<?php
/*
|--------------------------------------------------------------------------
| LEGACY FILE - DISABLED
|--------------------------------------------------------------------------
| order_success.php has been replaced by:
| - payment_pending.php
| - payment_success.php
| - payment_failed.php
|
| This file now redirects users to the correct modern payment flow.
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   VALIDATE ORDER ID
========================= */
$order_id = isset($_GET['order_id'])
    ? intval($_GET['order_id'])
    : 0;

if ($order_id <= 0) {
    die("Invalid order");
}

/* =========================
   REDIRECT TO NEW FLOW
========================= */
header(
    "Location: payment_pending.php?order_id=" . $order_id
);

exit();
?>