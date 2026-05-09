<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

class AccountsAPI
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function createAccount(array $accountData): int
    {
        $sql = "INSERT INTO accounts (email, password_hash, name, role, status) VALUES (?, ?, ?, ?, ?)";

        $passwordHash = $accountData['password_hash'] ?? null;
        if (empty($passwordHash) && !empty($accountData['password'])) {
            $passwordHash = password_hash($accountData['password'], PASSWORD_DEFAULT);
        }

        $params = [
            $accountData['email'],
            $passwordHash,
            $accountData['name'] ?? null,
            $accountData['role'] ?? 'user',
            $accountData['status'] ?? 'active'
        ];

        $this->db->execute($sql, $params);
        return (int)$this->db->lastInsertId();
    }

    public function getAccountByEmail(string $email): ?array
    {
        $sql = "SELECT * FROM accounts WHERE email = ? LIMIT 1";
        $result = $this->db->query($sql, [$email]);
        return $result[0] ?? null;
    }

    public function getAccountById(int $id): ?array
    {
        $sql = "SELECT id, email, name, role, status, created_at, updated_at FROM accounts WHERE id = ? LIMIT 1";
        $result = $this->db->query($sql, [$id]);
        return $result[0] ?? null;
    }

    public function getAccounts(): array
    {
        $sql = "SELECT id, email, name, role, status, created_at, updated_at FROM accounts ORDER BY created_at DESC";
        return $this->db->query($sql);
    }

    public function verifyCredentials(string $email, string $password): ?array
    {
        $account = $this->getAccountByEmail($email);
        if ($account === null || $account['status'] !== 'active') {
            return null;
        }

        if (!password_verify($password, $account['password_hash'])) {
            return null;
        }

        return $account;
    }

    public function updateAccount(int $id, array $accountData): bool
    {
        $updates = [];
        $params = [];

        foreach (['email', 'name', 'role', 'status'] as $field) {
            if (array_key_exists($field, $accountData)) {
                $updates[] = "$field = ?";
                $params[] = $accountData[$field];
            }
        }

        if (array_key_exists('password', $accountData) && $accountData['password'] !== '') {
            $updates[] = 'password_hash = ?';
            $params[] = password_hash($accountData['password'], PASSWORD_DEFAULT);
        }

        if (empty($updates)) {
            return false;
        }

        $sql = 'UPDATE accounts SET ' . implode(', ', $updates) . ', updated_at = CURRENT_TIMESTAMP WHERE id = ?';
        $params[] = $id;

        return $this->db->execute($sql, $params) > 0;
    }

    public function deleteAccount(int $id): bool
    {
        $sql = "DELETE FROM accounts WHERE id = ?";
        return $this->db->execute($sql, [$id]) > 0;
    }
}
