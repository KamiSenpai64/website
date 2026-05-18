<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/AccountsAPI.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metoda nepermisa.']);
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Date invalide.']);
    exit;
}

$name = trim((string)($data['name'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$password = trim((string)($data['password'] ?? ''));

if ($name === '' || $email === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Toate câmpurile sunt obligatorii.']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Parola trebuie să aibă cel puțin 6 caractere.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Adresa de email este invalidă.']);
    exit;
}

$accountsApi = new AccountsAPI();

// Check if email already exists
$existingAccount = $accountsApi->getAccountByEmail($email);
if ($existingAccount !== null) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'Există deja un cont cu această adresă de email.']);
    exit;
}

try {
    $accountId = $accountsApi->createAccount([
        'email' => $email,
        'password' => $password,
        'name' => $name,
        'role' => 'user',
        'status' => 'active'
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Cont creat cu succes.',
        'account_id' => $accountId
    ]);
} catch (Exception $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Nu s-a putut crea contul. Încearcă din nou mai târziu.'
    ]);
}
?>
