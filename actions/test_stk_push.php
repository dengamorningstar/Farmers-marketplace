<?php

date_default_timezone_set('Africa/Nairobi');

/* =========================
   MPESA CONFIG
========================= */
$consumerKey = "pHz4PaVFQfMpBAqMBSmRPqaFLR4sKyp0R6UmV5fzQtt2kQIU";
$consumerSecret = "REVkWwveB35RtYnMnvqDkduh0RdNu9wgjWzbaXzrZ7gPGZohglgxBGii1E9AOV5T";

$BusinessShortCode = "174379";

$Passkey = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";

/* =========================
   TEST PHONE NUMBER
   FORMAT: 2547XXXXXXXX
========================= */
$phone = "254742479000";

/* =========================
   TEST AMOUNT
========================= */
$amount = 1;

/* =========================
   CALLBACK URL
   (TEMPORARY LOCALHOST)
========================= */
$callbackUrl = "https://your-ngrok-url.ngrok-free.app/myformapp/actions/callback.php";

/* =========================
   GENERATE ACCESS TOKEN
========================= */
$credentials = base64_encode($consumerKey . ":" . $consumerSecret);

$tokenUrl = "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";

$curl = curl_init();

curl_setopt($curl, CURLOPT_URL, $tokenUrl);

curl_setopt($curl, CURLOPT_HTTPHEADER, [
    "Authorization: Basic $credentials"
]);

curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($curl);

if (curl_errno($curl)) {
    die("Token Error: " . curl_error($curl));
}

$result = json_decode($response);

if (!isset($result->access_token)) {

    echo "<h3>Failed to Generate Token</h3>";

    echo "<pre>";
    print_r($result);
    echo "</pre>";

    exit();
}

$accessToken = $result->access_token;

/* =========================
   GENERATE PASSWORD
========================= */
$timestamp = date("YmdHis");

$password = base64_encode(
    $BusinessShortCode .
    $Passkey .
    $timestamp
);

/* =========================
   STK PUSH REQUEST
========================= */
$stkUrl = "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest";

$data = [

    "BusinessShortCode" => $BusinessShortCode,

    "Password" => $password,

    "Timestamp" => $timestamp,

    "TransactionType" => "CustomerPayBillOnline",

    "Amount" => $amount,

    "PartyA" => $phone,

    "PartyB" => $BusinessShortCode,

    "PhoneNumber" => $phone,

    "CallBackURL" => $callbackUrl,

    "AccountReference" => "FarmMarket",

    "TransactionDesc" => "Test Payment"

];

$payload = json_encode($data);

/* =========================
   SEND STK PUSH
========================= */
$curl = curl_init();

curl_setopt($curl, CURLOPT_URL, $stkUrl);

curl_setopt($curl, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $accessToken
]);

curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

curl_setopt($curl, CURLOPT_POST, true);

curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);

$response = curl_exec($curl);

if (curl_errno($curl)) {
    die("STK Push Error: " . curl_error($curl));
}

curl_close($curl);

/* =========================
   SHOW RESPONSE
========================= */
$result = json_decode($response, true);

echo "<h2>STK Push Response</h2>";

echo "<pre>";
print_r($result);
echo "</pre>";

?>