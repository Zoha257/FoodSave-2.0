<?php
class Guideline {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->ensureSchema();
    }

    private function ensureSchema(): void {
        $sql = "CREATE TABLE IF NOT EXISTS food_safety_guidelines (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    content TEXT NOT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log('Failed to ensure guideline schema: ' . $e->getMessage());
        }
    }

    public function listAll(bool $includeInactive = false): array {
        $sql = 'SELECT * FROM food_safety_guidelines';
        if (!$includeInactive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY updated_at DESC';

        try {
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Failed to list guidelines: ' . $e->getMessage());
            return [];
        }
    }

    public function find(int $id): ?array {
        $sql = 'SELECT * FROM food_safety_guidelines WHERE id = ?';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('Failed to fetch guideline: ' . $e->getMessage());
            return null;
        }
    }

    public function create(string $title, string $content, bool $isActive = true): bool {
        $sql = 'INSERT INTO food_safety_guidelines (title, content, is_active) VALUES (?, ?, ?)';

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$title, $content, $isActive ? 1 : 0]);
        } catch (PDOException $e) {
            error_log('Failed to create guideline: ' . $e->getMessage());
            return false;
        }
    }

    public function update(int $id, string $title, string $content, bool $isActive = true): bool {
        $sql = 'UPDATE food_safety_guidelines SET title = ?, content = ?, is_active = ?, updated_at = NOW() WHERE id = ?';

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$title, $content, $isActive ? 1 : 0, $id]);
        } catch (PDOException $e) {
            error_log('Failed to update guideline: ' . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool {
        $sql = 'DELETE FROM food_safety_guidelines WHERE id = ?';

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log('Failed to delete guideline: ' . $e->getMessage());
            return false;
        }
    }
}
