<?php
session_start();

/* =========================
   DISABLED ENDPOINT
   (Legacy toggle_users system removed)
========================= */

/*
   This file has been disabled because
   user status management has been migrated
   to update_user_status.php.
*/

header("Location: /myformapp/pages/manage_users.php");
exit();
?>