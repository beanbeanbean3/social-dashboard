<?php

/**
 * ============================================================
 * AUTO REPORT PDF GENERATOR
 * ============================================================
 *
 * This file is separate from generate-pdf.php.
 *
 * It is designed for scheduled/server-side reports where
 * there is no browser, Chart.js, or DOM available.
 *
 * It:
 * 1. Loads a project
 * 2. Loads its social account credentials
 * 3. Uses social-dashboard.php functions
 * 4. Generates an "All" report
 * 5. Saves it to Reports/<project>/
 *
 * It returns:
 *
 * [
 *     'success' => true,
 *     'file' => '/full/path/to/report.pdf',
 *     'filename' => '...',
 *     'project' => '...',
 *     'date_from' => '...',
 *     'date_to' => '...'
 * ]
 */


/* ============================================================
   DOMPDF
============================================================ */

require_once __DIR__ . '/../dompdf/autoload.inc.php';

use Dompdf\Dompdf;


/* ============================================================
   SOCIAL DASHBOARD
============================================================ */

require_once __DIR__ . '/social-dashboard.php';


/* ============================================================
   ENCRYPTION
============================================================ */

if (!defined('SECRET_KEY')) {
    define(
        'SECRET_KEY',
        '12345678901234567890123456789012'
    );
}

if (!defined('SECRET_IV')) {
    define(
        'SECRET_IV',
        '1234567890123456'
    );
}


/**
 * Decrypt project token.
 */
if (!function_exists('autoreportDecryptToken')) {

    function autoreportDecryptToken($data)
    {
        if (empty($data)) {
            return '';
        }

        return openssl_decrypt(
            base64_decode($data),
            'AES-256-CBC',
            SECRET_KEY,
            0,
            SECRET_IV
        );
    }
}


/* ============================================================
   PROJECT LOADER
============================================================ */

function autoreportLoadProject($project)
{
    $filename = preg_replace(
        '/[^A-Za-z0-9_\-]/',
        '_',
        $project
    );

    $projectFile =
        __DIR__ .
        '/../Projects/' .
        $filename .
        '.json';

    if (!file_exists($projectFile)) {

        throw new Exception(
            'Project file not found: ' . $projectFile
        );
    }

    $projectData = json_decode(
        file_get_contents($projectFile),
        true
    );

    if (!is_array($projectData)) {

        throw new Exception(
            'Invalid project JSON.'
        );
    }

    return $projectData;
}


/* ============================================================
   ACCOUNT SETUP
============================================================ */

function autoreportSetupAccounts($projectData)
{
    global
        $fb_page_id,
        $fb_token,
        $ig_id,
        $ig_token,
        $tw_id,
        $tw_token,
        $li_id,
        $li_token,
        $yt_channel_id,
        $yt_api_key,
        $tt_id,
        $tt_token;

    /*
     * New account structure
     */
    $accounts =
        $projectData['accounts'] ?? [];


    /*
     * Backward-compatible account structure
     */
    if (empty($accounts)) {

        $keys =
            $projectData['keys'] ?? [];

        $accounts = [

            'facebook' => [
                'page_id' =>
                    $keys['fb_page_id'] ?? '',
                'token' =>
                    $keys['fb_token'] ?? ''
            ],

            'instagram' => [
                'id' =>
                    $keys['ig_id'] ?? '',
                'token' =>
                    $keys['ig_token'] ?? ''
            ],

            'twitter' => [
                'id' =>
                    $keys['tw_id'] ?? '',
                'token' =>
                    $keys['tw_token'] ?? ''
            ],

            'linkedin' => [
                'organization_id' =>
                    $keys['li_id'] ?? '',
                'token' =>
                    $keys['li_token'] ?? ''
            ],

            'youtube' => [
                'channel_id' =>
                    $keys['yt_channel_id'] ?? '',
                'api_key' =>
                    $keys['yt_api_key'] ?? ''
            ],

            'tiktok' => [
                'id' =>
                    $keys['tt_id'] ?? '',
                'token' =>
                    $keys['tt_token'] ?? ''
            ]

        ];
    }


    /*
     * Facebook
     */
    $fb_page_id =
        $accounts['facebook']['page_id'] ?? '';

    $fb_token =
        autoreportDecryptToken(
            $accounts['facebook']['token'] ?? ''
        );


    /*
     * Instagram
     */
    $ig_id =
        $accounts['instagram']['id'] ?? '';

    $ig_token =
        autoreportDecryptToken(
            $accounts['instagram']['token'] ?? ''
        );


    /*
     * X / Twitter
     */
    $tw_id =
        $accounts['twitter']['id'] ?? '';

    $tw_token =
        autoreportDecryptToken(
            $accounts['twitter']['token'] ?? ''
        );


    /*
     * LinkedIn
     */
    $li_id =
        $accounts['linkedin']['organization_id'] ?? '';

    $li_token =
        autoreportDecryptToken(
            $accounts['linkedin']['token'] ?? ''
        );


    /*
     * YouTube
     */
    $yt_channel_id =
        $accounts['youtube']['channel_id'] ?? '';

    $yt_api_key =
        autoreportDecryptToken(
            $accounts['youtube']['api_key'] ?? ''
        );


    /*
     * TikTok
     */
    $tt_id =
        trim(
            $accounts['tiktok']['id'] ?? ''
        );

    $tt_token =
        autoreportDecryptToken(
            $accounts['tiktok']['token'] ?? ''
        );
}


/* ============================================================
   DATE HELPERS
============================================================ */

function autoreportPreviousRange(
    $dateFrom,
    $dateTo
) {

    $start =
        new DateTime($dateFrom);

    $end =
        new DateTime($dateTo);

    $days =
        $start->diff($end)->days + 1;


    $previousEnd =
        clone $start;

    $previousEnd->modify('-1 day');


    $previousStart =
        clone $previousEnd;

    $previousStart->modify(
        '-' . ($days - 1) . ' days'
    );


    return [
        'from' =>
            $previousStart->format('Y-m-d'),

        'to' =>
            $previousEnd->format('Y-m-d')
    ];
}


/* ============================================================
   PLATFORM DATA
============================================================ */

function autoreportGetPlatformData(
    $platform,
    $dateFrom,
    $dateTo
) {

    switch ($platform) {

        case 'facebook':

            return get_facebook_engagement(
                $dateFrom,
                $dateTo
            );


        case 'instagram':

            /*
             * Existing Instagram function expects
             * optional daily follower/reach arrays.
             */
            return get_instagram_engagement(
                $dateFrom,
                $dateTo,
                [],
                []
            );


        case 'twitter':

            return get_twitter_engagement(
                $dateFrom,
                $dateTo
            );


        case 'linkedin':

            return get_linkedin_engagement(
                $dateFrom,
                $dateTo
            );


        case 'youtube':

            return get_youtube_engagement(
                $dateFrom,
                $dateTo
            );


        case 'tiktok':

            return get_tiktok_engagement(
                $dateFrom,
                $dateTo
            );


        default:

            return [
                'platform' =>
                    $platform,

                'error' =>
                    'Unsupported platform'
            ];
    }
}


/* ============================================================
   NORMALIZE DASHBOARD DATA
============================================================ */

function autoreportMetricData($data)
{
    return [

        'reach' =>
            (int)($data['reach'] ?? 0),

        'engagements' =>
            (int)($data['engagements'] ?? 0),

        'followers' =>
            (int)($data['new_followers'] ?? 0),

        'visits' =>
            (int)($data['visits'] ?? 0),

        'pageReach' =>
            (int)($data['page_reach'] ?? 0),

        'posts' =>
            (int)($data['total_posts'] ?? 0),

        'views' =>
            (int)($data['total_views'] ?? 0)
    ];
}


/* ============================================================
   PERCENTAGE DIFFERENCE
============================================================ */

function autoreportDifference(
    $current,
    $previous
) {

    $current = (float)$current;
    $previous = (float)$previous;

    if ($previous == 0) {
        return null;
    }

    return (($current - $previous) / $previous) * 100;
}

/* ============================================================
   ESCAPE HTML
============================================================ */

function autoreportEsc($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* ============================================================
   SIMPLE BAR
============================================================ */

function autoreportBar(
    $current,
    $previous
) {

    $max =
        max(
            abs((float)$current),
            abs((float)$previous),
            1
        );

    $currentWidth =
        min(
            100,
            ($current / $max) * 100
        );

    $previousWidth =
        min(
            100,
            ($previous / $max) * 100
        );

    return '

        <div style="
            margin-top:8px;
        ">

            <div style="
                height:10px;
                background:#f7931a;
                width:' .
                max(0, $currentWidth) .
                '%;
                border-radius:4px;
            "></div>

            <div style="
                height:5px;
                background:#cccccc;
                width:' .
                max(0, $previousWidth) .
                '%;
                border-radius:4px;
                margin-top:3px;
            "></div>

        </div>
    ';
}


/* ============================================================
   TOP POSTS HTML
============================================================ */

function autoreportTopPosts($data)
{
    $posts =
        $data['top_posts'] ?? [];

    if (empty($posts)) {

        return '
            <p style="
                text-align:center;
                color:#777;
            ">
                No top content available.
            </p>
        ';
    }

    $html = '
        <table style="
            width:100%;
            border-collapse:collapse;
            font-size:10px;
        ">

            <tr style="
                background:#f7931a;
            ">

                <th style="padding:6px;">
                    Content
                </th>

                <th style="padding:6px;">
                    Views
                </th>

                <th style="padding:6px;">
                    Engagements
                </th>

            </tr>
    ';


    foreach ($posts as $post) {

        $message =
            $post['message']
            ?? '';

        $message =
            mb_substr(
                $message,
                0,
                80
            );

        $views =
            (int)(
                $post['views'] ?? 0
            );

        $engagements =
            (int)(
                $post['engagements'] ?? 0
            );

        $html .= '

            <tr>

                <td style="
                    padding:6px;
                    border-bottom:1px solid #ddd;
                ">
                    ' .
                    autoreportEsc($message)
                    . '
                </td>

                <td style="
                    padding:6px;
                    border-bottom:1px solid #ddd;
                    text-align:center;
                ">
                    ' .
                    number_format($views)
                    . '
                </td>

                <td style="
                    padding:6px;
                    border-bottom:1px solid #ddd;
                    text-align:center;
                ">
                    ' .
                    number_format($engagements)
                    . '
                </td>

            </tr>
        ';
    }


    $html .= '</table>';

    return $html;
}


/* ============================================================
   CONTENT BREAKDOWN HTML
============================================================ */

function autoreportContentBreakdown($data)
{
    $breakdown =
        $data['content_breakdown']
        ?? [];

    if (empty($breakdown)) {

        return '
            <p style="
                text-align:center;
                color:#777;
            ">
                No content breakdown available.
            </p>
        ';
    }


    $html = '
        <table style="
            width:100%;
            border-collapse:collapse;
            font-size:10px;
        ">

            <tr style="
                background:#f7931a;
            ">

                <th style="padding:6px;">
                    Type
                </th>

                <th style="padding:6px;">
                    Posts
                </th>

                <th style="padding:6px;">
                    Views
                </th>

                <th style="padding:6px;">
                    Engagements
                </th>

            </tr>
    ';


    foreach ($breakdown as $type => $item) {

        $html .= '

            <tr>

                <td style="
                    padding:6px;
                    border-bottom:1px solid #ddd;
                ">
                    ' .
                    autoreportEsc($type)
                    . '
                </td>

                <td style="
                    padding:6px;
                    text-align:center;
                    border-bottom:1px solid #ddd;
                ">
                    ' .
                    number_format(
                        (int)(
                            $item['posts'] ?? 0
                        )
                    )
                    . '
                </td>

                <td style="
                    padding:6px;
                    text-align:center;
                    border-bottom:1px solid #ddd;
                ">
                    ' .
                    number_format(
                        (int)(
                            $item['views'] ?? 0
                        )
                    )
                    . '
                </td>

                <td style="
                    padding:6px;
                    text-align:center;
                    border-bottom:1px solid #ddd;
                ">
                    ' .
                    number_format(
                        (int)(
                            $item['engagements'] ?? 0
                        )
                    )
                    . '
                </td>

            </tr>
        ';
    }


    $html .= '</table>';

    return $html;
}


/* ============================================================
   GENERATE PDF
============================================================ */

function generateAutoReportPdf(
    $project,
    $dateFrom,
    $dateTo
) {

    global $existing;

    /*
     * Validate dates
     */
    if (
        empty($dateFrom) ||
        empty($dateTo)
    ) {

        throw new Exception(
            'Report dates are required.'
        );
    }


    if ($dateFrom > $dateTo) {

        throw new Exception(
            'Report start date cannot be after end date.'
        );
    }


    /*
     * Load project
     */
    $projectData =
        autoreportLoadProject($project);


    /*
     * Set credentials
     */
    autoreportSetupAccounts(
        $projectData
    );


    /*
     * Company name
     */
    $company =
        $projectData['company_name']
        ?? $project;


    /*
     * Load existing stored data.
     *
     * Some platform functions use this.
     */
    $filename =
        preg_replace(
            '/[^A-Za-z0-9_\-]/',
            '_',
            $project
        );


    $dataFile =
        __DIR__ .
        '/../data/' .
        $filename .
        '.json';


    $existing = [];

    if (file_exists($dataFile)) {

        $existing =
            json_decode(
                file_get_contents($dataFile),
                true
            ) ?? [];
    }


    /*
     * Previous period
     */
    $previousRange =
        autoreportPreviousRange(
            $dateFrom,
            $dateTo
        );


    /*
     * Platforms
     */
    $platforms = [

        'facebook',
        'instagram',
        'twitter',
        'linkedin',
        'tiktok',
        'youtube'

    ];


    $reportData = [];


    foreach ($platforms as $platform) {

        try {

            $current =
                autoreportGetPlatformData(
                    $platform,
                    $dateFrom,
                    $dateTo
                );


            $previous =
                autoreportGetPlatformData(
                    $platform,
                    $previousRange['from'],
                    $previousRange['to']
                );


            /*
             * If platform has no credentials or
             * API support, keep it as a report
             * section instead of killing the
             * entire report.
             */
            $reportData[$platform] = [

                'current' =>
                    $current,

                'previous' =>
                    $previous

            ];

        } catch (Throwable $e) {

            $reportData[$platform] = [

                'current' => [

                    'platform' =>
                        $platform,

                    'error' =>
                        $e->getMessage()

                ],

                'previous' => []

            ];
        }
    }


    /* ========================================================
       DOMPDF
    ======================================================== */

    $dompdf =
        new Dompdf();


    /* ========================================================
       CSS
    ======================================================== */

    $html = '

    <style>

        @font-face {
            font-family: "Proxima Nova";
            src:
                url("../fonts/Proxima_Nova_Regular.ttf")
                format("truetype");
        }

        @page {
            margin: 0;
        }

        body {
            font-family:
                "Proxima Nova",
                DejaVu Sans,
                sans-serif;

            color:#222;
            margin:0;
            padding:0;
        }

        .page {
            width:100%;
            padding:20px;
            box-sizing:border-box;
            page-break-after:always;
        }

        .last-page {
            page-break-after:auto;
        }

        .header {
            background:#f7931a;
            color:#000;
            text-align:center;
            padding:12px;
        }

        .header h1 {
            margin:0;
            font-size:12px;
            font-weight:normal;
        }

        .header h2 {
            margin:5px 0 0;
            font-size:20px;
            font-weight:normal;
        }

        .date {
            background:#f7931a;
            padding:8px 12px;
            margin:12px 0;
            width:45%;
            border-radius:4px;
            font-size:12px;
        }

        .metric-table {
            width:100%;
            border-collapse:separate;
            border-spacing:6px;
        }

        .metric {
            border:1px solid #999;
            border-radius:8px;
            text-align:center;
            padding:10px;
            height:60px;
        }

        .label {
            font-size:10px;
            color:#666;
        }

        .value {
            font-size:20px;
            margin-top:8px;
        }

        .change {
            font-size:9px;
            font-weight:bold;
            margin-top:4px;
        }

        .section {
            margin-top:18px;
        }

        .section-title {
            font-size:13px;
            font-weight:bold;
            margin-bottom:8px;
        }

        .two-column {
            width:100%;
            border-collapse:collapse;
        }

        .two-column td {
            width:50%;
            vertical-align:top;
            padding:5px;
        }

        .box {
            border:1px solid #999;
            border-radius:6px;
            padding:10px;
        }

    </style>
    ';


    /* ========================================================
       REPORT PAGES
    ======================================================== */

    $platformCount =
        count($reportData);

    $index = 0;


    foreach (
        $reportData
        as $platform =>
        $platformData
    ) {

        $index++;


        $current =
            autoreportMetricData(
                $platformData['current']
                ?? []
            );


        $previous =
            autoreportMetricData(
                $platformData['previous']
                ?? []
            );


        $rawCurrent =
            $platformData['current']
            ?? [];


        $platformLabel =
            strtoupper($platform);


        /*
         * Differences
         */
        $changes = [];

        foreach (
            [
                'reach',
                'followers',
                'visits',
                'engagements',
                'pageReach'
            ]
            as $metric
        ) {

            $changes[$metric] =
                autoreportDifference(
                    $current[$metric],
                    $previous[$metric]
                );
        }


        /*
         * Last page class
         */
        $pageClass =
            ($index === $platformCount)
            ? 'page last-page'
            : 'page';


        $html .= '

        <div class="' .
            $pageClass .
        '">

            <div class="header">

                <h1>' .
                    autoreportEsc($company)
                    . '
                </h1>

                <h2>
                    SMM WEEKLY REPORT -
                    ' .
                    $platformLabel
                    . '
                </h2>

            </div>

            <div class="date">

                ' .
                date(
                    "M j, Y",
                    strtotime($dateFrom)
                )
                . '

                -

                ' .

                date(
                    "M j, Y",
                    strtotime($dateTo)
                )
                . '

            </div>


            <div class="section">

                <div class="section-title">
                    REPORT PERIOD VS PREVIOUS PERIOD
                </div>


                <table class="metric-table">

                    <tr>

                        <td class="metric">

                            <div class="label">
                                Views
                            </div>

                            <div class="value">
                                ' .
                                number_format(
                                    $current['reach']
                                )
                                . '
                            </div>

                            ' .
                            autoreportChangeHtml(
                                $changes['reach']
                            )
                            . '

                        </td>


                        <td class="metric">

                            <div class="label">
                                Follows
                            </div>

                            <div class="value">
                                ' .
                                number_format(
                                    $current['followers']
                                )
                                . '
                            </div>

                            ' .
                            autoreportChangeHtml(
                                $changes['followers']
                            )
                            . '

                        </td>

                    </tr>


                    <tr>

                        <td class="metric">

                            <div class="label">
                                Visits
                            </div>

                            <div class="value">
                                ' .
                                number_format(
                                    $current['visits']
                                )
                                . '
                            </div>

                            ' .
                            autoreportChangeHtml(
                                $changes['visits']
                            )
                            . '

                        </td>


                        <td class="metric">

                            <div class="label">
                                Interactions
                            </div>

                            <div class="value">
                                ' .
                                number_format(
                                    $current['engagements']
                                )
                                . '
                            </div>

                            ' .
                            autoreportChangeHtml(
                                $changes['engagements']
                            )
                            . '

                        </td>

                    </tr>


                    <tr>

                        <td
                            class="metric"
                            colspan="2"
                        >

                            <div class="label">
                                Page Reach
                            </div>

                            <div class="value">
                                ' .
                                number_format(
                                    $current['pageReach']
                                )
                                . '
                            </div>

                            ' .
                            autoreportChangeHtml(
                                $changes['pageReach']
                            )
                            . '

                        </td>

                    </tr>

                </table>

            </div>


            <div class="section">

                <div class="section-title">
                    PREVIOUS PERIOD
                </div>


                <table
                    class="metric-table"
                >

                    <tr>

                        <td class="metric">

                            <div class="label">
                                Views
                            </div>

                            <div class="value">
                                ' .
                                number_format(
                                    $previous['reach']
                                )
                                . '
                            </div>

                        </td>


                        <td class="metric">

                            <div class="label">
                                Follows
                            </div>

                            <div class="value">
                                ' .
                                number_format(
                                    $previous['followers']
                                )
                                . '
                            </div>

                        </td>


                        <td class="metric">

                            <div class="label">
                                Visits
                            </div>

                            <div class="value">
                                ' .
                                number_format(
                                    $previous['visits']
                                )
                                . '
                            </div>

                        </td>


                        <td class="metric">

                            <div class="label">
                                Interactions
                            </div>

                            <div class="value">
                                ' .
                                number_format(
                                    $previous['engagements']
                                )
                                . '
                            </div>

                        </td>

                    </tr>

                </table>

            </div>


            <div class="section">

                <table class="two-column">

                    <tr>

                        <td>

                            <div class="box">

                                <div class="section-title">
                                    CONTENT BREAKDOWN
                                </div>

                                ' .
                                autoreportContentBreakdown(
                                    $rawCurrent
                                )
                                . '

                            </div>

                        </td>


                        <td>

                            <div class="box">

                                <div class="section-title">
                                    TOP CONTENT
                                </div>

                                ' .
                                autoreportTopPosts(
                                    $rawCurrent
                                )
                                . '

                            </div>

                        </td>

                    </tr>

                </table>

            </div>


            <div class="section">

                <div class="box">

                    <div class="section-title">
                        TOTAL CONTENT
                    </div>

                    <div style="
                        font-size:24px;
                        text-align:center;
                    ">

                        ' .
                        number_format(
                            $current['posts']
                        )
                        . '

                    </div>

                    <div style="
                        text-align:center;
                        color:#777;
                        font-size:10px;
                    ">
                        Published items
                    </div>

                </div>

            </div>

        </div>

        ';
    }


    /* ========================================================
       RENDER
    ======================================================== */

    $dompdf->loadHtml($html);

    $dompdf->setPaper(
        'legal',
        'landscape'
    );

    $dompdf->render();


    $pdfOutput =
        $dompdf->output();


    /* ========================================================
       REPORT DIRECTORY
    ======================================================== */

    $reportDirectory =
        __DIR__ .
        '/../Reports/' .
        $filename;


    if (!is_dir($reportDirectory)) {

        if (
            !mkdir(
                $reportDirectory,
                0755,
                true
            )
        ) {

            throw new Exception(
                'Unable to create report directory.'
            );
        }
    }


    /* ========================================================
       REPORT FILENAME
    ======================================================== */

    $fromLabel =
        date(
            'F j',
            strtotime($dateFrom)
        );


    $toLabel =
        date(
            'F j Y',
            strtotime($dateTo)
        );


    /*
     * Example:
     *
     * Life of Angge - All -
     * August 24-August 31 2026.pdf
     */
    $reportFilename =
        $project .
        ' - All - ' .
        $fromLabel .
        '-' .
        $toLabel .
        '.pdf';


    /*
     * Sanitize only filesystem-dangerous
     * characters.
     */
    $reportFilename =
        preg_replace(
            '/[\\\\\/:*?"<>|]/',
            '-',
            $reportFilename
        );


    $reportPath =
        $reportDirectory .
        '/' .
        $reportFilename;


    /* ========================================================
       SAVE
    ======================================================== */

    if (
        file_put_contents(
            $reportPath,
            $pdfOutput
        ) === false
    ) {

        throw new Exception(
            'Unable to save generated PDF.'
        );
    }


    return [

        'success' =>
            true,

        'project' =>
            $project,

        'company' =>
            $company,

        'date_from' =>
            $dateFrom,

        'date_to' =>
            $dateTo,

        'filename' =>
            $reportFilename,

        'path' =>
            $reportPath

    ];
}


/* ============================================================
   CHANGE HTML
============================================================ */

function autoreportChangeHtml($difference)
{
    if ($difference === null) {
        return '';
    }

    $isUp =
        $difference >= 0;

    $arrow =
        $isUp ? '▲' : '▼';

    $color =
        $isUp ? 'green' : 'red';


    return '

        <div
            class="change"
            style="color:' .
            $color .
            '"
        >

            ' .
            $arrow .
            ' ' .
            number_format(
                abs($difference),
                1
            )
            . '%

        </div>

    ';
}


/* ============================================================
   OPTIONAL DIRECT TEST
============================================================ */

/*
 * This section lets us test the PDF directly from the browser.
 *
 * Example:
 *
 * autoreport-pdf.php
 * ?project=Life_of_Angge
 * &from=2026-08-24
 * &to=2026-08-31
 *
 * It requires a logged-in user who has access
 * to the project.
 */

if (
    basename($_SERVER['SCRIPT_FILENAME'] ?? '')
    === basename(__FILE__)
) {

    session_start();


    if (
        empty($_SESSION['user_id'])
    ) {

        http_response_code(403);

        exit(
            'Unauthorized'
        );
    }


    $project =
        $_GET['project']
        ?? '';


    $dateFrom =
        $_GET['from']
        ?? '';


    $dateTo =
        $_GET['to']
        ?? '';


    if (
        empty($project) ||
        empty($dateFrom) ||
        empty($dateTo)
    ) {

        http_response_code(400);

        exit(
            'project, from and to are required.'
        );
    }


    /*
     * User must have access to the project.
     */
    // if (
    //     empty($_SESSION['projects']) ||
    //     !in_array(
    //         $project,
    //         $_SESSION['projects']
    //     )
    // ) {

    //     http_response_code(403);

    //     exit(
    //         'You do not have access to this project.'
    //     );
    // }


    try {

        $result =
            generateAutoReportPdf(
                $project,
                $dateFrom,
                $dateTo
            );


        /*
         * For direct browser testing,
         * return the PDF itself.
         */
        $pdf =
            file_get_contents(
                $result['path']
            );


        header(
            'Content-Type: application/pdf'
        );

        header(
            'Content-Disposition: inline; filename="' .
            $result['filename'] .
            '"'
        );

        echo $pdf;

    } catch (Throwable $e) {

    http_response_code(500);

    echo '<h3>AutoReport PDF Error</h3>';

    echo '<pre>';

    echo htmlspecialchars(
        'Message: ' . $e->getMessage()
    );

    echo "\n\n";

    echo htmlspecialchars(
        'File: ' . $e->getFile()
    );

    echo "\n";

    echo htmlspecialchars(
        'Line: ' . $e->getLine()
    );

    echo "\n\n";

    echo htmlspecialchars(
        $e->getTraceAsString()
    );

    echo '</pre>';
}

}