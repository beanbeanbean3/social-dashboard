<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/functions/mailer.php';


// --------------------------------------------------
// REPORT SETTINGS
// --------------------------------------------------

$recipient = 'angelikadizon@gmail.com';

$subject = 'Life of Angge Report';

$body = 'Testing the limits';

$attachmentPath = __DIR__ . '/backups/Life of Angge-all.pdf';


// --------------------------------------------------
// TEST SETTINGS
// --------------------------------------------------

$sendCount = 3;
$delay = 10;


// --------------------------------------------------
// CHECK ATTACHMENT
// --------------------------------------------------

if (!file_exists($attachmentPath)) {

    die(
        '<h2>Error</h2>' .
        '<p>Report attachment was not found:</p>' .
        '<pre>' . htmlspecialchars($attachmentPath) . '</pre>'
    );
}


// --------------------------------------------------
// START
// --------------------------------------------------

echo '<h2>Report test started</h2>';

echo '<p>Sending ' . $sendCount . ' emails.</p>';
echo '<p>There will be ' . $delay . ' seconds between each email.</p>';

flush();


// --------------------------------------------------
// SEND 3 EMAILS
// --------------------------------------------------

for ($i = 1; $i <= $sendCount; $i++) {

    echo '<p>Waiting ' . $delay . ' seconds before email #' . $i . '...</p>';
    flush();

    sleep($delay);

    echo '<p>Sending email #' . $i . '...</p>';
    flush();

    $sent = sendReportEmail(
        $recipient,
        $subject,
        $body,
        $attachmentPath
    );

    if ($sent) {

    echo '<p>Email #' . $i . ' sent successfully.</p>';

} else {

    echo '<p>Email #' . $i . ' FAILED.</p>';
    echo '<p>Please check the PHPMailer SMTP error above.</p>';
}

    flush();
}


// --------------------------------------------------
// DONE
// --------------------------------------------------

echo '<h2>Test completed.</h2>';

?>