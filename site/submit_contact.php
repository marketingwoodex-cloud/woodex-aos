<?php
include "lbpanel/config/connection.php";
header('Content-Type: application/json');

// PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/Exception.php';
require 'phpmailer/src/SMTP.php';

require_once __DIR__ . '/lbpanel/includes/email_config.php';

/* ---------------- Helpers ---------------- */
function base_url() {
    if (!empty($GLOBALS['domain'])) return rtrim($GLOBALS['domain'], '/') . '/';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/';
}
function clean_text($v, $max = 2000) {
    $v = trim((string)$v);
    $v = str_replace(["\r", "\n"], ' ', $v); // stop header injection
    $v = strip_tags($v);
    if (mb_strlen($v) > $max) $v = mb_substr($v, 0, $max);
    return $v;
}
/* ---------------- Inputs ---------------- */
$name        = clean_text($_POST['name'] ?? '', 120);
$email       = clean_text($_POST['email'] ?? '', 160);
$phone       = clean_text($_POST['phone'] ?? '', 40);
$topic       = clean_text($_POST['topic'] ?? 'General', 60);
$order_no    = clean_text($_POST['order_number'] ?? '', 80);
$message     = clean_text($_POST['message'] ?? '', 2000);
$consent     = isset($_POST['consent']) ? 1 : 0;
$honeypot    = trim($_POST['website'] ?? ''); // must be empty
$ip          = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent  = $_SERVER['HTTP_USER_AGENT'] ?? '';

/* ---------------- Validation ---------------- */
if ($honeypot !== '') {
    echo json_encode(['status'=>'error','message'=>'Invalid submission.']); exit;
}
if ($name === '' || $email === '' || $message === '') {
    echo json_encode(['status'=>'error','message'=>'Please fill in all required fields.']); exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status'=>'error','message'=>'Invalid email address.']); exit;
}
if (!$consent) {
    echo json_encode(['status'=>'error','message'=>'Please accept the Privacy Policy to continue.']); exit;
}
if ($phone && !preg_match('/^[0-9+\-\s()]{7,}$/', $phone)) {
    echo json_encode(['status'=>'error','message'=>'Please enter a valid phone number.']); exit;
}

/* ---------------- Insert into DB ----------------
   Table: contact_forms
   Columns used:
   name, email, phone, topic, order_number, message, consent, ip, user_agent, created_at
*/
$sql = "INSERT INTO contact_forms 
        (name, email, phone, topic, order_number, message, consent, ip, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare error: ".$conn->error);
    echo json_encode(['status'=>'error','message'=>'There was a problem processing your request.']); exit;
}
$stmt->bind_param("ssssssiss", $name, $email, $phone, $topic, $order_no, $message, $consent, $ip, $user_agent);
if (!$stmt->execute()) {
    error_log("Insert error: ".$stmt->error);
    echo json_encode(['status'=>'error','message'=>'There was a problem submitting your message. Please try again later.']); 
    $stmt->close(); $conn->close(); exit;
}
$stmt->close();

/* ---------------- Email — SMTP (Woodex) ---------------- */
$brand_name = 'Woodex';
$emailSettings = get_email_settings($conn);
$from_email = (string)($emailSettings['from_email'] ?? '');
$support_email = (string)($emailSettings['support_email'] ?? '');
$team_email = (string)($emailSettings['contact_team_email'] ?? '');
$base         = base_url();
$logo         = $base . 'assets/images/header-logo.png';

$userSubject = "Thanks for contacting {$brand_name}";
$userBody = <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>{$userSubject}</title></head>
<body style="margin:0;padding:0;background:#f8f8f8;font-family:Arial,sans-serif">
  <table width="100%" style="background:#f8f8f8;padding:20px 0;">
    <tr><td>
      <table width="600" align="center" style="background:#fff;border-radius:8px;overflow:hidden">
        <tr>
          <td style="background:#2B2B2B;text-align:center;padding:20px">
            <img src="{$logo}" alt="{$brand_name}" style="max-width:180px">
          </td>
        </tr>
        <tr>
          <td style="background:#f7f7f7;padding:24px 26px">
            <h1 style="font-size:20px;margin:0 0 10px;color:#333">Hello, {$name}!</h1>
            <p style="font-size:15px;color:#555;margin:0 0 10px">
              Thanks for reaching out to <strong>{$brand_name}</strong>. We’ve received your message and will respond within 24–48 hours.
            </p>
            <div style="margin:12px 0;padding:12px;background:#fff;border-left:3px solid #2B2B2B;color:#444">
              {$message}
            </div>
            <p style="font-size:13px;color:#666;margin:10px 0 0">
              Topic: <strong>{$topic}</strong><br>
              Order No: <strong>{$order_no}</strong><br>
              Phone: <strong>{$phone}</strong>
            </p>
          </td>
        </tr>
        <tr>
          <td style="background:#2B2B2B;text-align:center;padding:14px">
            <p style="margin:0;color:#ccc;font-size:12px">&copy; 2025 {$brand_name}. All rights reserved.</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body></html>
HTML;

$adminSubject = "New Contact Form — {$brand_name}";
$adminBody = 
"New message received:\n\n".
"Name: {$name}\n".
"Email: {$email}\n".
"Phone: {$phone}\n".
"Topic: {$topic}\n".
"Order No: {$order_no}\n".
"Consent: ".($consent ? 'Yes' : 'No')."\n".
"IP: {$ip}\n".
"UA: {$user_agent}\n\n".
"Message:\n{$message}\n";

function send_mail_html($toEmail, $toName, $subject, $html, $replyEmail, $replyName) {
    global $from_email;
    global $emailSettings;

    if (empty($emailSettings) || empty($emailSettings['enabled'])) return false;
    if (empty($emailSettings['smtp_host']) || empty($emailSettings['smtp_username']) || empty($emailSettings['smtp_password'])) return false;
    if (empty($from_email)) return false;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $emailSettings['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $emailSettings['smtp_username'];
        $mail->Password   = $emailSettings['smtp_password'];
        if (!empty($emailSettings['smtp_secure'])) {
            $mail->SMTPSecure = $emailSettings['smtp_secure'];
        }
        $mail->Port       = (int)($emailSettings['smtp_port'] ?? 465);

        $fromName = !empty($emailSettings['from_name']) ? $emailSettings['from_name'] : 'Woodex';
        $mail->setFrom($from_email, $fromName);
        $mail->addAddress($toEmail, $toName ?: $toEmail);
        if ($replyEmail) $mail->addReplyTo($replyEmail, $replyName ?: $replyEmail);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = strip_tags($html);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer error (HTML) to {$toEmail}: " . $mail->ErrorInfo);
        return false;
    }
}
function send_mail_text($toEmail, $subject, $text, $replyEmail='', $replyName='') {
    global $from_email;
    global $emailSettings;

    if (empty($emailSettings) || empty($emailSettings['enabled'])) return false;
    if (empty($emailSettings['smtp_host']) || empty($emailSettings['smtp_username']) || empty($emailSettings['smtp_password'])) return false;
    if (empty($from_email)) return false;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $emailSettings['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $emailSettings['smtp_username'];
        $mail->Password   = $emailSettings['smtp_password'];
        if (!empty($emailSettings['smtp_secure'])) {
            $mail->SMTPSecure = $emailSettings['smtp_secure'];
        }
        $mail->Port       = (int)($emailSettings['smtp_port'] ?? 465);

        $fromName = !empty($emailSettings['from_name']) ? $emailSettings['from_name'] : 'Woodex';
        $mail->setFrom($from_email, $fromName);
        $mail->addAddress($toEmail);
        if ($replyEmail) $mail->addReplyTo($replyEmail, $replyName ?: $replyEmail);

        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $text;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer error (TEXT) to {$toEmail}: " . $mail->ErrorInfo);
        return false;
    }
}

// Send user receipt
send_mail_html($email, $name, $userSubject, $userBody, $support_email, 'Support');
// Send team notification
if ($team_email) {
    send_mail_text($team_email, $adminSubject, $adminBody, $email, $name);
}

/* ---------------- Done ---------------- */
$conn->close();
echo json_encode(['status'=>'success','message'=>'Your message has been sent successfully!']);
