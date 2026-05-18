<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

/**
 * Reviews API class for managing user reviews
 */
class ReviewsAPI
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /**
     * Create a new review
     * @param array $reviewData Review information
     * @return int ID of created review
     */
    public function createReview(array $reviewData): int
    {
        $sql = "INSERT INTO reviews (booking_id, user_id, rating, review_text) 
                VALUES (?, ?, ?, ?)";
        
        $params = [
            $reviewData['booking_id'],
            $reviewData['user_id'],
            $reviewData['rating'],
            $reviewData['review_text'] ?? null
        ];

        $this->db->execute($sql, $params);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Get review by ID
     * @param int $id Review ID
     * @return array|null Review data or null if not found
     */
    public function getReviewById(int $id): ?array
    {
        $sql = "SELECT r.*, b.name as booking_name, u.name as user_name 
                FROM reviews r 
                LEFT JOIN bookings b ON r.booking_id = b.id 
                LEFT JOIN accounts u ON r.user_id = u.id 
                WHERE r.id = ?";
        $result = $this->db->query($sql, [$id]);
        return $result[0] ?? null;
    }

    /**
     * Get reviews by booking ID
     * @param int $bookingId Booking ID
     * @return array Reviews for the booking
     */
    public function getReviewsByBookingId(int $bookingId): array
    {
        $sql = "SELECT r.*, u.name as user_name 
                FROM reviews r 
                LEFT JOIN accounts u ON r.user_id = u.id 
                WHERE r.booking_id = ? 
                ORDER BY r.created_at DESC";
        return $this->db->query($sql, [$bookingId]);
    }

    /**
     * Get reviews by user ID
     * @param int $userId User ID
     * @return array Reviews by the user
     */
    public function getReviewsByUserId(int $userId): array
    {
        $sql = "SELECT r.*, b.name as booking_name 
                FROM reviews r 
                LEFT JOIN bookings b ON r.booking_id = b.id 
                WHERE r.user_id = ? 
                ORDER BY r.created_at DESC";
        return $this->db->query($sql, [$userId]);
    }

    /**
     * Get all reviews with optional filtering
     * @param array $filters Filter options
     * @return array Reviews data
     */
    public function getReviews(array $filters = []): array
    {
        $sql = "SELECT r.*, b.name as booking_name, u.name as user_name 
                FROM reviews r 
                LEFT JOIN bookings b ON r.booking_id = b.id 
                LEFT JOIN accounts u ON r.user_id = u.id 
                WHERE 1=1";
        $params = [];

        // Add filters
        if (!empty($filters['booking_id'])) {
            $sql .= " AND r.booking_id = ?";
            $params[] = $filters['booking_id'];
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND r.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['rating'])) {
            $sql .= " AND r.rating = ?";
            $params[] = $filters['rating'];
        }

        // Default ordering
        $sql .= " ORDER BY r.created_at DESC";

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
     * Update review
     * @param int $id Review ID
     * @param array $reviewData Updated review information
     * @return bool True if update was successful
     */
    public function updateReview(int $id, array $reviewData): bool
    {
        $sql = "UPDATE reviews SET ";
        $updates = [];
        $params = [];

        $fields = ['rating', 'review_text'];
        
        foreach ($fields as $field) {
            if (array_key_exists($field, $reviewData)) {
                $updates[] = "$field = ?";
                $params[] = $reviewData[$field];
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
     * Delete review
     * @param int $id Review ID
     * @return bool True if deletion was successful
     */
    public function deleteReview(int $id): bool
    {
        $sql = "DELETE FROM reviews WHERE id = ?";
        return $this->db->execute($sql, [$id]) > 0;
    }

    /**
     * Get average rating for all reviews
     * @return float Average rating
     */
    public function getAverageRating(): float
    {
        $sql = "SELECT AVG(rating) as avg_rating FROM reviews";
        $result = $this->db->query($sql);
        return (float)($result[0]['avg_rating'] ?? 0);
    }

    /**
     * Get review statistics
     * @return array Review statistics
     */
    public function getReviewStats(): array
    {
        $stats = [];

        // Total reviews
        $result = $this->db->query("SELECT COUNT(*) as total FROM reviews");
        $stats['total_reviews'] = (int)$result[0]['total'];

        // Average rating
        $result = $this->db->query("SELECT AVG(rating) as avg_rating FROM reviews");
        $stats['average_rating'] = round((float)($result[0]['avg_rating'] ?? 0), 2);

        // Rating distribution
        $result = $this->db->query("SELECT rating, COUNT(*) as count FROM reviews GROUP BY rating ORDER BY rating");
        $stats['rating_distribution'] = $result;

        return $stats;
    }
}
?>
