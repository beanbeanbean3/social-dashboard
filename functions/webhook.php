<?php

// VERIFY TOKEN (you set this in Meta App)
$VERIFY_TOKEN = "my_verify_token";

/* =========================
   1. Verification (GET)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (
        $_GET['hub_mode'] === 'subscribe' &&
        $_GET['hub_verify_token'] === $VERIFY_TOKEN
    ) {
        echo $_GET['hub_challenge'];
        exit;
    }

    echo "Invalid verification";
    exit;
}

/* =========================
   2. Receive Events (POST)
========================= */

$input = json_decode(file_get_contents('php://input'), true);

// Log everything first (IMPORTANT for debugging)
file_put_contents(
    __DIR__ . '/webhook_log.json',
    json_encode($input, JSON_PRETTY_PRINT),
    FILE_APPEND
);

// Process webhook
foreach ($input['entry'] ?? [] as $entry) {
    foreach ($entry['changes'] ?? [] as $change) {

        if ($change['field'] === 'media') {

            $mediaId = $change['value']['media_id'] ?? null;

            if ($mediaId) {

                // Save media ID for later processing
                file_put_contents(
                    __DIR__ . '/story_queue.json',
                    json_encode([
                        'media_id' => $mediaId,
                        'time' => time()
                    ]) . PHP_EOL,
                    FILE_APPEND
                );
            }
        }
    }
}

echo "EVENT_RECEIVED";