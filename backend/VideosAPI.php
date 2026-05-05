<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

/**
 * Videos API class for managing YouTube videos in MariaDB
 */
class VideosAPI
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /**
     * Get all videos with optional filtering
     * @param array $filters Filter options (zodiac_sign, video_type, featured, limit, offset)
     * @return array Videos data
     */
    public function getVideos(array $filters = []): array
    {
        $sql = "SELECT id, title, link, description, published_date, zodiac_sign, video_type, featured 
                FROM videos WHERE 1=1";
        $params = [];

        // Add filters
        if (!empty($filters['zodiac_sign'])) {
            $sql .= " AND zodiac_sign = ?";
            $params[] = $filters['zodiac_sign'];
        }

        if (!empty($filters['video_type'])) {
            $sql .= " AND video_type = ?";
            $params[] = $filters['video_type'];
        }

        if (isset($filters['featured'])) {
            $sql .= " AND featured = ?";
            $params[] = $filters['featured'] ? 1 : 0;
        }

        // Default ordering
        $sql .= " ORDER BY published_date DESC";

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
     * Get featured videos
     * @param int $limit Maximum number of videos to return
     * @return array Featured videos
     */
    public function getFeaturedVideos(int $limit = 5): array
    {
        return $this->getVideos(['featured' => true, 'limit' => $limit]);
    }

    /**
     * Get videos by zodiac sign
     * @param string $zodiacSign Zodiac sign name
     * @param int $limit Maximum number of videos to return
     * @return array Videos for the specified zodiac sign
     */
    public function getVideosByZodiac(string $zodiacSign, int $limit = 10): array
    {
        return $this->getVideos(['zodiac_sign' => $zodiacSign, 'limit' => $limit]);
    }

    /**
     * Get latest videos
     * @param int $limit Maximum number of videos to return
     * @return array Latest videos
     */
    public function getLatestVideos(int $limit = 10): array
    {
        return $this->getVideos(['limit' => $limit]);
    }

    /**
     * Add a new video
     * @param array $videoData Video information
     * @return int ID of the inserted video
     */
    public function addVideo(array $videoData): int
    {
        $sql = "INSERT INTO videos (title, link, description, published_date, zodiac_sign, video_type, featured) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $videoData['title'],
            $videoData['link'],
            $videoData['description'] ?? null,
            $videoData['published_date'],
            $videoData['zodiac_sign'] ?? null,
            $videoData['video_type'] ?? 'general',
            $videoData['featured'] ?? false
        ];

        $this->db->execute($sql, $params);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Update an existing video
     * @param int $id Video ID
     * @param array $videoData Updated video information
     * @return bool True if update was successful
     */
    public function updateVideo(int $id, array $videoData): bool
    {
        $sql = "UPDATE videos SET ";
        $updates = [];
        $params = [];

        $fields = ['title', 'link', 'description', 'published_date', 'zodiac_sign', 'video_type', 'featured'];
        
        foreach ($fields as $field) {
            if (array_key_exists($field, $videoData)) {
                $updates[] = "$field = ?";
                $params[] = $videoData[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $sql .= implode(', ', $updates) . " WHERE id = ?";
        $params[] = $id;

        return $this->db->execute($sql, $params) > 0;
    }

    /**
     * Delete a video
     * @param int $id Video ID
     * @return bool True if deletion was successful
     */
    public function deleteVideo(int $id): bool
    {
        $sql = "DELETE FROM videos WHERE id = ?";
        return $this->db->execute($sql, [$id]) > 0;
    }

    /**
     * Get video by ID
     * @param int $id Video ID
     * @return array|null Video data or null if not found
     */
    public function getVideoById(int $id): ?array
    {
        $sql = "SELECT * FROM videos WHERE id = ?";
        $result = $this->db->query($sql, [$id]);
        return $result[0] ?? null;
    }

    /**
     * Get video statistics
     * @return array Video statistics
     */
    public function getVideoStats(): array
    {
        $stats = [];

        // Total videos
        $result = $this->db->query("SELECT COUNT(*) as total FROM videos");
        $stats['total_videos'] = (int)$result[0]['total'];

        // Videos by type
        $result = $this->db->query("SELECT video_type, COUNT(*) as count FROM videos GROUP BY video_type");
        $stats['by_type'] = $result;

        // Videos by zodiac sign
        $result = $this->db->query("SELECT zodiac_sign, COUNT(*) as count FROM videos WHERE zodiac_sign IS NOT NULL GROUP BY zodiac_sign");
        $stats['by_zodiac'] = $result;

        // Featured videos count
        $result = $this->db->query("SELECT COUNT(*) as count FROM videos WHERE featured = 1");
        $stats['featured_count'] = (int)$result[0]['count'];

        return $stats;
    }
}
