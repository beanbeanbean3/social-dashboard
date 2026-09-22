<?php

session_start();

echo "<pre>";
print_r($_GET);
exit;

$code = $_GET['code'];

$postFields = [
    'code' => $code,
    'client_id' => "706601626911-m83f4och6e3t0mog5fnvq3vqsq1e05f9.apps.googleusercontent.com",
    'client_secret' => "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",
    'redirect_uri' => "https://filamsoftware.com/social-dashboard/functions/youtube-callback.php",
    'grant_type' => 'authorization_code'
];

$ch = curl_init("https://oauth2.googleapis.com/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));

$response = curl_exec($ch);
curl_close($ch);

$token = json_decode($response, true);

$accessToken = $token['access_token'];
$refreshToken = $token['refresh_token'];