<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/BookingsAPI.php';

// Require admin authentication
Auth::requireAdmin();

try {
    $bookingsApi = new BookingsAPI();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get all bookings with optional filters
        $filters = [];
        
        // Optional status filter
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        
        // Optional date range filters
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = $_GET['date_from'];
        }
        
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = $_GET['date_to'];
        }
        
        // Optional limit for pagination
        if (!empty($_GET['limit'])) {
            $filters['limit'] = (int)$_GET['limit'];
        }
        
        if (!empty($_GET['offset'])) {
            $filters['offset'] = (int)$_GET['offset'];
        }
        
        $bookings = $bookingsApi->getBookings($filters);
        
        echo json_encode([
            'success' => true,
            'bookings' => $bookings
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $action = trim((string)($data['action'] ?? ''));
        $bookingId = (int)($data['booking_id'] ?? 0);

        if ($bookingId <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'ID rezervare invalid.']);
            exit;
        }

        // Verify booking exists
        $booking = $bookingsApi->getBookingById($bookingId);
        if (!$booking) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Rezervarea nu există.']);
            exit;
        }
        
        switch ($action) {
            case 'update':
                $updated = $bookingsApi->updateBooking($bookingId, [
                    'status' => $data['status'] ?? 'pending',
                    'consultation_date' => $data['consultation_date'] ?? null
                ]);
                echo json_encode(['success' => $updated]);
                break;
                
            case 'delete':
                $deleted = $bookingsApi->deleteBooking($bookingId);
                echo json_encode(['success' => $deleted]);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Actiune necunoscută.']);
                break;
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Metoda nepermisa.']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Eroare server: ' . $e->getMessage()
    ]);
}
?>
