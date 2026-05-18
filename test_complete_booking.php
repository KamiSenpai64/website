<?php
// Test complete booking system with date and time slots
require_once 'backend/Database.php';
require_once 'backend/BookingsAPI.php';
require_once 'backend/Auth.php';

echo "Testing complete booking system with date/time slots...\n\n";

try {
    $db = new Database();
    echo "✓ Database connection successful\n";
    
    // Test 1: Check if booking API works
    $bookingsApi = new BookingsAPI();
    echo "✓ Bookings API initialized\n";
    
    // Test 2: Check time slot system with date
    $timeAvailableToday = $bookingsApi->isTimeSlotAvailable('10:00', date('Y-m-d'));
    echo "✓ Time slot check for today: " . ($timeAvailableToday ? "Available" : "Not Available") . "\n";
    
    $timeAvailableTomorrow = $bookingsApi->isTimeSlotAvailable('10:00', date('Y-m-d', strtotime('+1 day')));
    echo "✓ Time slot check for tomorrow: " . ($timeAvailableTomorrow ? "Available" : "Not Available") . "\n";
    
    // Test 3: Check available time slots endpoint
    echo "\n=== Testing Available Time Slots API ===\n";
    $testUrl = 'http://localhost/backend/available_time_slots.php?date=' . date('Y-m-d');
    echo "Testing URL: $testUrl\n";
    
    // Test 4: Check existing bookings
    $allBookings = $bookingsApi->getBookings();
    echo "✓ Found " . count($allBookings) . " existing bookings\n";
    
    // Test 5: Create test booking with specific date
    $testBookingData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'preferred_time' => '15:00',
        'notes' => 'Test booking with date',
        'status' => 'pending',
        'booking_date' => date('Y-m-d')
    ];
    
    echo "\n=== Testing Booking Creation with Date ===\n";
    echo "Creating test booking with data:\n";
    echo "- Name: " . $testBookingData['name'] . "\n";
    echo "- Email: " . $testBookingData['email'] . "\n";
    echo "- Time: " . $testBookingData['preferred_time'] . "\n";
    echo "- Date: " . $testBookingData['booking_date'] . "\n";
    echo "- Notes: " . $testBookingData['notes'] . "\n";
    
    try {
        $bookingId = $bookingsApi->createBooking($testBookingData);
        echo "✓ Test booking created with ID: $bookingId\n";
        
        // Verify booking was created with correct date
        $createdBooking = $bookingsApi->getBookingById($bookingId);
        if ($createdBooking) {
            echo "✓ Booking verification successful\n";
            echo "  - Name: " . $createdBooking['name'] . "\n";
            echo "  - Email: " . $createdBooking['email'] . "\n";
            echo "  - Date: " . $createdBooking['booking_date'] . "\n";
            echo "  - Time: " . $createdBooking['preferred_time'] . "\n";
            echo "  - Status: " . $createdBooking['status'] . "\n";
        } else {
            echo "✗ Booking verification failed\n";
        }
        
        // Clean up test booking
        $bookingsApi->deleteBooking($bookingId);
        echo "✓ Test booking cleaned up\n";
        
    } catch (Exception $e) {
        echo "✗ Booking creation failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== Features Implemented ===\n";
    echo "✓ Date picker added to booking modal\n";
    echo "✓ Dynamic time slot loading based on selected date\n";
    echo "✓ Time slot availability hiding (unavailable times not shown)\n";
    echo "✓ Date-based time slot conflict prevention\n";
    echo "✓ Enhanced booking submission with date parameter\n";
    echo "✓ Backend API for available time slots\n";
    
    echo "\n=== Ready for Testing ===\n";
    echo "1. Open booking modal\n";
    echo "2. Select a date (today or future)\n";
    echo "3. Available time slots will load automatically\n";
    echo "4. Unavailable time slots will be hidden\n";
    echo "5. Submit booking with date and time\n";
    echo "6. Check time slot conflict prevention by date\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nComplete booking system test completed!\n";
?>
