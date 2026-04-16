<?php
/**
 * AIXCO – Contact Form Mailer
 * Place this file in the same directory as index.html on your PHP server.
 *
 * SETUP:
 *  1. Install PHPMailer via Composer:  composer require phpmailer/phpmailer
 *  2. Fill in your SMTP credentials in the CONFIG section below.
 *  3. Set $to = your receiving email address.
 */

header('Content-Type: application/json; charset=UTF-8');

// ─── CONFIG ────────────────────────────────────────────────────────────────
$to          = 'info@axico.com';            // ← Receiving email
$subject     = 'Neue Anfrage: AIXCO-Festzinsanlage';

// SMTP credentials (e.g. Gmail, Mailgun, SendGrid, your hosting provider)
$smtp_host   = 'smtp.example.com';          // ← SMTP host
$smtp_port   = 587;                         // 587 (TLS) or 465 (SSL)
$smtp_user   = 'your@email.com';            // ← SMTP username
$smtp_pass   = 'your_password';             // ← SMTP password
$smtp_from   = 'your@email.com';            // ← From address
$smtp_name   = 'AIXCO Festzinsanlage';      // ← From name
// ───────────────────────────────────────────────────────────────────────────

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Sanitize inputs
function clean(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

$vorname  = clean($_POST['vorname']  ?? '');
$nachname = clean($_POST['nachname'] ?? '');
$phone    = clean($_POST['phone']    ?? '');
$email    = clean($_POST['email']    ?? '');
$amount   = clean($_POST['amount']   ?? '');

// Basic validation
if (!$vorname || !$nachname || !$phone || !$email || !$amount) {
    echo json_encode(['success' => false, 'message' => 'Bitte füllen Sie alle Pflichtfelder aus.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.']);
    exit;
}

// ─── Send with PHPMailer ───────────────────────────────────────────────────

$autoload = __DIR__ . '/vendor/autoload.php';

if (!file_exists($autoload)) {
    // Fallback: send with PHP's built-in mail() if PHPMailer not installed
    sendWithBuiltIn($to, $subject, $vorname, $nachname, $phone, $email, $amount, $smtp_from, $smtp_name);
    exit;
}

require $autoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $smtp_host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp_user;
    $mail->Password   = $smtp_pass;
    $mail->SMTPSecure = ($smtp_port === 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $smtp_port;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($smtp_from, $smtp_name);
    $mail->addAddress($to);
    $mail->addReplyTo($email, "$vorname $nachname");

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = buildHtmlBody($vorname, $nachname, $phone, $email, $amount);
    $mail->AltBody = buildTextBody($vorname, $nachname, $phone, $email, $amount);

    $mail->send();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'E-Mail konnte nicht gesendet werden: ' . $mail->ErrorInfo]);
}

// ─── Helpers ──────────────────────────────────────────────────────────────

function buildHtmlBody(string $v, string $n, string $p, string $e, string $a): string {
    return "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px'>
      <div style='background:#1C6FC4;padding:20px;text-align:center'>
        <h1 style='color:#F8CF01;margin:0;font-size:24px'>AIXCO Festzinsanlage</h1>
        <p style='color:white;margin:5px 0 0'>Neue Kontaktanfrage</p>
      </div>
      <div style='background:#f5f5f5;padding:24px;border:1px solid #ddd'>
        <table style='width:100%;border-collapse:collapse'>
          <tr><td style='padding:10px;font-weight:bold;width:160px'>Vorname:</td><td style='padding:10px'>$v</td></tr>
          <tr style='background:white'><td style='padding:10px;font-weight:bold'>Nachname:</td><td style='padding:10px'>$n</td></tr>
          <tr><td style='padding:10px;font-weight:bold'>Telefon:</td><td style='padding:10px'>$p</td></tr>
          <tr style='background:white'><td style='padding:10px;font-weight:bold'>E-Mail:</td><td style='padding:10px'><a href='mailto:$e'>$e</a></td></tr>
          <tr><td style='padding:10px;font-weight:bold'>Investitionsbetrag:</td><td style='padding:10px;color:#1C6FC4;font-weight:bold'>$a</td></tr>
        </table>
      </div>
      <div style='background:#0d3b6a;padding:12px;text-align:center'>
        <p style='color:#ccc;font-size:12px;margin:0'>Gesendet über das Kontaktformular auf aixco-anleihe.de</p>
      </div>
    </div>";
}

function buildTextBody(string $v, string $n, string $p, string $e, string $a): string {
    return "Neue Anfrage – AIXCO Festzinsanlage\n\n"
         . "Vorname:          $v\n"
         . "Nachname:         $n\n"
         . "Telefon:          $p\n"
         . "E-Mail:           $e\n"
         . "Investitionsbetrag: $a\n";
}

function sendWithBuiltIn(string $to, string $subject, string $v, string $n, string $p, string $e, string $a, string $from, string $fromName): void {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: $fromName <$from>\r\n";
    $headers .= "Reply-To: $v $n <$e>\r\n";

    $body = buildHtmlBody($v, $n, $p, $e, $a);
    $sent = mail($to, $subject, $body, $headers);
    echo json_encode(['success' => $sent, 'message' => $sent ? '' : 'mail() fehlgeschlagen.']);
}