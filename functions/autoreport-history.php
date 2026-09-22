<?php

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

    $existingData['history'][] =
        $historyEntry;

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
        'success' => true,
        'message' =>
            'Report history recorded successfully.',
        'duplicate' => false,
        'history' => $historyEntry
    ];
}