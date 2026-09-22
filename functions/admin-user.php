<?php


ini_set('display_errors', 1);
error_reporting(E_ALL);


require __DIR__ . '/mailer.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$accountsDir = dirname(__DIR__) . '/json/';
$action = $_POST['action'] ?? '';



/* =========================
   HELPER: GENERATE PASSWORD
========================= */
function generatePassword($length = 10) {
    return substr(str_shuffle(
        'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%'
    ), 0, $length);
}


/* =========================
   ADD USER
========================= */
if ($action === 'add') {

    $first = trim($_POST['first_name']);
    $last  = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $role  = $_POST['role'];


        // Check duplicate email
    foreach (glob($accountsDir . '*.json') as $file) {

        $user = json_decode(file_get_contents($file), true);

        if (
            isset($user['email']) &&
            strtolower(trim($user['email'])) === strtolower($email)
        ) {

            echo json_encode([
                "success" => false,
                "message" => "Email already exists."
            ]);

            exit;
        }
    }

    if (!$first || !$last || !$email) {
        echo json_encode(["success"=>false,"message"=>"All fields required"]);
        exit;
    }

    $userId = uniqid('user_', true);
    $generatedPassword = generatePassword();

    $newUser = [
        "first_name" => $first,
        "last_name"  => $last,
        "email"      => $email,
        "password"   => password_hash($generatedPassword, PASSWORD_DEFAULT),
        "role"       => $role,
        "projects"   => []
    ];

    file_put_contents(
        $accountsDir . $userId . '.json',
        json_encode($newUser, JSON_PRETTY_PRINT)
    );

    sendUserEmail("$first $last", $email, $generatedPassword);

    echo json_encode(["success"=>true]);
    exit;
}


/* =========================
   RESET PASSWORD
========================= */
if ($action === 'reset_password') {

    $id = $_POST['id'] ?? '';
    $file = $accountsDir . $id . '.json';

    if (!file_exists($file)) {
        echo json_encode(["success"=>false,"message"=>"User not found"]);
        exit;
    }

    $data = json_decode(file_get_contents($file), true);

    $newPassword = generatePassword();
    $data['password'] = password_hash($newPassword, PASSWORD_DEFAULT);

    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

    sendUserEmail(
        $data['first_name'] . " " . $data['last_name'],
        $data['email'],
        $newPassword
    );

    echo json_encode(["success"=>true]);
    exit;
}


/* =========================
   EDIT USER (INFO ONLY)
========================= */
if ($action === 'edit') {

    $id = $_POST['id'] ?? '';
    $file = $accountsDir . $id . '.json';

    if (!file_exists($file)) {
        echo json_encode(["success"=>false]);
        exit;
    }

    $data = json_decode(file_get_contents($file), true);

    $data['first_name'] = trim($_POST['first_name']);
    $data['last_name']  = trim($_POST['last_name']);
    $data['email']      = trim($_POST['email']);
    $data['role']       = $_POST['role'];

    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

    echo json_encode(["success"=>true]);
    exit;
}


/* =========================
   DELETE USER
========================= */
if ($action === 'delete') {

    $id = $_POST['id'] ?? '';
    $file = $accountsDir . $id . '.json';

    if (file_exists($file)) {
        unlink($file);
        echo json_encode(["success"=>true]);
    } else {
        echo json_encode(["success"=>false]);
    }
    exit;
}

echo json_encode(["success"=>false,"message"=>"Invalid action"]);
exit;