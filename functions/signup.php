<?php
session_start();
header('Content-Type: application/json');

ini_set('display_errors', 1);
error_reporting(E_ALL);

$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$password  = $_POST['password'] ?? '';

if (!$firstName || !$lastName || !$email || !$password) {
    echo json_encode([
        "success" => false,
        "message" => "All fields are required"
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid email format"
    ]);
    exit;
}

$accountsDir = __DIR__ . '/../json/';

/*
|--------------------------------------------------------------------------
| Check for duplicate email
|--------------------------------------------------------------------------
*/

foreach (glob($accountsDir . '*.json') as $file) {
    $data = json_decode(file_get_contents($file), true);
    if (!$data) continue;

    if (strcasecmp($data['email'] ?? '', $email) === 0) {
        echo json_encode([
            "success" => false,
            "message" => "Email already registered"
        ]);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Create User
|--------------------------------------------------------------------------
*/

$userId = uniqid('user_', true);
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$newUser = [
    "first_name" => $firstName,
    "last_name"  => $lastName,
    "email"      => $email,
    "password"   => $hashedPassword,
    "role"       => "customer",   // 👈 default role
    "projects"   => [],   // 👈 ADD THIS
    "logged_in"  => []
];

file_put_contents(
    $accountsDir . $userId . '.json',
    json_encode($newUser, JSON_PRETTY_PRINT)
);

echo json_encode([
    "success" => true,
    "message" => "Account created successfully"
]);