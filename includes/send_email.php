<?php
// Sends one email with PHPMailer (Mailtrap SMTP).
// Set these before including this file:
//   $mail_to, $mail_name, $mail_subject, $mail_html, $mail_text
//   $mail_attachments (optional) = [['content' => ..., 'name' => ..., 'mime' => ...]]
// Result: $mail_error is empty when the email was sent, otherwise it holds the problem.
use PHPMailer\PHPMailer\PHPMailer;

require_once(APP_ROOT . '/vendor/autoload.php');

$mail_error = '';
$mail_attachments = $mail_attachments ?? [];

$mail_username = getenv('MAILTRAP_USERNAME');
$mail_password = getenv('MAILTRAP_PASSWORD');
$mail_host = getenv('MAILTRAP_HOST') ?: 'sandbox.smtp.mailtrap.io';
$mail_port = getenv('MAILTRAP_PORT') ?: '2525';
$mail_from_email = getenv('MAILTRAP_FROM_EMAIL') ?: 'no-reply@soigne-apparel.test';
$mail_from_name = getenv('MAILTRAP_FROM_NAME') ?: 'Soigné';

if (!$mail_username || !$mail_password) {
    $mail_error = 'Mailtrap SMTP credentials are not configured.';
} else {
    try {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $mail_host;
        $mailer->SMTPAuth = true;
        $mailer->Username = $mail_username;
        $mailer->Password = $mail_password;
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Port = (int)$mail_port;
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->setFrom($mail_from_email, $mail_from_name);
        $mailer->addAddress($mail_to, $mail_name);
        $mailer->Subject = $mail_subject;
        $mailer->isHTML(true);
        $mailer->Body = $mail_html;
        $mailer->AltBody = $mail_text;
        foreach ($mail_attachments as $attachment) {
            $mailer->addStringAttachment($attachment['content'], $attachment['name'], PHPMailer::ENCODING_BASE64, $attachment['mime']);
        }
        $mailer->send();
    } catch (Exception $e) {
        $mail_error = 'Could not send the order email. ' . $e->getMessage();
    }
}
