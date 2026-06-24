<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("../includes/config.php");

/* TEST VALUES */
$phone = "2547XXXXXXXX";
$amount = 1;

/* TOKEN */
$credentials = base64_encode(CONSUMER_KEY . ":" . CONSUMER_SECRET);

$ch = curl_init(TOKEN_URL);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Basic $credentials"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$token = json_decode(curl_exec($ch), true)['access_token'];
curl_close($ch);

/* STK */
$timestamp = date('YmdHis');
$password = base64_encode(SHORTCODE . PASSKEY . $timestamp);

$data = [
    "BusinessShortCode" => SHORTCODE,
    "Password" => $password,
    "Timestamp" => $timestamp,
    "TransactionType" => "CustomerPayBillOnline",
    "Amount" => $amount,
    "PartyA" => $phone,
    "PartyB" => SHORTCODE,
    "PhoneNumber" => $phone,
    "CallBackURL" => CALLBACK_URL,
    "AccountReference" => "TEST",
    "TransactionDesc" => "Test STK"
];

$ch = curl_init(STK_PUSH_URL);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer $token"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
curl_close($ch);

echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";
?>