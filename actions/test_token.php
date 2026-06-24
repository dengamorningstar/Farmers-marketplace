<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include("../includes/config.php");

$credentials = base64_encode(CONSUMER_KEY . ":" . CONSUMER_SECRET);

$ch = curl_init(TOKEN_URL);

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Basic $credentials"
]);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

if ($response === false) {
    die(curl_error($ch));
}

curl_close($ch);

echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";
?>