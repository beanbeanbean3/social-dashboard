<?php

session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized.'
    ]);
    exit;
}

if (!isset($_SESSION['projects']) || !is_array($_SESSION['projects'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'No projects assigned.'
    ]);
    exit;
}

$action = $_POST['action'] ?? '';

$projectName = $_POST['project'] ?? '';

if ($projectName === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Project is required.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Verify project access
|--------------------------------------------------------------------------
*/

if (!in_array($projectName, $_SESSION['projects'], true)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'You do not have access to this project.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Sanitize project filename
|--------------------------------------------------------------------------
*/

$projectFile = preg_replace(
    '/[^A-Za-z0-9_\-]/',
    '',
    $projectName
);

if ($projectFile === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid project name.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Autoreport directory
|--------------------------------------------------------------------------
*/

$autoreportsDir = dirname(__DIR__) . '/autoreports/';

if (!is_dir($autoreportsDir)) {
    if (!mkdir($autoreportsDir, 0755, true)) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Unable to create autoreports directory.'
        ]);

        exit;
    }
}

$autoreportFile = $autoreportsDir . $projectFile . '.json';


/*
|--------------------------------------------------------------------------
| RECORD HISTORY HELPER
|--------------------------------------------------------------------------
*/

function recordAutoreportHistory(
    $autoreportFile,
    $periodFrom,
    $periodTo,
    $report,
    $success,
    $recipients,
    $runAt = null
) {

    $existingData = [];

    if (file_exists($autoreportFile)) {

        $existingJson =
            file_get_contents($autoreportFile);

        if ($existingJson !== false) {

            $decoded =
                json_decode(
                    $existingJson,
                    true
                );

            if (is_array($decoded)) {
                $existingData = $decoded;
            }
        }
    }

    /*
     * Make sure history exists.
     */

    if (
        !isset($existingData['history']) ||
        !is_array($existingData['history'])
    ) {

        $existingData['history'] = [];
    }

    /*
     * Prevent duplicate history entries
     * for the same report period.
     */

    foreach ($existingData['history'] as $historyItem) {

        if (
            ($historyItem['period_from'] ?? '') === $periodFrom &&
            ($historyItem['period_to'] ?? '') === $periodTo
        ) {

            return [
                'success' => true,
                'message' => 'Report history already exists.',
                'duplicate' => true,
                'history' => $historyItem
            ];
        }
    }

    /*
     * Build history entry.
     */

    $historyEntry = [

        'run_at' =>
            $runAt ??
            date(DateTime::ATOM),

        'period_from' =>
            $periodFrom,

        'period_to' =>
            $periodTo,

        'report' =>
            basename($report),

        'success' =>
            (bool)$success,

        'recipients' =>
            is_array($recipients)
                ? $recipients
                : []
    ];

    /*
     * Add to history.
     */

    $existingData['history'][] =
        $historyEntry;

    /*
     * Save.
     */

    $json =
        json_encode(
            $existingData,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES
        );

    if (
        $json === false ||
        file_put_contents(
            $autoreportFile,
            $json
        ) === false
    ) {

        throw new Exception(
            'Unable to save report history.'
        );
    }

    return [

        'success' =>
            true,

        'message' =>
            'Report history recorded successfully.',

        'duplicate' =>
            false,

        'history' =>
            $historyEntry
    ];
}



/*
|--------------------------------------------------------------------------
| SAVE SCHEDULE
|--------------------------------------------------------------------------
*/

if ($action === 'save') {

    $startDate = $_POST['start_date'] ?? '';
    $time = $_POST['time'] ?? '';
    $timezone = $_POST['timezone'] ?? '';

    if ($startDate === '' || $time === '' || $timezone === '') {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Start date, time, and timezone are required.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate date
    |--------------------------------------------------------------------------
    */

    $dateObject = DateTime::createFromFormat('Y-m-d', $startDate);

    if (!$dateObject || $dateObject->format('Y-m-d') !== $startDate) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid start date.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate time
    |--------------------------------------------------------------------------
    */

    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid time.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate timezone
    |--------------------------------------------------------------------------
    */

    try {
        $timezoneObject = new DateTimeZone($timezone);
    } catch (Exception $e) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid timezone.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate next run
    |--------------------------------------------------------------------------
    */

    $nextRun = new DateTime(
        $startDate . ' ' . $time . ':00',
        $timezoneObject
    );

    /*
    |--------------------------------------------------------------------------
    | Existing autoreport data
    |--------------------------------------------------------------------------
    */

    $existingData = [];

    if (file_exists($autoreportFile)) {

        $existingJson = file_get_contents($autoreportFile);

        if ($existingJson !== false) {

            $decoded = json_decode($existingJson, true);

            if (is_array($decoded)) {
                $existingData = $decoded;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Preserve existing history
    |--------------------------------------------------------------------------
    */

    $history = [];

    if (
        isset($existingData['history']) &&
        is_array($existingData['history'])
    ) {
        $history = $existingData['history'];
    }

    /*
    |--------------------------------------------------------------------------
    | Schedule data
    |--------------------------------------------------------------------------
    */

    $scheduleData = [
        'project' => $projectName,
        'enabled' => true,
        'start_date' => $startDate,
        'time' => $time,
        'timezone' => $timezone,
        'frequency' => 'weekly',
        'next_run' => $nextRun->format(DateTime::ATOM),
        'history' => $history
    ];

    /*
    |--------------------------------------------------------------------------
    | Save JSON
    |--------------------------------------------------------------------------
    */

    $json = json_encode(
        $scheduleData,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );

    if ($json === false || file_put_contents($autoreportFile, $json) === false) {

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Unable to save autoreport schedule.'
        ]);

        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Autoreport schedule saved successfully.',
        'schedule' => $scheduleData
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| CANCEL SCHEDULE
|--------------------------------------------------------------------------
*/

if ($action === 'cancel') {

    /*
     * If no schedule exists, there is nothing to cancel.
     */

    if (!file_exists($autoreportFile)) {

        echo json_encode([
            'success' => true,
            'message' => 'No autoreport schedule exists.'
        ]);

        exit;
    }

    $existingJson = file_get_contents($autoreportFile);
    $existingData = json_decode($existingJson, true);

    if (!is_array($existingData)) {
        $existingData = [];
    }

    /*
     * Keep the configuration/history but disable it.
     */

    $existingData['project'] = $projectName;
    $existingData['enabled'] = false;

    $json = json_encode(
        $existingData,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );

    if ($json === false || file_put_contents($autoreportFile, $json) === false) {

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Unable to cancel autoreport schedule.'
        ]);

        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Autoreport cancelled successfully.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| GET SCHEDULE
|--------------------------------------------------------------------------
*/

if ($action === 'get') {

    if (!file_exists($autoreportFile)) {

        echo json_encode([
            'success' => true,
            'exists' => false,
            'schedule' => null
        ]);

        exit;
    }

    $json = file_get_contents($autoreportFile);
    $schedule = json_decode($json, true);

    if (!is_array($schedule)) {

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid autoreport configuration.'
        ]);

        exit;
    }

    echo json_encode([
        'success' => true,
        'exists' => true,
        'schedule' => $schedule
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Unknown action
|--------------------------------------------------------------------------
*/

http_response_code(400);

echo json_encode([
    'success' => false,
    'message' => 'Invalid action.'
]);


/*
|--------------------------------------------------------------------------
| RECORD REPORT HISTORY
|--------------------------------------------------------------------------
*/

if ($action === 'record') {

    $periodFrom =
        $_POST['period_from'] ?? '';

    $periodTo =
        $_POST['period_to'] ?? '';

    $report =
        $_POST['report'] ?? '';

    $success =
        isset($_POST['success'])
            ? filter_var(
                $_POST['success'],
                FILTER_VALIDATE_BOOLEAN
            )
            : false;

    $recipientsJson =
        $_POST['recipients'] ?? '[]';

    $recipients =
        json_decode(
            $recipientsJson,
            true
        );

    if (!is_array($recipients)) {
        $recipients = [];
    }

    if (
        $periodFrom === '' ||
        $periodTo === '' ||
        $report === ''
    ) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' =>
                'Report history data is incomplete.'
        ]);

        exit;
    }

    try {

        $historyResult =
            recordAutoreportHistory(
                $autoreportFile,
                $periodFrom,
                $periodTo,
                $report,
                $success,
                $recipients,
                $_POST['run_at'] ?? null
            );

        echo json_encode(
            $historyResult
        );

    } catch (Throwable $e) {

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' =>
                $e->getMessage()
        ]);
    }

    exit;
}