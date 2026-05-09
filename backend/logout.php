<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metoda nepermisa.']);
    exit;
}

Auth::logout();
echo json_encode(['success' => true, 'message' => 'Delogat cu succes.']);
