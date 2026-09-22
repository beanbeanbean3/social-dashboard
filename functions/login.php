<?php
session_start();
header('Content-Type: application/json');

ini_set('display_errors', 1);
error_reporting(E_ALL);

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (!$username || !$password) {
    echo json_encode([
        "success" => false,
        "message" => "Missing username or password"
    ]);
    exit;
}

$accountsDir = __DIR__ . '/../json/';
$found = false;
$valid = false;
$userFilePath = null;

foreach (glob($accountsDir . '*.json') as $file) {

    $data = json_decode(file_get_contents($file), true);

    if (!$data) continue;

    $storedEmail = $data['email'] ?? '';
    $storedHash  = $data['password'] ?? '';

    if (strcasecmp($username, $storedEmail) === 0) {
        $found = true;
        $userFilePath = $file;

        if (password_verify($password, $storedHash)) {
            $valid = true;

            $_SESSION['user_id'] = basename($file, '.json');
            $_SESSION['email']   = $storedEmail;
            $_SESSION['role'] = $data['role'] ?? 'viewer';
            $_SESSION['projects'] = $data['projects'] ?? [];

            // log login time
            $data['logged_in'][] = date('Y-m-d H:i:s');
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
        }

        break;
    }
}

if ($valid) {
    if (
        $_SESSION['role'] === 'admin' ||
        $_SESSION['role'] === 'client'
    ) {

        echo json_encode([
            "success" => true,
            "redirect" => "social-list.php"
        ]);

    } else {

        echo json_encode([
            "success" => true,
            "redirect" => "dashboard.php"
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => $found ? "Invalid password" : "User not found"
    ]);
}