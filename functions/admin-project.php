<?php

session_start();
header('Content-Type: application/json');

if (
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['admin', 'client'])
) {
    echo json_encode(["success"=>false]);
    exit;
}

$projectsDir = dirname(__DIR__) . '/Projects/';
$usersDir    = dirname(__DIR__) . '/json/';

if (!is_dir($projectsDir)) {
    mkdir($projectsDir, 0755, true);
}

$action = $_POST['action'] ?? '';

/* =========================
   ENCRYPTION FUNCTION
   ========================= */

  define('SECRET_KEY', '12345678901234567890123456789012'); // 32 chars
define('SECRET_IV',  '1234567890123456'); // 16 chars

function encryptToken($data) {
    return base64_encode(openssl_encrypt($data, 'AES-256-CBC', SECRET_KEY, 0, SECRET_IV));
}

function decryptToken($data) {
    return openssl_decrypt(base64_decode($data), 'AES-256-CBC', SECRET_KEY, 0, SECRET_IV);
}

/* =========================
   ADD OR EDIT
   ========================= */

   if ($action === 'add' || $action === 'edit') {

    $company = trim($_POST['company_name']);

        // Check duplicate project name
    if ($action === 'add') {

        foreach (glob($projectsDir . '*.json') as $projectFile) {

            $project = json_decode(
                file_get_contents($projectFile),
                true
            );

            if (
                isset($project['company_name']) &&
                strtolower(trim($project['company_name'])) === strtolower($company)
            ) {

                echo json_encode([
                    "success" => false,
                    "message" => "Project name already exists."
                ]);

                exit;
            }
        }
    }

    
    $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $company);
    $filePath = $projectsDir . $filename . '.json';

    $viewers = $_POST['viewers'] ?? [];

    // =========================
// LOAD EXISTING DATA (for edit)
// =========================
    $existing = [];

    if (
        $_SESSION['role'] === 'client' &&
        file_exists($filePath)
    ) {

        $checkProject = json_decode(file_get_contents($filePath), true);

        if (($checkProject['owner_id'] ?? '') !== $_SESSION['user_id']) {
            echo json_encode([
                "success" => false,
                "message" => "Unauthorized project access"
            ]);
            exit;
        }
    }

    if ($action === 'edit' && file_exists($filePath)) {
        $existing = json_decode(file_get_contents($filePath), true);
    }

// =========================
// TOKEN PROTECTION LOGIC
// =========================
    $fbTokenInput = trim($_POST['fb_token']);
    $igTokenInput = trim($_POST['ig_token']);
    $twTokenInput = trim($_POST['tw_token']);
    $liTokenInput = trim($_POST['li_token']);
    $ytTokenInput = trim($_POST['yt_api_key']);
    $ttTokenInput = trim($_POST['tt_token']);

/* =========================
   LOAD EXISTING ACCOUNTS
   (Backward Compatible)
   ========================= */

   $accounts = $existing['accounts'] ?? [];

   if (empty($accounts) && isset($existing['keys'])) {

    $accounts = [

        "facebook" => [
            "page_id" => $existing['keys']['fb_page_id'] ?? '',
            "token"   => $existing['keys']['fb_token'] ?? ''
        ],

        "instagram" => [
            "id"      => $existing['keys']['ig_id'] ?? '',
            "token"   => $existing['keys']['ig_token'] ?? ''
        ],

        "twitter" => [
            "id"      => $existing['keys']['tw_id'] ?? '',
            "token"   => $existing['keys']['tw_token'] ?? ''
        ],

        "linkedin" => [
            "organization_id" => $existing['keys']['li_id'] ?? '',
            "token"           => $existing['keys']['li_token'] ?? ''
        ],

        "youtube" => [
            "channel_id" => $existing['keys']['yt_channel_id'] ?? '',
            "api_key"    => $existing['keys']['yt_api_key'] ?? ''
        ],

        "tiktok" => [
            "id"    => $existing['keys']['tt_id'] ?? '',
            "token" => $existing['keys']['tt_token'] ?? ''
        ]        

    ];
}

$fbToken = $accounts['facebook']['token'] ?? '';
$igToken = $accounts['instagram']['token'] ?? '';
$twToken = $accounts['twitter']['token'] ?? '';
$liToken = $accounts['linkedin']['token'] ?? '';
$ytToken = $accounts['youtube']['api_key'] ?? '';
$ttToken = $accounts['tiktok']['token'] ?? '';


// Only overwrite if admin typed a new token
if ($fbTokenInput !== "" && $fbTokenInput !== "********") {
    $fbToken = encryptToken($fbTokenInput);
}

if ($igTokenInput !== "" && $igTokenInput !== "********") {
    $igToken = encryptToken($igTokenInput);
}

if ($twTokenInput !== "" && $twTokenInput !== "********") {
    $twToken = encryptToken($twTokenInput);
}

if ($liTokenInput !== "" && $liTokenInput !== "********") {
    $liToken = encryptToken($liTokenInput);
}

if ($ytTokenInput !== "" && $ytTokenInput !== "********") {
    $ytToken = encryptToken($ytTokenInput);
}

if ($ttTokenInput !== "" && $ttTokenInput !== "********") {
    $ttToken = encryptToken($ttTokenInput);
}

// =========================
// DEBUG LOGGING: decrypt always if token exists
$fbTokenPlain = $fbToken ? decryptToken($fbToken) : null;
$igTokenPlain = $igToken ? decryptToken($igToken) : null;

// =========================
// BUILD DATA
$data = [
    "company_name" => $company,
    "client_name"   => $_POST['client_name'],
    "owner_id"     => $_SESSION['user_id'],
    "email"         => $_POST['email'],
    "accounts" => [

        "facebook" => [
            "page_id" => $_POST['fb_page_id'],
            "token"   => $fbToken
        ],

        "instagram" => [
            "id" => $_POST['ig_id'],
            "token" => $igToken
        ],

        "twitter" => [
            "id" => $_POST['tw_id'],
            "token" => $twToken
        ],

        "linkedin" => [
            "organization_id" => $_POST['li_id'],
            "token" => $liToken
        ],

        "youtube" => [
            "channel_id" => $_POST['yt_channel_id'],
            "api_key"    => $ytToken
        ],

        "tiktok" => [
            "id"    => $_POST['tt_id'],
            "token" => $ttToken
        ]

    ],
    "keys" => [

        "fb_page_id" => $_POST['fb_page_id'],
        "fb_token"   => $fbToken,

        "ig_id"      => $_POST['ig_id'],
        "ig_token"   => $igToken,

        "tw_id"      => $_POST['tw_id'],
        "tw_token"   => $twToken,

        "li_id"      => $_POST['li_id'],
        "li_token"   => $liToken,

        "yt_channel_id" => $_POST['yt_channel_id'],
        "yt_api_key"    => $ytToken,

        "tt_id"         => $_POST['tt_id'],
        "tt_token"      => $ttToken
    ],

    "viewers" => $viewers
];

file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));

// =========================
// AUTO-SYNC USERS
foreach (glob($usersDir . '*.json') as $file) {
    $user = json_decode(file_get_contents($file), true);
    if (!$user) continue;

    $uid = basename($file, '.json');

    if (!isset($user['projects'])) {
        $user['projects'] = [];
    }

    if (in_array($uid, $viewers)) {
        if (!in_array($company, $user['projects'])) {
            $user['projects'][] = $company;
        }
    } else {
        $user['projects'] = array_values(array_diff($user['projects'], [$company]));
    }

    file_put_contents($file, json_encode($user, JSON_PRETTY_PRINT));
}

// =========================
// RETURN JSON
echo json_encode([
    "success" => true
]);
exit;
}

/* =========================
   DELETE
   ========================= */

   if ($action === 'delete') {

    $id = $_POST['id'];
    $file = $projectsDir . $id . '.json';

    if (file_exists($file)) {

        $projectData = json_decode(file_get_contents($file), true);

        if (
            $_SESSION['role'] === 'client' &&
            ($projectData['owner_id'] ?? '') !== $_SESSION['user_id']
        ) {
            echo json_encode([
                "success" => false,
                "message" => "Unauthorized"
            ]);
            exit;
        }

        unlink($file);

        // Remove from users
        foreach (glob($usersDir . '*.json') as $uFile) {

            $user = json_decode(file_get_contents($uFile), true);
            if (!$user) continue;

            if (isset($user['projects'])) {
                $user['projects'] = array_diff($user['projects'], [$id]);
                file_put_contents($uFile, json_encode($user, JSON_PRETTY_PRINT));
            }
        }

        echo json_encode(["success"=>true]);
    } else {
        echo json_encode(["success"=>false]);
    }

    exit;
}