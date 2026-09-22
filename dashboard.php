<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

if (empty($_SESSION['projects'])) {
    die("No project assigned.");
}

$projectName = $_GET['project'] ?? $_SESSION['projects'][0];

// Security: ensure user actually owns this project
if (!in_array($projectName, $_SESSION['projects'])) {
    $projectName = $_SESSION['projects'][0];
}

// Apply SAME sanitizing rule used when creating the file
$filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $projectName);

$projectFile = __DIR__ . "/Projects/" . $filename . ".json";

if (!file_exists($projectFile)) {
    die("Project file not found: " . $projectFile);
}

$projectData = json_decode(file_get_contents($projectFile), true);

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

$fb_token = decryptToken($projectData['keys']['fb_token']);
$ig_token = decryptToken($projectData['keys']['ig_token']);
$tw_token   = decryptToken($projectData['keys']['tw_token'] ?? '');
$li_token   = decryptToken($projectData['keys']['li_token'] ?? '');
$yt_api_key = decryptToken($projectData['keys']['yt_api_key'] ?? '');
$tt_token   = decryptToken($projectData['keys']['tt_token'] ?? '');

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

// Optional: dynamically set month
$month = date('Y-m');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Social Dashboard</title>

    <!-- CSS -->
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/dashboard.css">

    <!-- Chart.js -->
    <script>
        const CURRENT_PROJECT = <?= json_encode($projectName) ?>;
        window.CURRENT_PROJECT = CURRENT_PROJECT;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div id="pdfExportContainer" style="display:none;"></div>
    <div class="nav-bar">

        <div class="logo-icon-container">
            <a href="/">
                <img src="">
            </a>
        </div>

        <a href="/"class="logo-link">
            <div class="logo-container"><img src="https://filamsoftware.com/project-estimate/images/logo-blue.svg"></div>
        </a>

        <div class="menu-con">



            <ul id="navItems" class="nav-items">
                <div class="menu-group" id="list_menu">

                    <a href="functions/logout.php"><li>Log Out</li></a>

                </div>



                <div class="social-group">
                    <a href="https://www.linkedin.com/company/filamsoftware" _blank><div class="social in"></div></a>
                    <a href="https://www.facebook.com/filamsoftware" _blank><div class="social fb"></div></a>
                    <a href="https://twitter.com/FilAmSoftware" _blank><div class="social tw"></div></a>
                </div>

            </ul>

            <div id="burger" onclick="toggleBurger()">
                <div></div>
                <div></div>
                <div></div>
            </div>


            <div id="navbar_cta" class="nav-bar-cta" onclick="toggleformbox()">Schedule A Call</div>
        </div>
    </div>

    <section class="dashboard">
        <div id="globalLoader" class="loader-overlay">
            <div class="spinner"></div>
        </div>
        <div class="container">

         <div class="header-smm">
             <h1 id="companyName">Loading...</h1>
             <div class="meta" id="reportMeta">
                SMM WEEKLY REPORT - Facebook
            </div>
        </div>




        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" data-tab="facebook">Facebook</div>
            <div class="tab" data-tab="instagram">Instagram</div>
            <div class="tab" data-tab="twitter">Twitter</div>
            <div class="tab" data-tab="linkedin">LinkedIn</div>
            <div class="tab" data-tab="youtube">Youtube</div>
            <div class="tab" data-tab="tiktok">Tiktok</div>
        </div>

        <div class="report-controls">

            <select id="pdfFormat">
                <option value="">Select Format</option>
                <option value="dashboard">Dashboard Only</option>
                <option value="insights">Insights Only</option>
                <option value="platform">Platform</option>
                <option value="all-dashboard">All Dashboard</option>
                <option value="all-insights">All Insights</option>
                <option value="all">All</option>
            </select>

            <button id="generatePdfBtn">Generate PDF</button>

        </div>

        <div class="autogen">
            <button id="autogenerateBtn" class="autogenerate-btn">
                Schedule Autoreport
            </button>
        </div>

        <?php if(count($_SESSION['projects']) > 1): ?>
            <div style="margin-bottom:20px;">
                <label>Select Project:</label>
                <select id="projectPicker">
                    <?php foreach($_SESSION['projects'] as $proj): ?>
                        <option value="<?= htmlspecialchars($proj) ?>"
                            <?= $proj === $projectName ? 'selected' : '' ?>>
                            <?= htmlspecialchars($proj) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div style="margin-bottom:20px;">
            <!-- <label>Select Week:</label> -->
            <div class="date-range-picker">

                <div>
                    <label>From:</label>
                    <input type="date" id="fromDate">
                </div>

                <div>
                    <label>To:</label>
                    <input type="date" id="toDate">
                </div>

                <button id="applyRange">Apply</button>

            </div>
        </div>


        <!-- FACEBOOK -->
        <div id="facebook" class="tab-content active">

            <div class="row">
                <div class="tab-col">
                    <h2>Facebook Performance</h2>

                    <div class="stats">
                        <div class="stat-box">
                            <h3>Reach</h3>
                            <p id="fb-reach">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Engagements</h3>
                            <p id="fb-engagements">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>New Followers</h3>
                            <p id="fb-followers">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Visits</h3>
                            <p id="fb-visits">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Page Reach</h3>
                            <p id="fb-page-reach">0</p>
                        </div>

                    </div>

                    <h2>Previous Performance</h2>
                    <div class="stats" id="fb-previous">
                        <div class="stat-box">
                            <h3>Reach</h3>
                            <p id="fb-prev-reach">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Engagements</h3>
                            <p id="fb-prev-engagements">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>New Followers</h3>
                            <p id="fb-prev-followers">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Visits</h3>
                            <p id="fb-prev-visits">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Page Reach</h3>
                            <p id="fb-prev-page-reach">0</p>
                        </div>
                    </div>


                    <h2>2026 Overtime</h2>
                    <div class="stats">
                        <div class="stat-box">
                            <h3>Reach</h3>
                            <p id="fb-year-reach">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Engagements</h3>
                            <p id="fb-year-engagements">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>New Followers</h3>
                            <p id="fb-year-followers">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Visits</h3>
                            <p id="fb-year-visits">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Page Reach</h3>
                            <p id="fb-year-page-reach">0</p>
                        </div>
                    </div>

                    <h3>Post Performance</h3>
                    <button class="insights-btn" onclick="openInsightsModal('facebook')">
                        View Insights
                    </button>
                    <table>
                        <thead>
                            <tr>
                                <th>Content Type</th>
                                <th>Posts</th>
                                <th>Engagements</th>
                                <th>Views</th>
                            </tr>
                        </thead>
                        <tbody id="fb-breakdown"></tbody>
                    </table>
                </div>


                <div class="tab-col">
                    <h2>Quarterly Overview</h2>

                    <div class="charts">
                        <div class="col"><canvas id="fbViewsChart"></canvas></div>
                        <div class="col"><canvas id="fbFollowsChart"></canvas></div>
                        <div class="col"><canvas id="fbVisitsChart"></canvas></div>
                        <div class="col"><canvas id="fbInteractionsChart"></canvas></div>
                        <div class="col"><canvas id="fbPageReachChart"></canvas></div>
                    </div>
                </div>

            </div>




        </div>

        <!-- INSTAGRAM -->
        <div id="instagram" class="tab-content">

            <div class="row">

                <div class="tab-col">
                    <h2>Instagram Performance</h2>

                    <div class="stats">
                        <div class="stat-box">
                            <h3>Reach</h3>
                            <p id="ig-reach">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Engagements</h3>
                            <p id="ig-engagements">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>New Followers</h3>
                            <p id="ig-followers">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Visits</h3>
                            <p id="ig-visits">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Page Reach</h3>
                            <p id="ig-page-reach">0</p>
                        </div>
                    </div>

                    <h2>Previous Performance</h2>
                    <div class="stats" id="ig-previous">
                        <div class="stat-box">
                            <h3>Reach</h3>
                            <p id="ig-prev-reach">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Engagements</h3>
                            <p id="ig-prev-engagements">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>New Followers</h3>
                            <p id="ig-prev-followers">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Visits</h3>
                            <p id="ig-prev-visits">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Page Reach</h3>
                            <p id="ig-prev-page-reach">0</p>
                        </div>
                    </div>

                    <h2>2026 Overtime</h2>
                    <div class="stats">
                        <div class="stat-box">
                            <h3>Reach</h3>
                            <p id="ig-year-reach">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Engagements</h3>
                            <p id="ig-year-engagements">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>New Followers</h3>
                            <p id="ig-year-followers">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Visits</h3>
                            <p id="ig-year-visits">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Page Reach</h3>
                            <p id="ig-year-page-reach">0</p>
                        </div>
                    </div>

                    <h3>Post Performance</h3>
                    <button class="insights-btn" onclick="openInsightsModal('instagram')">
                        View Insights
                    </button>
                    <table>
                        <thead>
                            <tr>
                                <th>Content Type</th>
                                <th>Posts</th>
                                <th>Engagements</th>
                                <th>Views</th>
                            </tr>
                        </thead>
                        <tbody id="ig-breakdown"></tbody>
                    </table>
                </div>

                <div class="tab-col">

                    <h2>Quarterly Overview</h2>

                    <div class="charts">
                        <div class="col"><canvas id="igViewsChart"></canvas></div>
                        <div class="col"><canvas id="igFollowsChart"></canvas></div>
                        <div class="col"> <canvas id="igVisitsChart"></canvas></div>
                        <div class="col"><canvas id="igInteractionsChart"></canvas></div>
                        <div class="col"><canvas id="igPageReachChart"></canvas></div>
                    </div>
                </div>

            </div>


        </div>



        <!-- Twitter -->
        <div id="twitter" class="tab-content">

            <div class="row">

                <div class="tab-col">
                    <h2>Twitter Performance</h2>

                    <div class="stats">
                        <div class="stat-box">
                            <h3>Reach</h3>
                            <p id="tw-reach">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Engagements</h3>
                            <p id="tw-engagements">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>New Followers</h3>
                            <p id="tw-followers">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Visits</h3>
                            <p id="tw-visits">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Page Reach</h3>
                            <p id="tw-page-reach">0</p>
                        </div>
                    </div>

                    <h2>Previous Performance</h2>
                    <div class="stats" id="tw-previous">
                        <div class="stat-box">
                            <h3>Reach</h3>
                            <p id="tw-prev-reach">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Engagements</h3>
                            <p id="tw-prev-engagements">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>New Followers</h3>
                            <p id="tw-prev-followers">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Visits</h3>
                            <p id="tw-prev-visits">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Page Reach</h3>
                            <p id="tw-prev-page-reach">0</p>
                        </div>
                    </div>

                    <h2>2026 Overtime</h2>
                    <div class="stats">
                        <div class="stat-box">
                            <h3>Reach</h3>
                            <p id="tw-year-reach">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Engagements</h3>
                            <p id="tw-year-engagements">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>New Followers</h3>
                            <p id="tw-year-followers">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Visits</h3>
                            <p id="tw-year-visits">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Page Reach</h3>
                            <p id="tw-year-page-reach">0</p>
                        </div>
                    </div>

                    <h3>Post Performance</h3>
                    <button class="insights-btn" onclick="openInsightsModal('twitter')">
                        View Insights
                    </button>
                    <table>
                        <thead>
                            <tr>
                                <th>Content Type</th>
                                <th>Posts</th>
                                <th>Engagements</th>
                                <th>Views</th>
                            </tr>
                        </thead>
                        <tbody id="tw-breakdown"></tbody>
                    </table>
                </div>

                <div class="tab-col">

                    <h2>Quarterly Overview</h2>

                    <div class="charts">
                        <div class="col"><canvas id="twViewsChart"></canvas></div>
                        <div class="col"><canvas id="twFollowsChart"></canvas></div>
                        <div class="col"> <canvas id="twVisitsChart"></canvas></div>
                        <div class="col"><canvas id="twInteractionsChart"></canvas></div>
                        <div class="col"><canvas id="twPageReachChart"></canvas></div>
                    </div>
                </div>

            </div>


        </div>

        <!-- LinkedIn -->
        <div id="linkedin" class="tab-content">

            <div class="row">

                <div class="tab-col">
                    <h2>Linkedin Performance</h2>

                    <div class="stats">
                        <div class="stat-box">
                            <h3>Reach</h3>
                            <p id="li-reach">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Engagements</h3>
                            <p id="li-engagements">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>New Followers</h3>
                            <p id="li-followers">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Visits</h3>
                            <p id="li-visits">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Page Reach</h3>
                            <p id="li-page-reach">0</p>
                        </div>
                    </div>

                    <h2>Previous Performance</h2>
                    <div class="stats" id="li-previous">
                        <div class="stat-box">
                            <h3>Reach</h3>
                            <p id="li-prev-reach">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Engagements</h3>
                            <p id="li-prev-engagements">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>New Followers</h3>
                            <p id="li-prev-followers">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Visits</h3>
                            <p id="li-prev-visits">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Page Reach</h3>
                            <p id="li-prev-page-reach">0</p>
                        </div>
                    </div>

                    <h2>2026 Overtime</h2>
                    <div class="stats">
                        <div class="stat-box">
                            <h3>Reach</h3>
                            <p id="li-year-reach">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Engagements</h3>
                            <p id="li-year-engagements">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>New Followers</h3>
                            <p id="li-year-followers">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Visits</h3>
                            <p id="li-year-visits">0</p>
                        </div>
                        <div class="stat-box">
                            <h3>Page Reach</h3>
                            <p id="li-year-page-reach">0</p>
                        </div>
                    </div>

                    <h3>Post Performance</h3>
                    <button class="insights-btn" onclick="openInsightsModal('linkedin')">
                        View Insights
                    </button>
                    <table>
                        <thead>
                            <tr>
                                <th>Content Type</th>
                                <th>Posts</th>
                                <th>Engagements</th>
                                <th>Views</th>
                            </tr>
                        </thead>
                        <tbody id="li-breakdown"></tbody>
                    </table>
                </div>

                <div class="tab-col">

                    <h2>Quarterly Overview</h2>

                    <div class="charts">
                        <div class="col"><canvas id="liViewsChart"></canvas></div>
                        <div class="col"><canvas id="liFollowsChart"></canvas></div>
                        <div class="col"> <canvas id="liVisitsChart"></canvas></div>
                        <div class="col"><canvas id="liInteractionsChart"></canvas></div>
                        <div class="col"><canvas id="liPageReachChart"></canvas></div>
                    </div>
                </div>

            </div>


        </div>


<!-- Youtube -->
<div id="youtube" class="tab-content">

    <div class="row">

        <div class="tab-col">
            <h2>Youtube Performance</h2>

            <div class="stats">
                <div class="stat-box">
                    <h3>Reach</h3>
                    <p id="yt-reach">0</p>
                </div>
                <div class="stat-box">
                    <h3>Engagements</h3>
                    <p id="yt-engagements">0</p>
                </div>
                <div class="stat-box">
                    <h3>New Followers</h3>
                    <p id="yt-followers">0</p>
                </div>
                <div class="stat-box">
                    <h3>Visits</h3>
                    <p id="yt-visits">0</p>
                </div>
                <div class="stat-box">
                    <h3>Page Reach</h3>
                    <p id="yt-page-reach">0</p>
                </div>
            </div>

            <h2>Previous Performance</h2>
            <div class="stats" id="yt-previous">
                <div class="stat-box">
                    <h3>Reach</h3>
                    <p id="yt-prev-reach">0</p>
                </div>
                <div class="stat-box">
                    <h3>Engagements</h3>
                    <p id="yt-prev-engagements">0</p>
                </div>
                <div class="stat-box">
                    <h3>New Followers</h3>
                    <p id="yt-prev-followers">0</p>
                </div>
                <div class="stat-box">
                    <h3>Visits</h3>
                    <p id="yt-prev-visits">0</p>
                </div>
                <div class="stat-box">
                    <h3>Page Reach</h3>
                    <p id="yt-prev-page-reach">0</p>
                </div>
            </div>

            <h2>2026 Overtime</h2>
            <div class="stats">
                <div class="stat-box">
                    <h3>Reach</h3>
                    <p id="yt-year-reach">0</p>
                </div>
                <div class="stat-box">
                    <h3>Engagements</h3>
                    <p id="yt-year-engagements">0</p>
                </div>
                <div class="stat-box">
                    <h3>New Followers</h3>
                    <p id="yt-year-followers">0</p>
                </div>
                <div class="stat-box">
                    <h3>Visits</h3>
                    <p id="yt-year-visits">0</p>
                </div>
                <div class="stat-box">
                    <h3>Page Reach</h3>
                    <p id="yt-year-page-reach">0</p>
                </div>
            </div>

            <h3>Post Performance</h3>
            <button class="insights-btn" onclick="openInsightsModal('youtube')">
                View Insights
            </button>
            <table>
                <thead>
                    <tr>
                        <th>Content Type</th>
                        <th>Posts</th>
                        <th>Engagements</th>
                        <th>Views</th>
                    </tr>
                </thead>
                <tbody id="yt-breakdown"></tbody>
            </table>
        </div>

        <div class="tab-col">

            <h2>Quarterly Overview</h2>

            <div class="charts">
                <div class="col"><canvas id="ytViewsChart"></canvas></div>
                <div class="col"><canvas id="ytFollowsChart"></canvas></div>
                <div class="col"> <canvas id="ytVisitsChart"></canvas></div>
                <div class="col"><canvas id="ytInteractionsChart"></canvas></div>
                <div class="col"><canvas id="ytPageReachChart"></canvas></div>
            </div>
        </div>

    </div>


</div>



<!-- TikTok -->
<div id="tiktok" class="tab-content">

    <div class="row">

        <div class="tab-col">
            <h2>Tiktok Performance</h2>

            <div class="stats">
                <div class="stat-box">
                    <h3>Reach</h3>
                    <p id="tt-reach">0</p>
                </div>
                <div class="stat-box">
                    <h3>Engagements</h3>
                    <p id="tt-engagements">0</p>
                </div>
                <div class="stat-box">
                    <h3>New Followers</h3>
                    <p id="tt-followers">0</p>
                </div>
                <div class="stat-box">
                    <h3>Visits</h3>
                    <p id="tt-visits">0</p>
                </div>
                <div class="stat-box">
                    <h3>Page Reach</h3>
                    <p id="tt-page-reach">0</p>
                </div>
            </div>

            <h2>Previous Performance</h2>
            <div class="stats" id="tt-previous">
                <div class="stat-box">
                    <h3>Reach</h3>
                    <p id="tt-prev-reach">0</p>
                </div>
                <div class="stat-box">
                    <h3>Engagements</h3>
                    <p id="tt-prev-engagements">0</p>
                </div>
                <div class="stat-box">
                    <h3>New Followers</h3>
                    <p id="tt-prev-followers">0</p>
                </div>
                <div class="stat-box">
                    <h3>Visits</h3>
                    <p id="tt-prev-visits">0</p>
                </div>
                <div class="stat-box">
                    <h3>Page Reach</h3>
                    <p id="tt-prev-page-reach">0</p>
                </div>
            </div>

            <h2>2026 Overtime</h2>
            <div class="stats">
                <div class="stat-box">
                    <h3>Reach</h3>
                    <p id="tt-year-reach">0</p>
                </div>
                <div class="stat-box">
                    <h3>Engagements</h3>
                    <p id="tt-year-engagements">0</p>
                </div>
                <div class="stat-box">
                    <h3>New Followers</h3>
                    <p id="tt-year-followers">0</p>
                </div>
                <div class="stat-box">
                    <h3>Visits</h3>
                    <p id="tt-year-visits">0</p>
                </div>
                <div class="stat-box">
                    <h3>Page Reach</h3>
                    <p id="tt-year-page-reach">0</p>
                </div>
            </div>

            <h3>Post Performance</h3>
            <button class="insights-btn" onclick="openInsightsModal('tiktok')">
                View Insights
            </button>
            <table>
                <thead>
                    <tr>
                        <th>Content Type</th>
                        <th>Posts</th>
                        <th>Engagements</th>
                        <th>Views</th>
                    </tr>
                </thead>
                <tbody id="tt-breakdown"></tbody>
            </table>
        </div>

        <div class="tab-col">

            <h2>Quarterly Overview</h2>

            <div class="charts">
                <div class="col"><canvas id="ttViewsChart"></canvas></div>
                <div class="col"><canvas id="ttFollowsChart"></canvas></div>
                <div class="col"> <canvas id="ttVisitsChart"></canvas></div>
                <div class="col"><canvas id="ttInteractionsChart"></canvas></div>
                <div class="col"><canvas id="ttPageReachChart"></canvas></div>
            </div>
        </div>

    </div>


</div>


  <!--   <h2>Platform Comparison</h2>
<canvas id="comparisonChart" height="120"></canvas>
-->
</div>
</section>
<div id="insightsModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeInsightsModal()">&times;</span>

        <div id="modalLoader" class="loader-overlay" style="display:none;">
            <div class="spinner"></div>
        </div>


        <h2 id="modalTitle"></h2>
        <div id="modalContent"></div>
    </div>
</div>

<!-- AutoReport Modal -->
<div id="autogenerateModal" class="modal">
    <div class="modal-content autoreport-modal-content">

        <span class="close" id="closeAutoreportModal">&times;</span>

        <h2>Schedule Autoreport</h2>

        <div class="autoreport-form">

            <div class="autoreport-field">
                <label for="autoreportStartDate">Start Date</label>
                <input type="date" id="autoreportStartDate">
            </div>

            <div class="autoreport-field">
                <label for="autoreportTime">Time</label>
                <input type="time" id="autoreportTime">
            </div>

            <div class="autoreport-field">
                <label for="autoreportTimezone">Timezone</label>
                <input
                    type="text"
                    id="autoreportTimezone"
                    readonly
                >
            </div>

        </div>

        <div class="autoreport-actions">
            <button id="autoreportConfirmBtn" type="button">
                Confirm
            </button>

            <button id="autoreportCancelBtn" type="button">
                Cancel Autogen
            </button>
        </div>

    </div>
</div>


<!-- Pass PHP month to JS -->
<script>
    const API_URL = "functions/api.php";
</script>

<!-- JS -->
<script src="js/pdf-export.js"></script>
<script src="js/autoreport.js"></script>
<script src="js/dashboard.js"></script>


</body>
</html>