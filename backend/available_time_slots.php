<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/BookingsAPI.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metoda nepermisa.']);
    exit;
}

$date = $_GET['date'] ?? '';

if (empty($date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Data este obligatorie.']);
    exit;
}

try {
    $bookingsApi = new BookingsAPI();
    
    // Generate all possible time slots
    $allTimeSlots = [
        '09:00', '09:30', '10:00', '10:30',
        '11:00', '11:30', '12:00', '12:30',
        '13:00', '13:30', '14:00', '14:30',
        '15:00', '15:30', '16:00', '16:30',
        '17:00', '17:30', '18:00', '18:30',
        '19:00', '19:30', '20:00', '20:30'
    ];
    
    $availableTimes = [];
    
    foreach ($allTimeSlots as $timeSlot) {
        // Check if this time slot is available for the given date
        if ($bookingsApi->isTimeSlotAvailable($timeSlot, $date)) {
            $availableTimes[] = $timeSlot;
        }
    }
    
    echo json_encode([
        'success' => true,
        'date' => $date,
        'available_times' => $availableTimes
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Eroare server: ' . $e->getMessage()
    ]);
}
?>
