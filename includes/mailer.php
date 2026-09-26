<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';

/**
 * Lightweight Gmail SMTP sender.
 * Uses STARTTLS on port 587 by default and authenticates with a Gmail App Password.
 */
function smtp_read($socket): string {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') break;
    }
    return $response;
}

function smtp_expect($socket, array $codes): void {
    $response = smtp_read($socket);
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $codes, true)) {
        throw new RuntimeException('SMTP error '.$code.': '.trim($response));
    }
}

function smtp_cmd($socket, string $command, array $codes): void {
    fwrite($socket, $command."\r\n");
    smtp_expect($socket, $codes);
}

function smtp_connect_and_auth() {
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false
        ]
    ]);

    $socket = stream_socket_client(
        'tcp://'.MAIL_HOST.':'.MAIL_PORT,
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        throw new RuntimeException('Unable to connect to Gmail SMTP ('.$errno.'): '.$errstr);
    }
    stream_set_timeout($socket, 20);

    try {
        smtp_expect($socket, [220]);
        smtp_cmd($socket, 'EHLO greatsolomon.local', [250]);
        smtp_cmd($socket, 'STARTTLS', [220]);

        $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($crypto !== true) {
            throw new RuntimeException('Could not establish TLS with Gmail SMTP.');
        }

        smtp_cmd($socket, 'EHLO greatsolomon.local', [250]);
        smtp_cmd($socket, 'AUTH LOGIN', [334]);
        smtp_cmd($socket, base64_encode(MAIL_USERNAME), [334]);
        smtp_cmd($socket, base64_encode(str_replace(' ', '', MAIL_PASSWORD)), [235]);

        return $socket;
    } catch (Throwable $e) {
        fclose($socket);
        throw $e;
    }
}

function smtp_send_message(string $recipient, string $subject, string $htmlBody): void {
    if (trim(MAIL_USERNAME) === '' || trim(MAIL_PASSWORD) === '') {
        throw new RuntimeException('Gmail SMTP username or App Password is not configured.');
    }

    $socket = smtp_connect_and_auth();
    try {
        $from = OTP_SENDER_EMAIL ?: MAIL_FROM_EMAIL;
        smtp_cmd($socket, 'MAIL FROM:<'.$from.'>', [250]);
        smtp_cmd($socket, 'RCPT TO:<'.$recipient.'>', [250, 251]);
        smtp_cmd($socket, 'DATA', [354]);

        $safeSubject = '=?UTF-8?B?'.base64_encode($subject).'?=';
        $headers = [
            'From: '.MAIL_FROM_NAME.' <'.$from.'>',
            'To: <'.$recipient.'>',
            'Subject: '.$safeSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit'
        ];

        // SMTP requires any body line beginning with "." to be dot-stuffed.
        $body = preg_replace('/(^|\r\n|\n)\./', '$1..', $htmlBody);
        $message = implode("\r\n", $headers)."\r\n\r\n".$body."\r\n.";
        smtp_cmd($socket, $message, [250]);
        smtp_cmd($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}

function send_otp_email(string $recipient, string $recipientName, string $otp): void {
    $safeName = htmlspecialchars($recipientName ?: 'Administrator', ENT_QUOTES, 'UTF-8');
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

    $body = '<!doctype html><html><body style="margin:0;font-family:Arial,sans-serif;background:#f7f3ff;padding:30px">'
          . '<div style="max-width:560px;margin:auto;background:#fff;border-radius:16px;padding:30px;border:1px solid #ddd6fe">'
          . '<h2 style="color:#6d28d9;margin-top:0">Great Solomon Manpower Services Inc.</h2>'
          . '<p style="color:#475569">Core Transaction 4 security verification</p>'
          . '<p>Hello '.$safeName.',</p>'
          . '<p>Your one-time verification code is:</p>'
          . '<div style="font-size:34px;font-weight:800;letter-spacing:8px;color:#6d28d9;text-align:center;padding:18px;background:#f3e8ff;border-radius:12px">'.$safeOtp.'</div>'
          . '<p>This code expires in '.OTP_EXPIRY_MINUTES.' minutes. If you did not attempt to sign in, you can ignore this email.</p>'
          . '<p style="color:#6b7280;font-size:13px">Sent by the CT4 security mailbox.</p>'
          . '</div></body></html>';

    smtp_send_message($recipient, 'Great Solomon Manpower Services Inc. — Security Verification Code', $body);
}

function send_feedback_email(string $recipient, string $senderName, string $senderRole, string $feedback): void {
    $name=htmlspecialchars($senderName,ENT_QUOTES,'UTF-8');
    $role=htmlspecialchars($senderRole,ENT_QUOTES,'UTF-8');
    $bodyText=nl2br(htmlspecialchars($feedback,ENT_QUOTES,'UTF-8'));
    $body='<html><body style="font-family:Arial,sans-serif"><h2>New User Feedback</h2>'
         .'<p><strong>Name:</strong> '.$name.'</p><p><strong>Role:</strong> '.$role.'</p>'
         .'<p><strong>Feedback:</strong></p><div style="padding:16px;background:#f8fafc;border-radius:10px">'.$bodyText.'</div>'
         .'<p style="color:#64748b;font-size:12px">Sent from Great Solomon Manpower Services Inc. Core Transaction 4.</p></body></html>';
    smtp_send_message($recipient, 'Great Solomon CT4 — User Feedback', $body);
}
