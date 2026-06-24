<?php

/* =========================
   SAFARICOM SANDBOX KEYS
========================= */
$consumerKey = "pHz4PaVFQfMpBAqMBSmRPqaFLR4sKyp0R6UmV5fzQtt2kQIU";
$consumerSecret = "REVkWwveB35RtYnMnvqDkduh0RdNu9wgjWzbaXzrZ7gPGZohglgxBGii1E9AOV5T";

/* =========================
   GENERATE ACCESS TOKEN
========================= */
$credentials = base64_encode($consumerKey . ":" . $consumerSecret);

$url = "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";

$curl = curl_init();

curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    "Authorization: Basic $credentials"
]);

curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($curl);

if (curl_errno($curl)) {
    die("cURL Error: " . curl_error($curl));
}

curl_close($curl);

/* =========================
   DECODE RESPONSE
========================= */
$result = json_decode($response);

if (isset($result->access_token)) {

    echo $result->access_token;

} else {

    echo "Failed to generate token";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
}
?>