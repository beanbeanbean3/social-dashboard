<?php

/**
 * ============================================================
 * AUTO REPORT EMAIL
 * ============================================================
 *
 * Resolves project viewers:
 *
 * Projects/<project>.json
 *        ↓
 * viewers[]
 *        ↓
 * json/<user-id>.json
 *        ↓
 * email
 *
 * Then sends the generated PDF using
 * the existing sendReportEmail() function.
 */


/* ============================================================
   MAILER
============================================================ */

require_once __DIR__ . '/mailer.php';
// require_once __DIR__ . '/autoreport.php';
require_once __DIR__ . '/autoreport-history.php';


/* ============================================================
   PROJECT
============================================================ */

function autoreportLoadEmailProject($project)
{
    $filename =
        preg_replace(
            '/[^A-Za-z0-9_\-]/',
            '_',
            $project
        );


    $path =
        __DIR__ .
        '/../Projects/' .
        $filename .
        '.json';


    if (!file_exists($path)) {

        throw new Exception(
            'Project file not found.'
        );
    }


    $data =
        json_decode(
            file_get_contents($path),
            true
        );


    if (!is_array($data)) {

        throw new Exception(
            'Invalid project JSON.'
        );
    }


    return $data;
}


/* ============================================================
   VIEWER EMAILS
============================================================ */

function autoreportGetViewerEmails(
    $project
) {

    $projectData =
        autoreportLoadEmailProject(
            $project
        );


    $viewers =
        $projectData['viewers']
        ?? [];


    $emails = [];


    foreach ($viewers as $userId) {

        $userId =
            basename(
                (string)$userId
            );


        $userFile =
            __DIR__ .
            '/../json/' .
            $userId .
            '.json';


        if (
            !file_exists($userFile)
        ) {

            continue;
        }


        $userData =
            json_decode(
                file_get_contents($userFile),
                true
            );


        if (
            !is_array($userData)
        ) {

            continue;
        }


        $email =
            trim(
                $userData['email']
                ?? ''
            );


        if (
            empty($email) ||
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            continue;
        }


        /*
         * Avoid duplicate emails.
         */
        if (
            !in_array(
                $email,
                $emails,
                true
            )
        ) {

            $emails[] =
                $email;
        }
    }


    return $emails;
}


/* ============================================================
   SEND REPORT
============================================================ */

/* ============================================================
   SEND REPORT
============================================================ */

function autoreportSendEmail(
    $project,
    $reportPath,
    $dateFrom,
    $dateTo
) {

    if (!file_exists($reportPath)) {

        throw new Exception(
            'Report PDF not found.'
        );
    }


    $emails =
        autoreportGetViewerEmails(
            $project
        );


    if (empty($emails)) {

        throw new Exception(
            'No valid viewer email addresses found.'
        );
    }


    $projectData =
        autoreportLoadEmailProject(
            $project
        );


    $company =
        $projectData['company_name']
        ?? $project;


    $dateLabel =
        date(
            'F j',
            strtotime($dateFrom)
        )
        .
        '-'
        .
        date(
            'F j Y',
            strtotime($dateTo)
        );


    $subject =
        $company .
        ' - Social Media Report - ' .
        $dateLabel;


    $body =

        "Hello,\n\n" .

        "Please find attached the social media report for " .
        $company .
        ".\n\n" .

        "Report period: " .
        date(
            'F j, Y',
            strtotime($dateFrom)
        ) .
        " - " .
        date(
            'F j, Y',
            strtotime($dateTo)
        ) .
        "\n\n" .

        "Regards,\n" .
        "Filam Software";


    $results = [];


    foreach ($emails as $email) {

        try {

            $success =
                sendReportEmail(
                    $email,
                    $subject,
                    $body,
                    $reportPath
                );


            $results[] = [

                'email' =>
                    $email,

                'success' =>
                    $success,

                'error' =>
                    $success
                    ? null
                    : 'Mailer returned false'

            ];

        } catch (Throwable $e) {

            $results[] = [

                'email' =>
                    $email,

                'success' =>
                    false,

                'error' =>
                    $e->getMessage()

            ];
        }
    }


    $overallSuccess =
        count(
            array_filter(
                $results,
                fn($item) =>
                    $item['success']
            )
        ) > 0;


    return [

        'success' =>
            $overallSuccess,

        'project' =>
            $project,

        'report' =>
            basename($reportPath),

        'period_from' =>
            $dateFrom,

        'period_to' =>
            $dateTo,

        'recipients' =>
            $results

    ];
}


/* ============================================================
   RECORD REPORT HISTORY
============================================================ */

function autoreportRecordHistory(
    $project,
    $result
) {

    $projectFilename =
        preg_replace(
            '/[^A-Za-z0-9_\-]/',
            '_',
            $project
        );

    $autoreportFile =
        __DIR__ .
        '/../autoreports/' .
        $projectFilename .
        '.json';

    return recordAutoreportHistory(
        $autoreportFile,
        $result['period_from'] ?? '',
        $result['period_to'] ?? '',
        $result['report'] ?? '',
        !empty($result['success']),
        $result['recipients'] ?? [],
        date(DateTime::ATOM)
    );
}

/* ============================================================
   DIRECT TEST
============================================================ */

if (
    isset($_GET['project']) &&
    isset($_GET['from']) &&
    isset($_GET['to'])
) {

    try {

        $project =
            trim($_GET['project']);

        $dateFrom =
            trim($_GET['from']);

        $dateTo =
            trim($_GET['to']);


        if (
            empty($project) ||
            empty($dateFrom) ||
            empty($dateTo)
        ) {

            throw new Exception(
                'Project, from date, and to date are required.'
            );
        }


        /*
         * Expected report filename:
         *
         * Life_of_Angge - All - August 24-August 31 2026.pdf
         */

        $projectFilename =
            preg_replace(
                '/[^A-Za-z0-9_\-]/',
                '_',
                $project
            );


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


        $reportPath =
            __DIR__ .
            '/../Reports/' .
            $projectFilename .
            '/' .
            $projectFilename .
            ' - All - ' .
            $fromLabel .
            '-' .
            $toLabel .
            '.pdf';


        echo '<h2>AutoReport Email Test</h2>';

        echo '<p><strong>Project:</strong> ' .
            htmlspecialchars($project) .
            '</p>';

        echo '<p><strong>Period:</strong> ' .
            htmlspecialchars($dateFrom) .
            ' to ' .
            htmlspecialchars($dateTo) .
            '</p>';

        echo '<p><strong>PDF:</strong><br>' .
            htmlspecialchars($reportPath) .
            '</p>';


        if (!file_exists($reportPath)) {

            throw new Exception(
                'Generated PDF was not found at: ' .
                $reportPath
            );
        }


        echo '<p>PDF found. Sending report...</p>';

        flush();


        $result =
            autoreportSendEmail(
                $project,
                $reportPath,
                $dateFrom,
                $dateTo
            );


echo '<p>Report email processing completed.</p>';

echo '<h3>Email Result</h3>';

echo '<pre>';
echo htmlspecialchars(
    json_encode(
        $result,
        JSON_PRETTY_PRINT
    )
);
echo '</pre>';


/*
 * Record the run in autoreport history.
 */

echo '<p>Recording report history...</p>';

flush();


$historyResult =
    autoreportRecordHistory(
        $project,
        $result
    );


echo '<h3>History Result</h3>';

echo '<pre>';
echo htmlspecialchars(
    json_encode(
        $historyResult,
        JSON_PRETTY_PRINT
    )
);
echo '</pre>';

        echo '<h3>Result</h3>';

        echo '<pre>';
        echo htmlspecialchars(
            json_encode(
                $result,
                JSON_PRETTY_PRINT
            )
        );
        echo '</pre>';


    } catch (Throwable $e) {

        echo '<h3>AutoReport Email Error</h3>';

        echo '<pre>';
        echo htmlspecialchars(
            $e->getMessage()
        );
        echo '</pre>';
    }
}