<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AccountsAPI.php';

// Require user login
Auth::requireLogin();

$accountsApi = new AccountsAPI();
$currentUser = Auth::currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return current user profile
    echo json_encode([
        'success' => true,
        'user' => $currentUser
    ]);
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

$name = trim((string)($data['name'] ?? ''));
$email = trim((string)($data['email'] ?? ''));

if ($name === '' || $email === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Numele și emailul sunt obligatorii.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Adresa de email este invalidă.']);
    exit;
}

try {
    // Check if email is already taken by another user
    $existingAccount = $accountsApi->getAccountByEmail($email);
    if ($existingAccount && $existingAccount['id'] !== $currentUser['id']) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Această adresă de email este deja folosită de alt cont.']);
        exit;
    }
    
    $updated = $accountsApi->updateAccount($currentUser['id'], [
        'name' => $name,
        'email' => $email
    ]);
    
    if ($updated) {
        echo json_encode([
            'success' => true,
            'message' => 'Profil actualizat cu succes.',
            'user' => [
                'id' => $currentUser['id'],
                'name' => $name,
                'email' => $email,
                'role' => $currentUser['role'],
                'status' => $currentUser['status'],
                'created_at' => $currentUser['created_at']
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Nu s-a putut actualiza profilul.']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Eroare server: ' . $e->getMessage()
    ]);
}
?>
