<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/BookingsAPI.php';
require_once __DIR__ . '/AccountsAPI.php';

// Require admin authentication
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metoda nepermisa.']);
    exit;
}

try {
    $bookingsApi = new BookingsAPI();
    $accountsApi = new AccountsAPI();
    
    // Get booking statistics
    $bookingStats = $bookingsApi->getBookingStats();
    
    // Get accounts count
    $accounts = $accountsApi->getAccounts();
    $totalAccounts = count($accounts);
    
    // Combine statistics
    $stats = [
        'total_accounts' => $totalAccounts,
        'total_bookings' => $bookingStats['total_bookings'] ?? 0,
        'pending_bookings' => $bookingStats['pending'] ?? 0,
        'confirmed_bookings' => 0, // Can be calculated from by_status
        'this_month_bookings' => $bookingStats['this_month'] ?? 0,
        'today_bookings' => $bookingStats['today'] ?? 0
    ];
    
    // Calculate confirmed bookings from by_status array
    if (isset($bookingStats['by_status']) && is_array($bookingStats['by_status'])) {
        foreach ($bookingStats['by_status'] as $status) {
            if ($status['status'] === 'confirmed') {
                $stats['confirmed_bookings'] = (int)$status['count'];
                break;
            }
        }
    }
    
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
