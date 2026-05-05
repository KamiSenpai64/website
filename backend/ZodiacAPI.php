<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

/**
 * Zodiac API class for managing zodiac signs in MariaDB
 */
class ZodiacAPI
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /**
     * Get all zodiac signs
     * @return array All zodiac signs
     */
    public function getAllZodiacSigns(): array
    {
        $sql = "SELECT id, name, html_path, element, symbol, date_range, description 
                FROM zodiac_signs 
                ORDER BY 
                    CASE element 
                        WHEN 'fire' THEN 1
                        WHEN 'earth' THEN 2
                        WHEN 'air' THEN 3
                        WHEN 'water' THEN 4
                    END,
                    id";
        return $this->db->query($sql);
    }

    /**
     * Get zodiac sign by name
     * @param string $name Zodiac sign name
     * @return array|null Zodiac sign data or null if not found
     */
    public function getZodiacSignByName(string $name): ?array
    {
        $sql = "SELECT * FROM zodiac_signs WHERE name = ?";
        $result = $this->db->query($sql, [$name]);
        return $result[0] ?? null;
    }

    /**
     * Get zodiac sign by ID
     * @param int $id Zodiac sign ID
     * @return array|null Zodiac sign data or null if not found
     */
    public function getZodiacSignById(int $id): ?array
    {
        $sql = "SELECT * FROM zodiac_signs WHERE id = ?";
        $result = $this->db->query($sql, [$id]);
        return $result[0] ?? null;
    }

    /**
     * Get zodiac signs by element
     * @param string $element Element name (fire, earth, air, water)
     * @return array Zodiac signs of specified element
     */
    public function getZodiacSignsByElement(string $element): array
    {
        $sql = "SELECT * FROM zodiac_signs WHERE element = ? ORDER BY id";
        return $this->db->query($sql, [$element]);
    }

    /**
     * Add new zodiac sign
     * @param array $zodiacData Zodiac sign information
     * @return int ID of inserted zodiac sign
     */
    public function addZodiacSign(array $zodiacData): int
    {
        $sql = "INSERT INTO zodiac_signs (name, html_path, element, symbol, date_range, description) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $params = [
            $zodiacData['name'],
            $zodiacData['html_path'],
            $zodiacData['element'] ?? null,
            $zodiacData['symbol'] ?? null,
            $zodiacData['date_range'] ?? null,
            $zodiacData['description'] ?? null
        ];

        $this->db->execute($sql, $params);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Update existing zodiac sign
     * @param int $id Zodiac sign ID
     * @param array $zodiacData Updated zodiac sign information
     * @return bool True if update was successful
     */
    public function updateZodiacSign(int $id, array $zodiacData): bool
    {
        $sql = "UPDATE zodiac_signs SET ";
        $updates = [];
        $params = [];

        $fields = ['name', 'html_path', 'element', 'symbol', 'date_range', 'description'];
        
        foreach ($fields as $field) {
            if (array_key_exists($field, $zodiacData)) {
                $updates[] = "$field = ?";
                $params[] = $zodiacData[$field];
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
     * Delete zodiac sign
     * @param int $id Zodiac sign ID
     * @return bool True if deletion was successful
     */
    public function deleteZodiacSign(int $id): bool
    {
        $sql = "DELETE FROM zodiac_signs WHERE id = ?";
        return $this->db->execute($sql, [$id]) > 0;
    }

    /**
     * Get zodiac signs grouped by element
     * @return array Zodiac signs grouped by element
     */
    public function getZodiacSignsGroupedByElement(): array
    {
        $sql = "SELECT element, 
                       GROUP_CONCAT(CONCAT(name, ' (', symbol, ')') SEPARATOR ', ') as signs,
                       COUNT(*) as count
                FROM zodiac_signs 
                WHERE element IS NOT NULL
                GROUP BY element
                ORDER BY 
                    CASE element 
                        WHEN 'fire' THEN 1
                        WHEN 'earth' THEN 2
                        WHEN 'air' THEN 3
                        WHEN 'water' THEN 4
                    END";
        
        return $this->db->query($sql);
    }

    /**
     * Get zodiac statistics
     * @return array Zodiac statistics
     */
    public function getZodiacStats(): array
    {
        $stats = [];

        // Total zodiac signs
        $result = $this->db->query("SELECT COUNT(*) as total FROM zodiac_signs");
        $stats['total_signs'] = (int)$result[0]['total'];

        // Signs by element
        $result = $this->db->query("SELECT element, COUNT(*) as count FROM zodiac_signs WHERE element IS NOT NULL GROUP BY element");
        $stats['by_element'] = $result;

        // Elements distribution
        $elements = ['fire', 'earth', 'air', 'water'];
        $stats['element_distribution'] = [];
        
        foreach ($elements as $element) {
            $result = $this->db->query("SELECT COUNT(*) as count FROM zodiac_signs WHERE element = ?", [$element]);
            $stats['element_distribution'][$element] = (int)$result[0]['count'];
        }

        return $stats;
    }

    /**
     * Search zodiac signs
     * @param string $searchTerm Search term
     * @return array Matching zodiac signs
     */
    public function searchZodiacSigns(string $searchTerm): array
    {
        $sql = "SELECT * FROM zodiac_signs 
                WHERE name LIKE ? OR description LIKE ? OR symbol LIKE ?
                ORDER BY name";
        
        $searchPattern = "%" . $searchTerm . "%";
        return $this->db->query($sql, [$searchPattern, $searchPattern, $searchPattern]);
    }

    /**
     * Get zodiac sign for current date (simplified - you might want to implement proper zodiac date calculation)
     * @return array|null Current zodiac sign based on today's date
     */
    public function getCurrentZodiacSign(): ?array
    {
        // This is a simplified version. You might want to implement proper zodiac date ranges
        $today = date('m-d');
        
        $sql = "SELECT * FROM zodiac_signs 
                WHERE ? BETWEEN SUBSTRING_INDEX(date_range, ' - ', 1) 
                           AND SUBSTRING_INDEX(SUBSTRING_INDEX(date_range, ' - ', -1), ' ', 2)
                LIMIT 1";
        
        $result = $this->db->query($sql, [$today]);
        return $result[0] ?? null;
    }
}
