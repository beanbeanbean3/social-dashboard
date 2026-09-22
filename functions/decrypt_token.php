<?php
// decrypt_token.php
header('Content-Type: text/plain');

// Get the encrypted token from URL
$token = $_GET['token'] ?? '';

// ✅ Set the same key & IV that were used for encrypting the token
define('SECRET_KEY', '12345678901234567890123456789012'); // 32 chars
define('SECRET_IV',  '1234567890123456'); // 16 chars

function decryptToken($data) {
    return openssl_decrypt(base64_decode($data), 'AES-256-CBC', SECRET_KEY, 0, SECRET_IV);
}

// Output the decrypted token
echo decryptToken($token);