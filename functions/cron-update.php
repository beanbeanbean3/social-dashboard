<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


ignore_user_abort(true);
set_time_limit(0);

/* ===============================
   🔐 OPTIONAL SECURITY
   ================================ */

   define('CRON_SECRET', 'a9F!k2Pz_93Lx@Qw7');

// If calling via URL, protect it
   if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['key']) || $_GET['key'] !== CRON_SECRET) {
        die('Unauthorized');
    }

    echo "<pre>";
}

/* ===============================
   📁 INIT
   ================================ */

   require_once 'social-dashboard.php';

   function ensure_data_folder() {
    $dir = dirname(__DIR__) . "/data/";
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

ensure_data_folder();

/* ===============================
   🔐 DECRYPT TOKENS
   ================================ */

   define('SECRET_KEY', '12345678901234567890123456789012');
   define('SECRET_IV',  '1234567890123456');

   function decryptToken($data) {
    return openssl_decrypt(
        base64_decode($data),
        'AES-256-CBC',
        SECRET_KEY,
        0,
        SECRET_IV
    );
}




/* ===============================
   📅 LAST 12 WEEKS
   ================================ */

   function getLast12WeeksPHP() {

    $today = new DateTime();

    // Get last Sunday
    $sunday = clone $today;
    $sunday->modify('this sunday');

    $weeks = [];

    for ($i = 0; $i < 12; $i++) {

        $end = clone $sunday;
        $start = clone $sunday;
        $start->modify('-6 days');

        $weeks[] = [
            'start' => $start->format('Y-m-d'),
            'end'   => $end->format('Y-m-d')
        ];

        // Move to previous week
        $sunday->modify('-7 days');
    }

    return array_reverse($weeks);
}




/* ===============================
   📂 LOAD ALL PROJECTS
   ================================ */

   $projectsDir = dirname(__DIR__) . "/Projects/";
   $projectFiles = glob($projectsDir . "*.json");

   foreach ($projectFiles as $projectFile) {

    echo "Processing: $projectFile\n";

    $projectData = json_decode(file_get_contents($projectFile), true);

    if (!$projectData) continue;

    $filename = basename($projectFile, ".json");

    /* ===============================
       🔑 TOKENS
       ================================= */

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
                "channel_id"=>$projectData['keys']['yt_channel_id'] ?? '',
                "api_key"=>$projectData['keys']['yt_api_key'] ?? ''
            ],
            "tiktok"=>[
                "id"=>$projectData['keys']['tt_id'] ?? '',
                "token"=>$projectData['keys']['tt_token'] ?? ''
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
    $yt_api_key = decryptToken($accounts['youtube']['api_key'] ?? '');

    $tt_id = trim($accounts['tiktok']['id'] ?? '');
    $tt_token = decryptToken($accounts['tiktok']['token'] ?? '');

    $company_name = $projectData['company_name'] ?? $filename;

    /* ===============================
       📄 DATA FILE
       ================================= */

       $dataFile = dirname(__DIR__) . "/data/" . $filename . ".json";

       $existing = file_exists($dataFile)
       ? json_decode(file_get_contents($dataFile), true)
       : [];

       $existing['company_name'] = $company_name;
       $existing['facebook'] = $existing['facebook'] ?? [];
       $existing['instagram'] = $existing['instagram'] ?? [];
       $existing['youtube'] = $existing['youtube'] ?? [];
       $existing['tiktok']  = $existing['tiktok'] ?? [];


       $existing['instagram_monthly'] = $existing['instagram_monthly'] ?? [];
       

       $currentYear  = date('Y');
       $currentMonth = date('n');

       for ($m = 1; $m <= $currentMonth; $m++) {

        $monthKey = $currentYear . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);

    // ✅ skip if already exists (avoid extra API calls)
        if (isset($existing['instagram_monthly'][$monthKey])) {
            continue;
        }

        $existing['instagram_monthly'][$monthKey] =
        get_instagram_monthly_followers($currentYear, $m);

    usleep(200000); // small delay (optional but safe)
}



    /* ===============================
       🔁 FETCH ONLY MISSING WEEKS
       ================================= */

       $weeks = getLast12WeeksPHP();

       foreach ($weeks as $week) {

        $start = $week['start'];
        $end   = $week['end'];



        $weekKey = $start . '|' . $end;

        // Always refresh last 2 weeks
        $twoWeeksAgo = strtotime('-14 days');

        if (
            isset($existing['facebook'][$weekKey]) &&
            isset($existing['instagram'][$weekKey]) &&
            isset($existing['twitter'][$weekKey]) &&
            isset($existing['linkedin'][$weekKey]) &&
            isset($existing['youtube'][$weekKey]) &&
            isset($existing['tiktok'][$weekKey]) &&
            strtotime($end) < $twoWeeksAgo
        ) {
            continue;
        }

        echo "Fetching $filename → $weekKey\n";

        if ($fb_page_id && $fb_token) {
            $fb = get_facebook_engagement($start, $end);
            $existing['facebook'][$weekKey] = $fb;
        }

// ============================
// ✅ INSTAGRAM BLOCK (FIXED)
// ============================
        if ($ig_id && $ig_token) {

    // ✅ 1. FETCH DAILY FOLLOWERS FIRST
            $dailyFollowers = call_graph_api(
                "https://graph.facebook.com/v24.0/$ig_id/insights?metric=follower_count&period=day&since=$start&until=$end&access_token=$ig_token"
            );

            if (!empty($dailyFollowers['data'][0]['values'])) {

                if (!isset($existing['instagram_daily'])) {
                    $existing['instagram_daily'] = [];
                }

                if (!isset($existing['instagram_reach_daily'])) {
                    $existing['instagram_reach_daily'] = [];
                }

                foreach ($dailyFollowers['data'][0]['values'] as $day) {

                    $date = substr($day['end_time'], 0, 10);
                    $value = (int)($day['value'] ?? 0);

            // ✅ THIS WAS MISSING (very important)
                    $existing['instagram_daily'][$date] = $value;
                }
            }

            $dailyReach = call_graph_api(
                "https://graph.facebook.com/v24.0/$ig_id/insights?metric=reach&period=day&since=$start&until=$end&access_token=$ig_token"
            );

            if (!empty($dailyReach['data'][0]['values'])) {

                foreach ($dailyReach['data'][0]['values'] as $day) {

                    $date = substr($day['end_time'], 0, 10);
                    $value = (int)($day['value'] ?? 0);

                    $existing['instagram_reach_daily'][$date] = $value;
                }
            }

    // ✅ 2. NOW compute weekly using saved daily data
            $ig = get_instagram_engagement(
                $start,
                $end,
                $existing['instagram_daily'] ?? [],
                $existing['instagram_reach_daily'] ?? []
            );
            $existing['instagram'][$weekKey] = $ig;
        }


    if ($tw_id && $tw_token) {
        $existing['twitter'][$weekKey] =
        get_twitter_engagement($start,$end);
    }

    if ($li_id && $li_token) {
        $existing['linkedin'][$weekKey] =
        get_linkedin_engagement($start,$end);
    }


    if ($yt_channel_id && $yt_api_key) {
        $existing['youtube'][$weekKey] =
        get_youtube_engagement($start, $end);
    }

    if ($tt_id && $tt_token) {
        $existing['tiktok'][$weekKey] =
        get_tiktok_engagement($start, $end);
    }


        usleep(300000);
    }


    /* ===============================
       🧹 KEEP ONLY LAST 12 WEEKS
       ================================= */

       $existing['facebook'] = array_slice($existing['facebook'], -12, 12, true);
       $existing['instagram'] = array_slice($existing['instagram'], -12, 12, true);
       $existing['twitter'] = $existing['twitter'] ?? [];
       $existing['linkedin'] = $existing['linkedin'] ?? [];

       $existing['youtube'] =
       array_slice($existing['youtube'], -12, 12, true);

       $existing['tiktok'] =
       array_slice($existing['tiktok'], -12, 12, true);

    /* ===============================
       💾 SAFE WRITE
       ================================= */

       $tmpFile = $dataFile . '.tmp';

       file_put_contents($tmpFile, json_encode($existing), LOCK_EX);
       rename($tmpFile, $dataFile);
   }




/* ===============================
   🧹 CLEAR CACHE (2 HOURS)
   ================================ */

   $cacheDir = dirname(__DIR__) . "/cache/";

   foreach (glob($cacheDir . "*.json") as $file) {
    if (time() - filemtime($file) > 7200) {
        unlink($file);
    }
}


echo "Cron completed.\n";