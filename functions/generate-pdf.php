<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit("Unauthorized");
}

$data = json_decode(file_get_contents("php://input"), true);

require_once __DIR__ . '/../dompdf/autoload.inc.php';

use Dompdf\Dompdf;

$dompdf = new Dompdf();

$project = $data['project'];
$format = $data['format'];
$platforms = $data['platforms'] ?? [];

$company = $data['company'];
$dateFrom = $data['dateRange']['from'];
$dateTo = $data['dateRange']['to'];

// $charts = $data['charts'];
// $dashboard = $data['dashboard'];
// $insightsHTML = $data['insightsHTML'] ?? '';

function pageBreak() {
    return '<div style="page-break-after: always;"></div>';
}

function getWeeklyDifference($current, $previous) {
    if (!$previous) return '';

    $diff = (($current - $previous) / $previous) * 100;
    $isUp = $diff >= 0;

    $arrow = $isUp ? "▲" : "▼";
    $color = $isUp ? "green" : "red";

    return "
    <div style='color:$color; font-size:10px; font-weight:bold; margin-top:4px; font-family:DejaVu Sans, sans-serif'>
    $arrow " . number_format(abs($diff), 1) . "%
    </div>
    ";
}

$html = '


<style>

@font-face {
    font-family: "Proxima Nova";
    src: url("../fonts/Proxima_Nova_Regular.ttf") format("truetype");
}

@page {
    margin: 0;
}

body{
    font-family: "Proxima Nova", sans-serif;
    color:#222;
    margin:0;
    padding:0;
}

.page{
    width:100%;
    padding:0;
    margin:0;
    height:100%;
    overflow:hidden;
}


img{
    max-width:100%;
}

table{
    page-break-inside:auto;
}

tr{
    page-break-inside:avoid;
}



/* HEADER */

.report-header{
    background:#f7931a;
    color:#000;
    text-align:center;
    padding: 10px 5px;
}

.report-header h1{
    margin:0;
    font-size:11px;
    font-weight:normal;
    text-transform: uppercase;
}

.report-header h2{
    margin:5px 0 0;
    font-size:20px;
    font-weight:normal;
}

/* DATE */

.date-bar{
    background: #f7931a;
    color: #000;
    padding: 8px 16px;
    margin: 12px 20px;
    border-radius: 4px;
    font-size: 15px;
    font-weight: normal;
    width: 44%;
}


/* MAIN GRID */

.dashboard-grid{
    width:100%;
    border-collapse:collapse;
}

.dashboard-grid td{
    vertical-align:top;
}



.insights-wrapper{
    width:100%;
    font-size:0;
    white-space:nowrap;
}

.insights-left{
    display:inline-block;
    vertical-align:top;
    width:47%;
    margin-right:1%;
    padding:0 8px 0 16px;
    box-sizing:border-box;
    font-size:12px;
    white-space:normal;
}

.insights-right{
    display:inline-block;
    vertical-align:top;
    width:47%;
    padding:0 16px 0 8px;
    box-sizing:border-box;
    font-size:12px;
    white-space:normal;
}

.chart-box-insight{
    border:1px solid #999;
    border-radius:6px;
    padding:6px;
    margin-bottom:10px;
    page-break-inside:avoid;
}

.top-content-card{
    page-break-inside:avoid;
}



/* LEFT COLUMN */

.left-column{
    width:48%;
    padding:0 8px 0 16px;
}

/* RIGHT COLUMN */

.right-column{
    width:52%;
    padding:0 16px 0 8px;
}

/* SECTION TITLE */

.section-title{
    text-align: center;
    font-size: 8px;
    font-weight: normal;
    margin: 5px 0 8px;
}

/* KPI GRID */

.stats-grid{
    width:100%;
    border-collapse:separate;
    border-spacing:6px;

}

.stat-box{
    border:1px solid #999;
    border-radius:8px;
    text-align:center;
    padding:10px 6px;
    height:65px;
    width:50%;
}

.stat-label{
    font-size:9px;
    color:#666;
    margin-bottom:10px;
}

.stat-value{
    font-size: 20px;
    font-weight: normal;
    line-height: 1;
}

.stat-change{
    margin-top:4px;
    font-size:9px;
    display:block;
    font-weight:bold;
}


/* SMALL STATS */

.small-grid{
    width:100%;
    border-collapse:separate;
    border-spacing:6px;
}

.small-box{
    border:1px solid #999;
    border-radius:6px;
    text-align:center;
    padding:8px 4px;
    height:42px;
}

.small-label{
    font-size:8px;
    color:#777;
}

.small-value{
    font-size:9px;
    margin-top:4px;
    font-weight:bold;
}

/* YEAR BOXES */

.year-highlight{
    background:#f7931a;
    color:#fff;
}

/* CHARTS */

.chart-grid{
    width:100%;
    border-collapse:separate;
    border-spacing:12px;
}

.chart-box{
    border:1px solid #999;
    border-radius:6px;
    padding:6px;
    margin-bottom: 10px;
    height:50px;
}

.chart-box-insight{
    border:1px solid #999;
    border-radius:6px;
    padding:6px;
    margin-bottom: 10px;
}

.chart-title{
    font-size:7px;
    font-weight:bold;
    margin-bottom:10px;
}

.chart-img{
    width:100%;
    height:100px;
    object-fit:contain;
}

.chart-img-insight{
    width:100%;
    height:auto;
    object-fit:contain;
}

/* PAGE BREAK */

.page-break{
    page-break-after:always;
}

</style>

';

/* =========================
   DASHBOARD PAGE
   ========================= */

   if (
    $format === "dashboard" ||
    $format === "platform" ||
    $format === "all" ||
    $format === "all-dashboard"
) {

    $platformCount = count($platforms);
    $currentIndex = 0;
    
    foreach ($platforms as $platformName => $platformData) {

        $currentIndex++;

        $dashboard = $platformData['dashboard'] ?? [];
        $charts = $platformData['charts'] ?? [];

        $current = $dashboard['current'] ?? [];
        $previous = $dashboard['previous'] ?? [];
        $overtime = $dashboard['overtime'] ?? [];

        $reach = $current['reach'] ?? 0;
        $engagements = $current['engagements'] ?? 0;
        $followers = $current['followers'] ?? 0;
        $visits = $current['visits'] ?? 0;
        $pageReach = $current['pageReach'] ?? 0;

        $platformLabel = strtoupper($platformName);

        $html .= '

        <div class="page">

        <div class="report-header">
        <h1>' . $company . '</h1>
        <h2>SMM WEEKLY REPORT - ' . $platformLabel . '</h2>
        </div>

        <div class="date-bar">
        ' . date("M j, Y", strtotime($dateFrom)) . ' - ' . date("M j, Y", strtotime($dateTo)) . '
        </div>

        <div class="dashboard-con">
        <table class="dashboard-grid">

        <tr>

        <!-- LEFT -->

        <td class="left-column">

        <div class="section-title">
        REPORT PERIOD VS PREVIOUS PERIOD
        </div>

        <table class="stats-grid">

        <tr>

        <td class="stat-box">
        <div class="stat-label">Views</div>
        <div class="stat-value">' . $reach . '
        ' . getWeeklyDifference($reach, $previous['reach'] ?? 0) . '
        </div>
        </td>

        <td class="stat-box">
        <div class="stat-label">Follows</div>
        <div class="stat-value">' . $followers . ' 
        ' . getWeeklyDifference($followers, $previous['followers'] ?? 0) . '
        </div>
        </td>

        </tr>

        <tr>

        <td class="stat-box">
        <div class="stat-label">Visits</div>
        <div class="stat-value">' . $visits . '
        ' . getWeeklyDifference($visits, $previous['visits'] ?? 0) . '
        </div>
        </td>

        <td class="stat-box">
        <div class="stat-label">Interactions</div>
        <div class="stat-value">' . $engagements . '
        ' . getWeeklyDifference($engagements, $previous['engagements'] ?? 0) . '
        </div>
        </td>

        </tr>

        <tr>

        <td class="stat-box" colspan="2">
        <div class="stat-label">Page Reach</div>
        <div class="stat-value">' . $pageReach . '
        ' . getWeeklyDifference($pageReach, $previous['pageReach'] ?? 0) . '
        </div>
        </td>

        </tr>

        </table>

        <!-- PREVIOUS -->

        <div class="section-title">
        PREVIOUS PERIOD
        </div>

        <table class="small-grid">

        <tr>

        <td class="small-box">
        <div class="small-label">Views</div>
        <div class="small-value">' . ($previous['reach'] ?? '—') . '</div>
        </td>

        <td class="small-box">
        <div class="small-label">Follows</div>
        <div class="small-value">' . ($previous['followers'] ?? '—') . '</div>
        </td>

        <td class="small-box">
        <div class="small-label">Visits</div>
        <div class="small-value">' . ($previous['visits'] ?? '—') . '</div>
        </td>

        <td class="small-box">
        <div class="small-label">Interactions</div>
        <div class="small-value">' . ($previous['engagements'] ?? '—') . '</div>
        </td>

        <td class="small-box">
        <div class="small-label">Page Reach</div>
        <div class="small-value">' . ($previous['pageReach'] ?? '—') . '</div>
        </td>

        </tr>

        </table>

        <!-- YEAR -->

        <div class="section-title">
        2026 OVER TIME
        </div>

        <table class="small-grid">

        <tr>

        <td class="small-box year-highlight">
        <div class="small-label">Views</div>
        <div class="small-value">' . ($overtime['reach'] ?? 0) . '</div>
        </td>

        <td class="small-box">
        <div class="small-label">Follows</div>
        <div class="small-value">' . ($overtime['followers'] ?? 0) . '</div>
        </td>

        <td class="small-box">
        <div class="small-label">Visits</div>
        <div class="small-value">' . ($overtime['visits'] ?? 0) . '</div>
        </td>

        <td class="small-box">
        <div class="small-label">Interactions</div>
        <div class="small-value">' . ($overtime['engagements'] ?? 0) . '</div>
        </td>

        <td class="small-box">
        <div class="small-label">Page Reach</div>
        <div class="small-value">' . ($overtime['pageReach'] ?? 0) . '</div>
        </td>

        </tr>

        </table>

        </td>

        <!-- RIGHT -->

        <td class="right-column">

        <table class="chart-grid">

        <tr>

        <td class="chart-box">

        <div class="chart-title">
        VIEWS
        </div>


        <img class="chart-img"
        src="' . ($charts[array_key_first($charts)] ?? '') . '">

        </td>

        <td class="chart-box">

        <div class="chart-title">
        FOLLOWS
        </div>

        <img class="chart-img"
        src="' . ($charts[array_keys($charts)[1]] ?? '') . '">

        </td>

        </tr>

        <tr>

        <td class="chart-box">

        <div class="chart-title">
        VISITS
        </div>

        <img class="chart-img"
        src="' . ($charts[array_keys($charts)[2]] ?? '') . '">

        </td>

        <td class="chart-box">

        <div class="chart-title">
        INTERACTIONS
        </div>

        <img class="chart-img"
        src="' . ($charts[array_keys($charts)[3]] ?? '') . '">

        </td>

        </tr>

        <tr>

        <td class="chart-box" colspan="2">

        <div class="chart-title">
        PAGE REACH
        </div>

        <img class="chart-img"
        src="' . ($charts[array_keys($charts)[4]] ?? '') . '" style="height:230px;">

        </td>

        </tr>

        </table>

        </td>

        </tr>

        </table>

        </div>

        </div>

        ';

        if ($currentIndex < $platformCount) {
            $html .= '<div class="page-break"></div>';
        }
    }
}
/* =========================
   INSIGHTS PAGE
   ========================= */

   if (
    $format === "insights" ||
    $format === "platform" ||
    $format === "all" ||
    $format === "all-insights"
) {

    $platformCount = count($platforms);
    $currentIndex = 0;

    foreach ($platforms as $platformName => $platformData) {

        $currentIndex++;

        $insightsPages =
        $platformData['insightsHTML'] ?? [];

        $platformLabel =
        strtoupper($platformName);

        $tabs = [
            'all' => 'ALL',
            'posts' => 'POSTS',
            'stories' => 'STORIES',
            'reels' => 'REELS'
        ];

        $tabIndex = 0;
        $tabCount = count($tabs);

        foreach ($tabs as $key => $label) {

            $tabIndex++;

            $insightsHTML =
            $insightsPages[$key] ?? '';

            $html .= '

            <div class="page">

            <div class="report-header">
            <h2>' . $platformLabel . ' - ' . $label . ' INSIGHTS REPORT</h2>
            </div>

            <div class="date-bar">
            ' . date("M j, Y", strtotime($dateFrom)) . ' - ' . date("M j, Y", strtotime($dateTo)) . '
            </div>
            ';

            if (!empty($insightsHTML)) {

                $html .= $insightsHTML;

            } else {

                $html .= '
                <p style="
                padding:20px;
                text-align:center;
                ">
                No insights available.
                </p>';

            }

            $html .= '</div>';

            /*
             * Add page break except last page
             */

            $isLastPlatform =
            $currentIndex === $platformCount;

            $isLastTab =
            $tabIndex === $tabCount;

            if (!($isLastPlatform && $isLastTab)) {

                $html .= '
                <div class="page-break"></div>';

            }
        }
    }
}
/* =========================
   OUTPUT PDF
   ========================= */

   $dompdf->loadHtml($html);
   $dompdf->setPaper("legal", "landscape");
   $dompdf->render();

   $pdfOutput = $dompdf->output();

/* =========================
   BACKUP SAVE
   ========================= */

if (
    $format === "all" ||
    $format === "all-dashboard" ||
    $format === "all-insights"
) {

    $backupName = "{$project}-{$format}.pdf";

} else {

    $platformKeys = implode(
        "-",
        array_keys($platforms)
    );

    $backupName =
    "{$project}-{$platformKeys}-{$format}.pdf";

}

file_put_contents(
    __DIR__ . "/../backups/" . $backupName,
    $pdfOutput
);
/* =========================
   DOWNLOAD
   ========================= */

   header("Content-Type: application/pdf");
   header("Content-Disposition: attachment; filename={$backupName}");

   echo $pdfOutput;
   exit;