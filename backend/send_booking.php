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

use PHPMailer\PHPMailer\Exception;
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

$subject = 'Confirmare programare';
$body = "Buna, {$safeName},\r\n\r\n";
$body .= "Iti multumesc pentru programare. Cererea ta a fost inregistrata cu succes.\r\n\r\n";
$body .= "Detalii programare:\r\n";
$body .= "- Nume: {$safeName}\r\n";
$body .= "- Email: {$safeEmail}\r\n";
$body .= "- Ora preferata: {$safeTime}\r\n\r\n";

if ($safeNotes !== '') {
    $body .= "Intrebarea / intentia ta:\r\n";
    $body .= "{$safeNotes}\r\n\r\n";
}

$body .= "Voi reveni in curand cu confirmarea finala si toate detaliile necesare.\r\n\r\n";
$body .= "Multumesc,\r\n";
$body .= "[Numele tau / Brand]\r\n\r\n";
$body .= "---\r\n";
$body .= "Acesta este un email automat. Te rugam sa nu raspunzi direct.\r\n";

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
    $mailer->Body = $body;
    $mailer->isHTML(false);

    $mailer->send();
} catch (Exception $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Emailul nu a putut fi trimis: ' . $exception->getMessage()
    ]);
    exit;
}

echo json_encode([
    'success' => true
]);
