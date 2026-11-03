<?php





use PHPMailer\PHPMailer\PHPMailer;

require_once APP_ROOT . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once APP_ROOT . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once APP_ROOT . '/vendor/phpmailer/phpmailer/src/SMTP.php';
$mailer = new PHPMailer();

$mail_error = '';
$mail_attachments = $mail_attachments ?? [];






 $mail_username = 'dd98c16486056d';
 $mail_password = '876a4f8e2b729b';

$mail_host = 'sandbox.smtp.mailtrap.io';
$mail_port = '2525';
$mail_from_email = 'no-reply@soigne-apparel.com';
$mail_from_name = 'Soigné';

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
