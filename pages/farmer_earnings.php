<?php
session_start();

/* =========================
   DISABLED PAGE SAFETY LOCK
========================= */
http_response_code(403);
exit("This page has been disabled. Earnings are now managed through the main earnings system.");
?>