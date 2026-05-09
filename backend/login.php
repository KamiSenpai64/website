<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/AccountsAPI.php';
require_once __DIR__ . '/Auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = Auth::currentUser();
    echo json_encode(['success' => true, 'user' => $user]);
    exit;
}

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

$email = trim((string)($data['email'] ?? ''));
$password = trim((string)($data['password'] ?? ''));

if ($email === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Email si parola sunt obligatorii.']);
    exit;
}

$accountsApi = new AccountsAPI();
$account = $accountsApi->verifyCredentials($email, $password);

if ($account === null) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Datele de autentificare sunt incorecte.']);
    exit;
}

Auth::login($account);

echo json_encode([
    'success' => true,
    'user' => [
        'id' => $account['id'],
        'email' => $account['email'],
        'name' => $account['name'],
        'role' => $account['role']
    ]
]);
