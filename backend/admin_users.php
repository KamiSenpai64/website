<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/AccountsAPI.php';
require_once __DIR__ . '/Auth.php';

Auth::requireAdmin();
$accountsApi = new AccountsAPI();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $accounts = $accountsApi->getAccounts();
    echo json_encode(['success' => true, 'accounts' => $accounts]);
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

$action = trim((string)($data['action'] ?? ''));

switch ($action) {
    case 'create':
        $email = trim((string)($data['email'] ?? ''));
        $password = trim((string)($data['password'] ?? ''));
        $name = trim((string)($data['name'] ?? ''));
        $role = trim((string)($data['role'] ?? 'user'));

        if ($email === '' || $password === '' || $name === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Email, parola si numele sunt obligatorii.']);
            exit;
        }

        try {
            $id = $accountsApi->createAccount([
                'email' => $email,
                'password' => $password,
                'name' => $name,
                'role' => $role,
                'status' => 'active'
            ]);
            echo json_encode(['success' => true, 'id' => $id]);
        } catch (Exception $exception) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Nu s-a putut crea contul.']);
        }
        break;

    case 'update':
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'ID cont invalid.']);
            exit;
        }

        $fields = [];
        foreach (['email', 'name', 'role', 'status', 'password'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Nicio valoare pentru actualizare.']);
            exit;
        }

        $updated = $accountsApi->updateAccount($id, $fields);
        echo json_encode(['success' => $updated]);
        break;

    case 'delete':
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'ID cont invalid.']);
            exit;
        }

        $deleted = $accountsApi->deleteAccount($id);
        echo json_encode(['success' => $deleted]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Actiune necunoscuta.']);
        break;
}
