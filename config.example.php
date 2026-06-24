<?php

define("APP_URL", "https://your-ngrok-subdomain.ngrok-free.dev/myformapp");
define("APP_NAME", "AgroMarket");

/* =========================
   ENVIRONMENT MODE
========================= */
define("APP_ENV", "testing"); // testing | production

/* =========================
   PAYMENT MODE CONTROL
   - simulation = fake success flow
   - live = real Daraja callback
========================= */
define("PAYMENT_MODE", "simulation"); 
// options: "simulation", "live"

/* =========================
   DARAJA CONFIG (PLACEHOLDERS FOR SECURITY)
========================= */
define("CONSUMER_KEY", trim("YOUR_DARARAJA_CONSUMER_KEY"));
define("CONSUMER_SECRET", trim("YOUR_DARAJA_CONSUMER_SECRET"));
define("PASSKEY", trim("YOUR_DARAJA_PASSKEY"));
define("SHORTCODE", "174379");

/* =========================
   CALLBACK URL
========================= */
define("CALLBACK_URL", APP_URL . "/actions/callback.php");

/* =========================
   DARAJA ENDPOINTS
========================= */
define("TOKEN_URL", "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials");
define("STK_PUSH_URL", "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest");

/* =========================
   FILE PATHS
========================= */
define("UPLOAD_PATH", __DIR__ . "/../uploads/");
define("LOG_PATH", __DIR__ . "/../actions/stk_log.txt");

/* =========================
   BUSINESS RULES
========================= */
define("DEFAULT_CURRENCY", "KES");
define("PLATFORM_COMMISSION", 0.10);

/* =========================
   HELPER FLAG (IMPORTANT)
========================= */
define("IS_SIMULATION", PAYMENT_MODE === "simulation");

?>