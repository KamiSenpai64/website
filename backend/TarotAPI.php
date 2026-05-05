<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

/**
 * Tarot API class for managing tarot cards and daily messages in MariaDB
 */
class TarotAPI
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /**
     * Get all tarot cards
     * @return array All tarot cards
     */
    public function getAllCards(): array
    {
        $sql = "SELECT id, name, description, image_filename, card_type, number, element, 
                       upright_meaning, reversed_meaning, keywords 
                FROM tarot_cards 
                ORDER BY number ASC";
        return $this->db->query($sql);
    }

    /**
     * Get a random tarot card
     * @return array Random tarot card
     */
    public function getRandomCard(): array
    {
        $sql = "SELECT id, name, description, image_filename, card_type, number, element, 
                       upright_meaning, reversed_meaning, keywords 
                FROM tarot_cards 
                ORDER BY RAND() 
                LIMIT 1";
        $result = $this->db->query($sql);
        return $result[0] ?? [];
    }

    /**
     * Get daily message for today
     * @return array|null Daily message with card data or null if not set
     */
    public function getDailyMessage(): ?array
    {
        $sql = "SELECT dm.custom_message, dm.message_date,
                       tc.id, tc.name, tc.description, tc.image_filename, 
                       tc.upright_meaning, tc.reversed_meaning, tc.keywords
                FROM daily_messages dm
                JOIN tarot_cards tc ON dm.card_id = tc.id
                WHERE dm.message_date = CURDATE() AND dm.is_active = 1
                LIMIT 1";
        
        $result = $this->db->query($sql);
        return $result[0] ?? null;
    }

    /**
     * Set daily message for today
     * @param int $cardId Tarot card ID
     * @param string|null $customMessage Custom message (optional)
     * @return bool True if successful
     */
    public function setDailyMessage(int $cardId, ?string $customMessage = null): bool
    {
        $this->db->beginTransaction();
        
        try {
            // Check if message already exists for today
            $sql = "SELECT id FROM daily_messages WHERE message_date = CURDATE()";
            $existing = $this->db->query($sql);
            
            if (!empty($existing)) {
                // Update existing message
                $sql = "UPDATE daily_messages 
                        SET card_id = ?, custom_message = ?, is_active = 1, updated_at = CURRENT_TIMESTAMP 
                        WHERE message_date = CURDATE()";
                $params = [$cardId, $customMessage];
            } else {
                // Insert new message
                $sql = "INSERT INTO daily_messages (card_id, custom_message, message_date, is_active) 
                        VALUES (?, ?, CURDATE(), 1)";
                $params = [$cardId, $customMessage];
            }
            
            $this->db->execute($sql, $params);
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Get card by ID
     * @param int $id Card ID
     * @return array|null Card data or null if not found
     */
    public function getCardById(int $id): ?array
    {
        $sql = "SELECT * FROM tarot_cards WHERE id = ?";
        $result = $this->db->query($sql, [$id]);
        return $result[0] ?? null;
    }

    /**
     * Get cards by element
     * @param string $element Element name (fire, earth, air, water)
     * @return array Cards of specified element
     */
    public function getCardsByElement(string $element): array
    {
        $sql = "SELECT * FROM tarot_cards WHERE element = ? ORDER BY number ASC";
        return $this->db->query($sql, [$element]);
    }

    /**
     * Get cards by type
     * @param string $type Card type (major, minor)
     * @return array Cards of specified type
     */
    public function getCardsByType(string $type): array
    {
        $sql = "SELECT * FROM tarot_cards WHERE card_type = ? ORDER BY number ASC";
        return $this->db->query($sql, [$type]);
    }

    /**
     * Add new tarot card
     * @param array $cardData Card information
     * @return int ID of inserted card
     */
    public function addCard(array $cardData): int
    {
        $sql = "INSERT INTO tarot_cards (name, description, image_filename, card_type, number, element, 
                                        upright_meaning, reversed_meaning, keywords) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $cardData['name'],
            $cardData['description'],
            $cardData['image_filename'],
            $cardData['card_type'] ?? 'major',
            $cardData['number'] ?? null,
            $cardData['element'] ?? null,
            $cardData['upright_meaning'] ?? null,
            $cardData['reversed_meaning'] ?? null,
            $cardData['keywords'] ? json_encode($cardData['keywords']) : null
        ];

        $this->db->execute($sql, $params);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Update existing tarot card
     * @param int $id Card ID
     * @param array $cardData Updated card information
     * @return bool True if update was successful
     */
    public function updateCard(int $id, array $cardData): bool
    {
        $sql = "UPDATE tarot_cards SET ";
        $updates = [];
        $params = [];

        $fields = ['name', 'description', 'image_filename', 'card_type', 'number', 'element', 
                  'upright_meaning', 'reversed_meaning', 'keywords'];
        
        foreach ($fields as $field) {
            if (array_key_exists($field, $cardData)) {
                $updates[] = "$field = ?";
                if ($field === 'keywords') {
                    $params[] = $cardData[$field] ? json_encode($cardData[$field]) : null;
                } else {
                    $params[] = $cardData[$field];
                }
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
     * Get daily messages history
     * @param int $limit Maximum number of messages to return
     * @param int $offset Offset for pagination
     * @return array Daily messages history
     */
    public function getDailyMessagesHistory(int $limit = 30, int $offset = 0): array
    {
        $sql = "SELECT dm.message_date, dm.custom_message, dm.is_active,
                       tc.name as card_name, tc.image_filename
                FROM daily_messages dm
                JOIN tarot_cards tc ON dm.card_id = tc.id
                ORDER BY dm.message_date DESC
                LIMIT ? OFFSET ?";
        
        return $this->db->query($sql, [$limit, $offset]);
    }

    /**
     * Get tarot statistics
     * @return array Tarot statistics
     */
    public function getTarotStats(): array
    {
        $stats = [];

        // Total cards
        $result = $this->db->query("SELECT COUNT(*) as total FROM tarot_cards");
        $stats['total_cards'] = (int)$result[0]['total'];

        // Cards by type
        $result = $this->db->query("SELECT card_type, COUNT(*) as count FROM tarot_cards GROUP BY card_type");
        $stats['by_type'] = $result;

        // Cards by element
        $result = $this->db->query("SELECT element, COUNT(*) as count FROM tarot_cards WHERE element IS NOT NULL GROUP BY element");
        $stats['by_element'] = $result;

        // Daily messages count
        $result = $this->db->query("SELECT COUNT(*) as count FROM daily_messages WHERE is_active = 1");
        $stats['daily_messages_count'] = (int)$result[0]['count'];

        return $stats;
    }
}
