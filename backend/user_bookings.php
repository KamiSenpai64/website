<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/BookingsAPI.php';

// Require user login
Auth::requireLogin();

$bookingsApi = new BookingsAPI();
$currentUser = Auth::currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $type = $_GET['type'] ?? 'current';
    
    try {
        $bookings = [];
        
        if ($type === 'current') {
            $bookings = $bookingsApi->getCurrentAndFutureBookingsByUserId($currentUser['id']);
        } elseif ($type === 'past') {
            $bookings = $bookingsApi->getPastBookingsByUserId($currentUser['id']);
        } elseif ($type === 'all') {
            $bookings = $bookingsApi->getBookingsByUserId($currentUser['id']);
        } else {
            $bookings = $bookingsApi->getBookingsByUserId($currentUser['id']);
        }
        
        // Check if reviews exist for past bookings
        foreach ($bookings as &$booking) {
            $booking['has_review'] = false;
            if ($booking['status'] === 'confirmed') {
                // Check if review exists for this booking
                $result = $bookingsApi->db->query(
                    "SELECT COUNT(*) as count FROM reviews WHERE booking_id = ? AND user_id = ?",
                    [$booking['id'], $currentUser['id']]
                );
                $booking['has_review'] = (int)$result[0]['count'] > 0;
            }
        }
        
        echo json_encode([
            'success' => true,
            'bookings' => $bookings
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Eroare server: ' . $e->getMessage()
        ]);
    }
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
$bookingId = (int)($data['booking_id'] ?? 0);

if ($bookingId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'ID rezervare invalid.']);
    exit;
}

try {
    // Verify booking belongs to current user
    $booking = $bookingsApi->getBookingById($bookingId);
    if (!$booking || $booking['user_id'] !== $currentUser['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Nu ai permisiunea de a modifica această rezervare.']);
        exit;
    }
    
    switch ($action) {
        case 'cancel':
            $updated = $bookingsApi->updateBooking($bookingId, ['status' => 'cancelled']);
            echo json_encode(['success' => $updated]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Actiune necunoscută.']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Eroare server: ' . $e->getMessage()
    ]);
}
?>
