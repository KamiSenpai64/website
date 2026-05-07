<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

/**
 * Bookings API class for managing consultation bookings in MariaDB
 */
class BookingsAPI
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /**
     * Create a new booking
     * @param array $bookingData Booking information
     * @return int ID of created booking
     */
    public function createBooking(array $bookingData): int
    {
        $sql = "INSERT INTO bookings (name, email, preferred_time, notes, status, consultation_date) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $params = [
            $bookingData['name'],
            $bookingData['email'],
            $bookingData['preferred_time'],
            $bookingData['notes'] ?? null,
            $bookingData['status'] ?? 'pending',
            $bookingData['consultation_date'] ?? null
        ];

        $this->db->execute($sql, $params);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Get booking by ID
     * @param int $id Booking ID
     * @return array|null Booking data or null if not found
     */
    public function getBookingById(int $id): ?array
    {
        $sql = "SELECT * FROM bookings WHERE id = ?";
        $result = $this->db->query($sql, [$id]);
        return $result[0] ?? null;
    }

    /**
     * Get all bookings with optional filtering
     * @param array $filters Filter options (status, email, date_from, date_to, limit, offset)
     * @return array Bookings data
     */
    public function getBookings(array $filters = []): array
    {
        $sql = "SELECT id, name, email, preferred_time, notes, status, 
                       consultation_date, booking_date, created_at, updated_at
                FROM bookings WHERE 1=1";
        $params = [];

        // Add filters
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['email'])) {
            $sql .= " AND email = ?";
            $params[] = $filters['email'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND booking_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND booking_date <= ?";
            $params[] = $filters['date_to'];
        }

        // Default ordering
        $sql .= " ORDER BY booking_date DESC";

        // Add pagination
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];

            if (!empty($filters['offset'])) {
                $sql .= " OFFSET ?";
                $params[] = (int)$filters['offset'];
            }
        }

        return $this->db->query($sql, $params);
    }

    /**
     * Get bookings by email
     * @param string $email Customer email
     * @return array Customer's bookings
     */
    public function getBookingsByEmail(string $email): array
    {
        return $this->getBookings(['email' => $email]);
    }

    /**
     * Get bookings by status
     * @param string $status Booking status
     * @param int $limit Maximum number of bookings to return
     * @return array Bookings with specified status
     */
    public function getBookingsByStatus(string $status, int $limit = 50): array
    {
        return $this->getBookings(['status' => $status, 'limit' => $limit]);
    }

    /**
     * Get pending bookings
     * @param int $limit Maximum number of bookings to return
     * @return array Pending bookings
     */
    public function getPendingBookings(int $limit = 50): array
    {
        return $this->getBookings(['status' => 'pending', 'limit' => $limit]);
    }

    /**
     * Update booking status
     * @param int $id Booking ID
     * @param string $status New status
     * @param string|null $consultationDate Consultation date (optional)
     * @return bool True if update was successful
     */
    public function updateBookingStatus(int $id, string $status, ?string $consultationDate = null): bool
    {
        $sql = "UPDATE bookings SET status = ?, updated_at = CURRENT_TIMESTAMP";
        $params = [$status];

        if ($consultationDate) {
            $sql .= ", consultation_date = ?";
            $params[] = $consultationDate;
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        return $this->db->execute($sql, $params) > 0;
    }

    /**
     * Update booking details
     * @param int $id Booking ID
     * @param array $bookingData Updated booking information
     * @return bool True if update was successful
     */
    public function updateBooking(int $id, array $bookingData): bool
    {
        $sql = "UPDATE bookings SET ";
        $updates = [];
        $params = [];

        $fields = ['name', 'email', 'preferred_time', 'notes', 'status', 'consultation_date'];
        
        foreach ($fields as $field) {
            if (array_key_exists($field, $bookingData)) {
                $updates[] = "$field = ?";
                $params[] = $bookingData[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $sql .= implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $params[] = $id;

        return $this->db->execute($sql, $params) > 0;
    }

    /**
     * Delete booking
     * @param int $id Booking ID
     * @return bool True if deletion was successful
     */
    public function deleteBooking(int $id): bool
    {
        $sql = "DELETE FROM bookings WHERE id = ?";
        return $this->db->execute($sql, [$id]) > 0;
    }

    /**
     * Get bookings for a specific date range
     * @param string $dateFrom Start date (Y-m-d format)
     * @param string $dateTo End date (Y-m-d format)
     * @return array Bookings in date range
     */
    public function getBookingsByDateRange(string $dateFrom, string $dateTo): array
    {
        return $this->getBookings(['date_from' => $dateFrom, 'date_to' => $dateTo]);
    }

    /**
     * Get today's bookings
     * @return array Today's bookings
     */
    public function getTodayBookings(): array
    {
        $today = date('Y-m-d');
        $sql = "SELECT * FROM bookings 
                WHERE DATE(booking_date) = ? OR DATE(consultation_date) = ?
                ORDER BY booking_date ASC";
        
        return $this->db->query($sql, [$today, $today]);
    }

    /**
     * Get upcoming consultations
     * @param int $days Number of days ahead to look
     * @return array Upcoming consultations
     */
    public function getUpcomingConsultations(int $days = 7): array
    {
        $sql = "SELECT * FROM bookings 
                WHERE consultation_date IS NOT NULL 
                AND consultation_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
                AND status IN ('pending', 'confirmed')
                ORDER BY consultation_date ASC";
        
        return $this->db->query($sql, [$days]);
    }

    /**
     * Get booking statistics
     * @return array Booking statistics
     */
    public function getBookingStats(): array
    {
        $stats = [];

        // Total bookings
        $result = $this->db->query("SELECT COUNT(*) as total FROM bookings");
        $stats['total_bookings'] = (int)$result[0]['total'];

        // Bookings by status
        $result = $this->db->query("SELECT status, COUNT(*) as count FROM bookings GROUP BY status");
        $stats['by_status'] = $result;

        // Bookings this month
        $result = $this->db->query("SELECT COUNT(*) as count FROM bookings 
                                   WHERE MONTH(booking_date) = MONTH(CURDATE()) 
                                   AND YEAR(booking_date) = YEAR(CURDATE())");
        $stats['this_month'] = (int)$result[0]['count'];

        // Bookings today
        $result = $this->db->query("SELECT COUNT(*) as count FROM bookings 
                                   WHERE DATE(booking_date) = CURDATE()");
        $stats['today'] = (int)$result[0]['count'];

        // Pending bookings
        $result = $this->db->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'");
        $stats['pending'] = (int)$result[0]['count'];

        return $stats;
    }

    /**
     * Search bookings
     * @param string $searchTerm Search term (searches in name, email, notes)
     * @return array Matching bookings
     */
    public function searchBookings(string $searchTerm): array
    {
        $sql = "SELECT * FROM bookings 
                WHERE name LIKE ? OR email LIKE ? OR notes LIKE ?
                ORDER BY booking_date DESC";
        
        $searchPattern = "%" . $searchTerm . "%";
        return $this->db->query($sql, [$searchPattern, $searchPattern, $searchPattern]);
    }

    /**
     * Check if email has existing bookings
     * @param string $email Email to check
     * @return bool True if email has existing bookings
     */
    public function hasExistingBookings(string $email): bool
    {
        $sql = "SELECT COUNT(*) as count FROM bookings WHERE email = ?";
        $result = $this->db->query($sql, [$email]);
        return (int)$result[0]['count'] > 0;
    }
}
