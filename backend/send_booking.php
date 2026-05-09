<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

function loadEnvFile(string $filePath): void
{
    if (!file_exists($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $parts = explode('=', $trimmed, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

loadEnvFile(__DIR__ . '/.env');

if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Dependintele lipsesc. Ruleaza "composer install".'
    ]);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/BookingsAPI.php';

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Metoda nepermisa.'
    ]);
    exit;
}

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Date invalide.'
    ]);
    exit;
}

$name = trim((string)($data['name'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$preferredTime = trim((string)($data['preferredTime'] ?? ''));
$notes = trim((string)($data['notes'] ?? ''));

if ($name === '' || $email === '' || $preferredTime === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Campurile obligatorii lipsesc.'
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Adresa de email este invalida.'
    ]);
    exit;
}

$smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
$smtpPort = (int)(getenv('SMTP_PORT') ?: '587');
$smtpUser = getenv('SMTP_USERNAME') ?: 'miuletdaniel@gmail.com';
$smtpPass = getenv('SMTP_PASSWORD') ?: '';
$smtpEncryption = getenv('SMTP_ENCRYPTION') ?: 'tls';
$fromEmail = getenv('SMTP_FROM_EMAIL') ?: 'miuletdaniel@gmail.com';
$fromName = getenv('SMTP_FROM_NAME') ?: 'Astro Tarot';
$bccEmail = getenv('SMTP_BCC_EMAIL') ?: 'miuletdaniel@gmail.com';

if ($smtpPass === '') {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Lipseste SMTP_PASSWORD. Configureaza variabilele de mediu.'
    ]);
    exit;
}

$safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeEmail = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeTime = htmlspecialchars($preferredTime, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeNotes = htmlspecialchars($notes, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$bookingId = null;
try {
    $bookingsApi = new BookingsAPI();
    $bookingId = $bookingsApi->createBooking([
        'name' => $name,
        'email' => $email,
        'preferred_time' => $preferredTime,
        'notes' => $notes,
        'status' => 'pending',
        'consultation_date' => null
    ]);
} catch (\Exception $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Nu s-a putut salva rezervarea. Incearca din nou mai tarziu.'
    ]);
    exit;
}

$subject = 'Confirmare rezervare Astro Tarot';
$htmlBody = '<!DOCTYPE html>' .
    '<html lang="ro">' .
    '<head>' .
    '<meta charset="UTF-8">' .
    '<title>Confirmare rezervare</title>' .
    '<style>' .
    'body { font-family: Arial, sans-serif; color: #333; background: #f3f0eb; margin: 0; padding: 0; }' .
    '.container { max-width: 620px; margin: 0 auto; padding: 24px; }' .
    '.card { background: #ffffff; border-radius: 18px; box-shadow: 0 16px 32px rgba(0,0,0,0.08); overflow: hidden; }' .
    '.card-header { background: #5f2b8a; color: #fff; padding: 28px 24px; text-align: center; }' .
    '.card-body { padding: 24px; }' .
    '.details { width: 100%; border-collapse: collapse; margin-top: 18px; }' .
    '.details td { padding: 10px 0; vertical-align: top; }' .
    '.details .label { color: #6c4c86; width: 38%; font-weight: 700; }' .
    '.details .value { color: #444; }' .
    '.footer { margin-top: 24px; font-size: 0.95rem; color: #6e6b70; }' .
    '</style>' .
    '</head>' .
    '<body>' .
    '<div class="container">' .
    '<div class="card">' .
    '<div class="card-header">' .
    '<h1>Rezervare primită</h1>' .
    '<p>Mulțumim pentru încredere, ' . $safeName . '.</p>' .
    '</div>' .
    '<div class="card-body">' .
    '<p>Am înregistrat cererea ta de programare. Vei primi un email de confirmare finală în curând.</p>' .
    '<table class="details">' .
    '<tr><td class="label">ID Rezervare</td><td class="value">' . htmlspecialchars((string)$bookingId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td></tr>' .
    '<tr><td class="label">Nume</td><td class="value">' . $safeName . '</td></tr>' .
    '<tr><td class="label">Email</td><td class="value">' . $safeEmail . '</td></tr>' .
    '<tr><td class="label">Ora preferată</td><td class="value">' . $safeTime . '</td></tr>' .
    '<tr><td class="label">Status</td><td class="value">Pending</td></tr>' .
    '</table>' .
    ($safeNotes !== '' ? '<p><strong>Întrebare / intenție:</strong><br>' . nl2br($safeNotes) . '</p>' : '') .
    '<div class="footer">' .
    '<p>Pentru orice întrebări, răspunde la acest email sau așteaptă confirmarea noastră.</p>' .
    '<p>Cu drag,<br>Astro Tarot</p>' .
    '</div>' .
    '</div>' .
    '</div>' .
    '</div>' .
    '</body>' .
    '</html>';

$textBody = "Buna, {$safeName},\r\n\r\n";
$textBody .= "Am primit cererea ta de programare si am inregistrat detaliile.\r\n\r\n";
$textBody .= "ID Rezervare: {$bookingId}\r\n";
$textBody .= "Nume: {$safeName}\r\n";
$textBody .= "Email: {$safeEmail}\r\n";
$textBody .= "Ora preferata: {$safeTime}\r\n";
if ($safeNotes !== '') {
    $textBody .= "Intrebarea / intentia ta: {$safeNotes}\r\n";
}
$textBody .= "\r\nVoi reveni curand cu confirmarea finala.\r\n\r\n";
$textBody .= "Cu drag,\r\nAstro Tarot\r\n";
$textBody .= "---\r\nAcesta este un email automat. Te rugam sa nu raspunzi direct.\r\n";

try {
    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $smtpHost;
    $mailer->SMTPAuth = true;
    $mailer->Username = $smtpUser;
    $mailer->Password = $smtpPass;
    $mailer->Port = $smtpPort;

    if ($smtpEncryption === 'ssl') {
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mailer->CharSet = 'UTF-8';
    $mailer->setFrom($fromEmail, $fromName);
    $mailer->addAddress($email, $name);
    if ($bccEmail !== '') {
        $mailer->addBCC($bccEmail);
    }
    $mailer->addReplyTo($fromEmail, $fromName);
    $mailer->Subject = $subject;
    $mailer->Body = $htmlBody;
    $mailer->AltBody = $textBody;
    $mailer->isHTML(true);

    $mailer->send();
} catch (PHPMailerException $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Emailul nu a putut fi trimis: ' . $exception->getMessage()
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'bookingId' => $bookingId
]);
