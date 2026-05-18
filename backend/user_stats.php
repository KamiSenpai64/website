<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/BookingsAPI.php';
require_once __DIR__ . '/ReviewsAPI.php';

// Require user login
Auth::requireLogin();

$bookingsApi = new BookingsAPI();
$reviewsApi = new ReviewsAPI();
$currentUser = Auth::currentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metoda nepermisa.']);
    exit;
}

try {
    // Get user's bookings
    $allBookings = $bookingsApi->getBookingsByUserId($currentUser['id']);
    $currentBookings = $bookingsApi->getCurrentAndFutureBookingsByUserId($currentUser['id']);
    $pastBookings = $bookingsApi->getPastBookingsByUserId($currentUser['id']);
    
    // Count bookings by status
    $totalBookings = count($allBookings);
    $currentBookingsCount = count($currentBookings);
    $completedBookingsCount = 0;
    
    foreach ($allBookings as $booking) {
        if ($booking['status'] === 'confirmed') {
            $completedBookingsCount++;
        }
    }
    
    // Get user's reviews
    $reviews = $reviewsApi->getReviewsByUserId($currentUser['id']);
    $reviewsCount = count($reviews);
    
    $stats = [
        'total_bookings' => $totalBookings,
        'current_bookings' => $currentBookingsCount,
        'completed_bookings' => $completedBookingsCount,
        'reviews_count' => $reviewsCount,
        'pending_bookings' => $totalBookings - $completedBookingsCount
    ];
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Eroare server: ' . $e->getMessage()
    ]);
}
?>
