<?php
// Test script for booking modal functionality
require_once 'backend/Database.php';
require_once 'backend/BookingsAPI.php';
require_once 'backend/Auth.php';

echo "Testing booking modal functionality...\n\n";

try {
    $db = new Database();
    echo "✓ Database connection successful\n";
    
    // Test 1: Check if booking system is working
    $bookingsApi = new BookingsAPI();
    echo "✓ Bookings API initialized\n";
    
    // Test 2: Check time slot system
    $timeAvailable = $bookingsApi->isTimeSlotAvailable('10:00');
    echo "✓ Time slot availability check: " . ($timeAvailable ? "Available" : "Not Available") . "\n";
    
    // Test 3: Check existing bookings
    $allBookings = $bookingsApi->getBookings();
    echo "✓ Found " . count($allBookings) . " existing bookings\n";
    
    // Test 4: Check database tables
    $tables = ['bookings', 'time_slots', 'accounts'];
    foreach ($tables as $table) {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if (!empty($result)) {
            echo "✓ Table '$table' exists\n";
        } else {
            echo "✗ Table '$table' missing\n";
        }
    }
    
    // Test 5: Check if send_booking.php exists and is accessible
    if (file_exists('backend/send_booking.php')) {
        echo "✓ send_booking.php exists\n";
    } else {
        echo "✗ send_booking.php missing\n";
    }
    
    echo "\n=== Booking Modal Issues ===\n";
    echo "Common issues to check:\n";
    echo "1. JavaScript errors in browser console\n";
    echo "2. Network connectivity issues\n";
    echo "3. Backend PHP errors\n";
    echo "4. Database connection problems\n";
    echo "5. Time slot conflicts\n";
    echo "6. Email sending failures\n";
    
    echo "\n=== Manual Testing Steps ===\n";
    echo "1. Open browser developer tools\n";
    echo "2. Try to open booking modal\n";
    echo "3. Fill form and submit\n";
    echo "4. Check browser network tab for errors\n";
    echo "5. Check backend logs for PHP errors\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nBooking modal test completed!\n";
?>
