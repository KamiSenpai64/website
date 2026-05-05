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
require_once __DIR__ . '/VideosAPI.php';

try {
    $videosAPI = new VideosAPI();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGet($videosAPI);
            break;
        case 'POST':
            handlePost($videosAPI);
            break;
        case 'PUT':
            handlePut($videosAPI);
            break;
        case 'DELETE':
            handleDelete($videosAPI);
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

function handleGet(VideosAPI $videosAPI): void
{
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            $filters = [];
            
            if (!empty($_GET['zodiac_sign'])) {
                $filters['zodiac_sign'] = $_GET['zodiac_sign'];
            }
            
            if (!empty($_GET['video_type'])) {
                $filters['video_type'] = $_GET['video_type'];
            }
            
            if (isset($_GET['featured'])) {
                $filters['featured'] = $_GET['featured'] === 'true';
            }
            
            if (!empty($_GET['limit'])) {
                $filters['limit'] = (int)$_GET['limit'];
            }
            
            if (!empty($_GET['offset'])) {
                $filters['offset'] = (int)$_GET['offset'];
            }
            
            $videos = $videosAPI->getVideos($filters);
            echo json_encode(['success' => true, 'data' => $videos]);
            break;
            
        case 'featured':
            $limit = !empty($_GET['limit']) ? (int)$_GET['limit'] : 5;
            $videos = $videosAPI->getFeaturedVideos($limit);
            echo json_encode(['success' => true, 'data' => $videos]);
            break;
            
        case 'zodiac':
            $zodiacSign = $_GET['sign'] ?? '';
            if (empty($zodiacSign)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Zodiac sign is required']);
                return;
            }
            
            $limit = !empty($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $videos = $videosAPI->getVideosByZodiac($zodiacSign, $limit);
            echo json_encode(['success' => true, 'data' => $videos]);
            break;
            
        case 'latest':
            $limit = !empty($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $videos = $videosAPI->getLatestVideos($limit);
            echo json_encode(['success' => true, 'data' => $videos]);
            break;
            
        case 'single':
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Video ID is required']);
                return;
            }
            
            $video = $videosAPI->getVideoById((int)$id);
            if ($video) {
                echo json_encode(['success' => true, 'data' => $video]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Video not found']);
            }
            break;
            
        case 'stats':
            $stats = $videosAPI->getVideoStats();
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
}

function handlePost(VideosAPI $videosAPI): void
{
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);
    
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
        return;
    }
    
    // Validate required fields
    if (empty($data['title']) || empty($data['link']) || empty($data['published_date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields: title, link, published_date']);
        return;
    }
    
    try {
        $id = $videosAPI->addVideo($data);
        echo json_encode(['success' => true, 'data' => ['id' => $id]]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handlePut(VideosAPI $videosAPI): void
{
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Video ID is required']);
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
        $success = $videosAPI->updateVideo((int)$id, $data);
        if ($success) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Video not found or no changes made']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handleDelete(VideosAPI $videosAPI): void
{
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Video ID is required']);
        return;
    }
    
    try {
        $success = $videosAPI->deleteVideo((int)$id);
        if ($success) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Video not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
