<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/ReviewsAPI.php';
require_once __DIR__ . '/BookingsAPI.php';

// Require user login
Auth::requireLogin();

$reviewsApi = new ReviewsAPI();
$bookingsApi = new BookingsAPI();
$currentUser = Auth::currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $reviews = $reviewsApi->getReviewsByUserId($currentUser['id']);
        echo json_encode([
            'success' => true,
            'reviews' => $reviews
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

$bookingId = (int)($data['booking_id'] ?? 0);
$rating = (int)($data['rating'] ?? 0);
$reviewText = trim((string)($data['review_text'] ?? ''));

if ($bookingId <= 0 || $rating < 1 || $rating > 5) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Date invalide.']);
    exit;
}

try {
    // Verify booking belongs to current user and is confirmed
    $booking = $bookingsApi->getBookingById($bookingId);
    if (!$booking || $booking['user_id'] !== $currentUser['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Nu ai permisiunea de a adăuga recenzie pentru această rezervare.']);
        exit;
    }
    
    if ($booking['status'] !== 'confirmed') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Poți lăsa recenzii doar pentru rezervări confirmate.']);
        exit;
    }
    
    // Check if review already exists
    $existingReviews = $reviewsApi->getReviewsByBookingId($bookingId);
    if (!empty($existingReviews)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Ai lăsat deja o recenzie pentru această rezervare.']);
        exit;
    }
    
    // Create review
    $reviewId = $reviewsApi->createReview([
        'booking_id' => $bookingId,
        'user_id' => $currentUser['id'],
        'rating' => $rating,
        'review_text' => $reviewText
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Recenzia a fost salvată cu succes.',
        'review_id' => $reviewId
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Eroare server: ' . $e->getMessage()
    ]);
}
?>
