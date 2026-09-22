<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

session_start();

/* ===============================
   1️⃣ Validate Logged-in User
   ================================ */

   if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => true, 'message' => 'Not logged in']);
    exit;
}

if (empty($_SESSION['projects'])) {
    echo json_encode(['error' => true, 'message' => 'No project assigned']);
    exit;
}

/* ===============================
   2️⃣ Load Project File
   ================================ */

   $projectName = $_GET['project'] ?? $_SESSION['projects'][0];

// Security check
   if (!in_array($projectName, $_SESSION['projects'])) {
    echo json_encode(['error' => true, 'message' => 'Invalid project']);
    exit;
}

// Apply same sanitize rule used when saving
$filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $projectName);

$projectFile = dirname(__DIR__) . "/Projects/" . $filename . ".json";

if (!file_exists($projectFile)) {
    echo json_encode(['error' => true, 'message' => 'Project file not found']);
    exit;
}

$projectData = json_decode(file_get_contents($projectFile), true);

/* ===============================
   3️⃣ Decrypt Tokens
   ================================ */

define('SECRET_KEY', '12345678901234567890123456789012'); // 32 chars
define('SECRET_IV',  '1234567890123456'); // 16 chars

function decryptToken($data) {
    return openssl_decrypt(
        base64_decode($data),
        'AES-256-CBC',
        SECRET_KEY,
        0,
        SECRET_IV
    );
}



$accounts = $projectData['accounts'] ?? [];

if (empty($accounts)) {

    $accounts = [
        "facebook" => [
            "page_id" => $projectData['keys']['fb_page_id'] ?? '',
            "token"   => $projectData['keys']['fb_token'] ?? ''
        ],
        "instagram" => [
            "id" => $projectData['keys']['ig_id'] ?? '',
            "token" => $projectData['keys']['ig_token'] ?? ''
        ],
        "twitter" => [
            "id" => $projectData['keys']['tw_id'] ?? '',
            "token" => $projectData['keys']['tw_token'] ?? ''
        ],
        "linkedin" => [
            "organization_id" => $projectData['keys']['li_id'] ?? '',
            "token" => $projectData['keys']['li_token'] ?? ''
        ],
        "youtube" => [
            "channel_id" => $projectData['keys']['yt_channel_id'] ?? '',
            "api_key"    => $projectData['keys']['yt_api_key'] ?? ''
        ],
        "tiktok" => [
            "id"    => $projectData['keys']['tt_id'] ?? '',
            "token" => $projectData['keys']['tt_token'] ?? ''
        ]
    ];
}

$fb_page_id = $accounts['facebook']['page_id'] ?? null;
$fb_token   = decryptToken($accounts['facebook']['token'] ?? '');

$ig_id      = $accounts['instagram']['id'] ?? null;
$ig_token   = decryptToken($accounts['instagram']['token'] ?? '');

$tw_id      = $accounts['twitter']['id'] ?? null;
$tw_token   = decryptToken($accounts['twitter']['token'] ?? '');

$li_id      = $accounts['linkedin']['organization_id'] ?? null;
$li_token   = decryptToken($accounts['linkedin']['token'] ?? '');

$yt_channel_id = $accounts['youtube']['channel_id'] ?? null;
$yt_api_key    = decryptToken($accounts['youtube']['api_key'] ?? '');

$tt_id         = trim($accounts['tiktok']['id'] ?? '');
$tt_token      = decryptToken($accounts['tiktok']['token'] ?? '');



$company_name = $projectData['company_name'] ?? $projectName;

/* ===============================
   4️⃣ Load Dashboard Logic
   ================================ */

   require_once 'social-dashboard.php';

/* ===============================
   5️⃣ Handle Request (READ JSON)
   ================================ */

   $start = $_GET['start'] ?? date('Y-m-01');
   $end   = $_GET['end'] ?? date('Y-m-t');

   $platform = $_GET['platform'] ?? '';

   $dataFile = dirname(__DIR__) . "/data/" . $filename . ".json";

   if (!file_exists($dataFile)) {
    echo json_encode(['error'=>true,'message'=>'No data generated yet']);
    exit;
}

$data = json_decode(file_get_contents($dataFile), true);
$instagramDaily = $data['instagram_daily'] ?? [];

switch ($platform) {

    case 'facebook':
    $source = $data['facebook'] ?? [];
    break;

    case 'instagram':
    $source = $data['instagram'] ?? [];
    break;

    case 'twitter':
    $source = $data['twitter'] ?? [];
    break;

    case 'linkedin':
    $source = $data['linkedin'] ?? [];
    break;

    case 'youtube':
    $source = $data['youtube'] ?? [];
    break;

    case 'tiktok':
    $source = $data['tiktok'] ?? [];
    break;

    default:
    $source = [];
}

$weekKey = $start . '|' . $end;

/* ===============================
   ✅ 1. EXACT WEEK (PRIMARY MODE)
   ================================ */
   if (isset($source[$weekKey])) {
    $result = $source[$weekKey];
}

/* ===============================
   ✅ 2. AGGREGATE (FALLBACK MODE)
   ================================ */
   else {

    $result = [
        'platform' => $platform,
        'total_posts' => 0,
        'total_views' => 0,
        'reach' => 0,
        'engagements' => 0,
        'new_followers' => 0,
        'weekly_data' => [],
        'content_breakdown' => [],
        'top_posts' => [],
        'visits' => 0,
        'page_reach' => 0,
        'error' => ''
    ];

    foreach ($source as $week => $values) {

        [$wStart, $wEnd] = explode('|', $week);

        if (
            strtotime($wEnd) >= strtotime($start) &&
            strtotime($wStart) <= strtotime($end)
        ) {

            $result['reach'] += $values['reach'] ?? 0;
            $result['engagements'] += $values['engagements'] ?? 0;
            $result['new_followers'] += $values['new_followers'] ?? 0;
            $result['visits'] += $values['visits'] ?? 0;
            $result['page_reach'] += $values['page_reach'] ?? 0;

            // optional: merge breakdown
            if (!empty($values['content_breakdown'])) {
                foreach ($values['content_breakdown'] as $type => $row) {

                    if (!isset($result['content_breakdown'][$type])) {
                        $result['content_breakdown'][$type] = [
                            'posts' => 0,
                            'engagements' => 0,
                            'views' => 0
                        ];
                    }

                    $result['content_breakdown'][$type]['posts'] += $row['posts'] ?? 0;
                    $result['content_breakdown'][$type]['engagements'] += $row['engagements'] ?? 0;
                    $result['content_breakdown'][$type]['views'] += $row['views'] ?? 0;
                }
            }

            // optional: merge top posts
            if (!empty($values['top_posts'])) {
                $result['top_posts'] = array_merge(
                    $result['top_posts'],
                    $values['top_posts']
                );
            }
        }
    }

    // sort top posts
    if (!empty($result['top_posts'])) {
        usort($result['top_posts'], fn($a,$b) => ($b['views'] ?? 0) <=> ($a['views'] ?? 0));
        $result['top_posts'] = array_slice($result['top_posts'], 0, 10);
    }
}



/* fallback empty structure */
if (empty($result)) {
    $result = [
        'platform' => $platform,
        'total_posts' => 0,
        'total_views' => 0,
        'reach' => 0,
        'engagements' => 0,
        'new_followers' => 0,
        'followers_month' => 0,
        'weekly_data' => [0],
        'content_breakdown' => [],
        'top_posts' => [],
        'visits' => 0,
        'error' => ''
    ];
}


if ($platform === 'instagram') {

    $followersTotal = 0;

    foreach ($instagramDaily as $date => $value) {

        if ($date >= $start && $date <= $end) {
            $followersTotal += (int)$value;
        }
    }

    $result['new_followers'] = $followersTotal;
}

$result['company_name'] = $data['company_name'] ?? $projectName;

if ($platform === 'instagram') {
    $result['instagram_monthly'] = $data['instagram_monthly'] ?? [];
}



echo json_encode($result);



