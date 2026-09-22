<?php

/**
 * ============================================================
 * AUTO REPORT SCHEDULER
 * ============================================================
 *
 * Checks saved autoreport schedules.
 *
 * For each enabled schedule whose next_run is due:
 *
 * 1. Determine the previous completed week
 * 2. Generate the PDF
 * 3. Send the PDF to project viewers
 * 4. Record report history
 * 5. Move next_run forward by 7 days
 *
 * This file is intended to be run manually first.
 * Cron/Azure scheduling will be added later.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/autoreport-pdf.php';
require_once __DIR__ . '/autoreport-email.php';
require_once __DIR__ . '/autoreport-history.php';


/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

$autoreportsDir = dirname(__DIR__) . '/autoreports/';
$reportsDir = dirname(__DIR__) . '/Reports/';


/*
|--------------------------------------------------------------------------
| BASIC CHECK
|--------------------------------------------------------------------------
*/

if (!is_dir($autoreportsDir)) {
    die("Autoreports directory not found.\n");
}


/*
|--------------------------------------------------------------------------
| FIND SCHEDULE FILES
|--------------------------------------------------------------------------
*/

$files = glob($autoreportsDir . '*.json');

if ($files === false) {
    $files = [];
}

echo "========================================\n";
echo "AUTO REPORT SCHEDULER\n";
echo "========================================\n";
echo "Schedules found: " . count($files) . "\n\n";


/*
|--------------------------------------------------------------------------
| PROCESS EACH SCHEDULE
|--------------------------------------------------------------------------
*/

foreach ($files as $autoreportFile) {

    echo "----------------------------------------\n";
    echo "Processing: " . basename($autoreportFile) . "\n";


    /*
    |--------------------------------------------------------------------------
    | LOAD JSON
    |--------------------------------------------------------------------------
    */

    $json = file_get_contents($autoreportFile);

    if ($json === false) {
        echo "Unable to read schedule. Skipping.\n\n";
        continue;
    }

    $schedule = json_decode($json, true);

    if (!is_array($schedule)) {
        echo "Invalid JSON. Skipping.\n\n";
        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | BASIC SCHEDULE DATA
    |--------------------------------------------------------------------------
    */

    $project = trim($schedule['project'] ?? '');
    $enabled = !empty($schedule['enabled']);
    $timezone = trim($schedule['timezone'] ?? 'UTC');
    $nextRunString = trim($schedule['next_run'] ?? '');


    if ($project === '') {
        echo "Project missing. Skipping.\n\n";
        continue;
    }


    if (!$enabled) {
        echo "Schedule disabled. Skipping.\n\n";
        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | TIMEZONE
    |--------------------------------------------------------------------------
    */

    try {

        $timezoneObject = new DateTimeZone($timezone);

    } catch (Exception $e) {

        echo "Invalid timezone: {$timezone}\n";
        echo "Skipping.\n\n";

        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | NEXT RUN
    |--------------------------------------------------------------------------
    */

    if ($nextRunString === '') {

        echo "next_run is missing. Skipping.\n\n";

        continue;
    }


    try {

        $nextRun = new DateTime(
            $nextRunString
        );

        $nextRun->setTimezone($timezoneObject);

    } catch (Exception $e) {
        echo "Invalid next_run value.\n";
        echo "Skipping.\n\n";

        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | CURRENT TIME
    |--------------------------------------------------------------------------
    |
    | Important:
    | We compare using the schedule's timezone.
    |
    */

    $now = new DateTime('now', $timezoneObject);


    echo "PHP default timezone: " . date_default_timezone_get() . "\n";
    echo "Schedule timezone: {$timezone}\n";
    echo "Current schedule time: " . $now->format(DateTime::ATOM) . "\n";
    echo "Next run schedule time: " . $nextRun->format(DateTime::ATOM) . "\n";

    echo "Project: {$project}\n";
    echo "Timezone: {$timezone}\n";
    echo "Next run: " . $nextRun->format(DateTime::ATOM) . "\n";
    echo "Current:  " . $now->format(DateTime::ATOM) . "\n";


    /*
    |--------------------------------------------------------------------------
    | CHECK IF DUE
    |--------------------------------------------------------------------------
    */

    if ($nextRun > $now) {

        echo "Not due yet. Skipping.\n\n";

        continue;
    }


    echo "Schedule is due.\n";


    /*
    |--------------------------------------------------------------------------
    | DETERMINE REPORT PERIOD
    |--------------------------------------------------------------------------
    |
    | Previous completed Monday-Sunday week.
    |
    */

    $periodTo = clone $now;

    $periodTo->modify('monday this week');
    $periodTo->modify('-1 day');

    $periodFrom = clone $periodTo;
    $periodFrom->modify('-6 days');


    $dateFrom = $periodFrom->format('Y-m-d');
    $dateTo = $periodTo->format('Y-m-d');


    echo "Report period: {$dateFrom} to {$dateTo}\n";


    /*
    |--------------------------------------------------------------------------
    | PROJECT FILENAME
    |--------------------------------------------------------------------------
    */

    $projectFilename = preg_replace(
        '/[^A-Za-z0-9_\-]/',
        '_',
        $project
    );


    /*
    |--------------------------------------------------------------------------
    | REPORT DIRECTORY
    |--------------------------------------------------------------------------
    */

    $projectReportsDir =
    $reportsDir .
    $projectFilename .
    '/';


    if (!is_dir($projectReportsDir)) {

        if (!mkdir($projectReportsDir, 0755, true)) {

            echo "Unable to create report directory.\n";
            echo "Skipping.\n\n";

            continue;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | REPORT FILENAME
    |--------------------------------------------------------------------------
    */

    $fromLabel = date(
        'F j',
        strtotime($dateFrom)
    );

    $toLabel = date(
        'F j Y',
        strtotime($dateTo)
    );


    $reportFilename =
    $projectFilename .
    ' - All - ' .
    $fromLabel .
    '-' .
    $toLabel .
    '.pdf';


    $reportPath =
    $projectReportsDir .
    $reportFilename;


    echo "Report: {$reportFilename}\n";


    /*
    |--------------------------------------------------------------------------
    | DUPLICATE CHECK
    |--------------------------------------------------------------------------
    */

    $history = $schedule['history'] ?? [];

    $alreadyProcessed = false;

    if (is_array($history)) {

        foreach ($history as $historyItem) {

            if (
                ($historyItem['period_from'] ?? '') === $dateFrom &&
                ($historyItem['period_to'] ?? '') === $dateTo
            ) {

                $alreadyProcessed = true;

                break;
            }
        }
    }


    if ($alreadyProcessed) {

        echo "This report period already exists in history.\n";
        echo "Skipping email/PDF generation.\n";

    } else {

        /*
|--------------------------------------------------------------------------
| GENERATE PDF
|--------------------------------------------------------------------------
*/

echo "Generating PDF...\n";

try {

    $pdfResult =
    generateAutoReportPdf(
        $project,
        $dateFrom,
        $dateTo
    );

} catch (Throwable $e) {

    echo "PDF generation failed.\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Skipping email.\n\n";

    continue;
}


if (
    !is_array($pdfResult) ||
    empty($pdfResult['success']) ||
    empty($pdfResult['path'])
) {

    echo "PDF generation returned an invalid result.\n";
    echo "Skipping email.\n\n";

    continue;
}


$reportPath = $pdfResult['path'];

$reportFilename =
$pdfResult['filename']
?? basename($reportPath);


echo "PDF generated successfully.\n";
echo "Report: {$reportFilename}\n";
echo "Path: {$reportPath}\n";


/*
|--------------------------------------------------------------------------
| SEND EMAIL
|--------------------------------------------------------------------------
*/

echo "Sending report email...\n";

try {

    $emailResult =
    autoreportSendEmail(
        $project,
        $reportPath,
        $dateFrom,
        $dateTo
    );

} catch (Throwable $e) {

    echo "Email error: " .
    $e->getMessage() .
    "\n\n";

    continue;
}


echo "Email processing completed.\n";


/*
|--------------------------------------------------------------------------
| RECORD HISTORY
|--------------------------------------------------------------------------
*/

try {

    $historyResult =
    autoreportRecordHistory(
        $project,
        $emailResult
    );

    echo "History recorded.\n";

} catch (Throwable $e) {

    echo "History error: " .
    $e->getMessage() .
    "\n";

    /*
     * Do not move next_run if history could not
     * be recorded.
     */

    continue;
}


/*
|--------------------------------------------------------------------------
| DISPLAY RESULT
|--------------------------------------------------------------------------
*/

echo json_encode(
    $emailResult,
    JSON_PRETTY_PRINT
);

echo "\n";
}


    /*
    |--------------------------------------------------------------------------
    | MOVE NEXT RUN FORWARD
    |--------------------------------------------------------------------------
    */

    $nextRun->modify('+7 days');

    $schedule['next_run'] =
    $nextRun->format(DateTime::ATOM);


    /*
    |--------------------------------------------------------------------------
    | SAVE UPDATED SCHEDULE
    |--------------------------------------------------------------------------
    */

    $updatedJson = json_encode(
        $schedule,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_SLASHES
    );


    if (
        $updatedJson === false ||
        file_put_contents(
            $autoreportFile,
            $updatedJson
        ) === false
    ) {

        echo "WARNING: Unable to update next_run.\n";

    } else {

        echo "Next run updated to: " .
        $schedule['next_run'] .
        "\n";
    }


    echo "\n";
}


echo "========================================\n";
echo "SCHEDULER FINISHED\n";
echo "========================================\n";