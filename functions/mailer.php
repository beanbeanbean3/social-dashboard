<?php

require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendUserEmail($name, $email, $password) {

    $mail = new PHPMailer(true);

    try {

        // SMTP SETTINGS
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'angelikadizon@gmail.com'; // 🔁 replace
        $mail->Password   = 'ycvs hsvy tsri royn'; // 🔁 replace
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        // Sender
        $mail->setFrom('angelikadizon@gmail.com', 'Filam Software');
        $mail->addAddress($email, $name);

        // Email Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Filam Software Account Is Ready';

        $mail->Body = "
            <p>Hello <strong>$name</strong>,</p>

            <p>Your account for <strong>Filam Software</strong> is now ready.</p>

            <p><strong>Login Details:</strong></p>
            <ul>
                <li>URL: <a href='https://filamsoftware.com/social-dashboard'>Login Here</a></li>
                <li>Email: $email</li>
                <li>Password: $password</li>
            </ul>

            <p><em>Please do not share these credentials with anyone.</em></p>

            <p>Cheers,<br>Filam Software</p>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        return false;
    }
}


/**
 * Send a report email with a PDF attachment.
 */
function sendReportEmail($email, $subject, $body, $attachmentPath) {

    $mail = new PHPMailer(true);

    try {

        // SMTP SETTINGS
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'angelikadizon@gmail.com';
        $mail->Password   = 'ycvs hsvy tsri royn';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

       
        // Sender
        $mail->setFrom(
            'angelikadizon@gmail.com',
            'Filam Software'
        );

        // Recipient
        $mail->addAddress($email);

        // Email
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = nl2br(
            htmlspecialchars($body)
        );

        // Attachment
        if (!file_exists($attachmentPath)) {
            throw new Exception(
                'Attachment not found: ' . $attachmentPath
            );
        }

        $mail->addAttachment($attachmentPath);

        // Send
        $mail->send();

        return true;

    } catch (Exception $e) {

    echo '<pre>';
    echo 'Report email failed: ';
    echo htmlspecialchars($mail->ErrorInfo);
    echo '</pre>';

    error_log(
        'Report email failed: ' .
        $mail->ErrorInfo
    );

    return false;
}
}