<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

// Load environment variables
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

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/TarotAPI.php';

try {
    $tarotAPI = new TarotAPI();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGet($tarotAPI);
            break;
        case 'POST':
            handlePost($tarotAPI);
            break;
        case 'PUT':
            handlePut($tarotAPI);
            break;
        case 'DELETE':
            handleDelete($tarotAPI);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function handleGet(TarotAPI $tarotAPI): void
{
    $action = $_GET['action'] ?? 'cards';
    
    switch ($action) {
        case 'cards':
            $cards = $tarotAPI->getAllCards();
            echo json_encode(['success' => true, 'data' => $cards]);
            break;
            
        case 'random':
            $card = $tarotAPI->getRandomCard();
            echo json_encode(['success' => true, 'data' => $card]);
            break;
            
        case 'daily':
            $dailyMessage = $tarotAPI->getDailyMessage();
            if ($dailyMessage) {
                echo json_encode(['success' => true, 'data' => $dailyMessage]);
            } else {
                // If no daily message set, return a random card
                $card = $tarotAPI->getRandomCard();
                echo json_encode(['success' => true, 'data' => $card]);
            }
            break;
            
        case 'single':
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Card ID is required']);
                return;
            }
            
            $card = $tarotAPI->getCardById((int)$id);
            if ($card) {
                echo json_encode(['success' => true, 'data' => $card]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Card not found']);
            }
            break;
            
        case 'element':
            $element = $_GET['element'] ?? '';
            if (empty($element)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Element is required']);
                return;
            }
            
            $cards = $tarotAPI->getCardsByElement($element);
            echo json_encode(['success' => true, 'data' => $cards]);
            break;
            
        case 'type':
            $type = $_GET['type'] ?? '';
            if (empty($type)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Card type is required']);
                return;
            }
            
            $cards = $tarotAPI->getCardsByType($type);
            echo json_encode(['success' => true, 'data' => $cards]);
            break;
            
        case 'history':
            $limit = !empty($_GET['limit']) ? (int)$_GET['limit'] : 30;
            $offset = !empty($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $history = $tarotAPI->getDailyMessagesHistory($limit, $offset);
            echo json_encode(['success' => true, 'data' => $history]);
            break;
            
        case 'stats':
            $stats = $tarotAPI->getTarotStats();
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
}

function handlePost(TarotAPI $tarotAPI): void
{
    $action = $_GET['action'] ?? 'card';
    
    switch ($action) {
        case 'card':
            $rawBody = file_get_contents('php://input');
            $data = json_decode($rawBody, true);
            
            if (!is_array($data)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
                return;
            }
            
            // Validate required fields
            if (empty($data['name']) || empty($data['description']) || empty($data['image_filename'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields: name, description, image_filename']);
                return;
            }
            
            try {
                $id = $tarotAPI->addCard($data);
                echo json_encode(['success' => true, 'data' => ['id' => $id]]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'daily':
            $rawBody = file_get_contents('php://input');
            $data = json_decode($rawBody, true);
            
            if (!is_array($data)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
                return;
            }
            
            if (empty($data['card_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Card ID is required']);
                return;
            }
            
            try {
                $success = $tarotAPI->setDailyMessage((int)$data['card_id'], $data['custom_message'] ?? null);
                if ($success) {
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Failed to set daily message']);
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
}

function handlePut(TarotAPI $tarotAPI): void
{
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Card ID is required']);
        return;
    }
    
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);
    
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
        return;
    }
    
    try {
        $success = $tarotAPI->updateCard((int)$id, $data);
        if ($success) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Card not found or no changes made']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handleDelete(TarotAPI $tarotAPI): void
{
    // Note: We don't implement delete for tarot cards as they are core data
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Delete operation not allowed for tarot cards']);
}
