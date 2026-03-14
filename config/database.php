<?php
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dbPath = BASE_PATH . '/database/fittrack.db';
        try {
            $this->pdo = new PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->initSchema();
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): PDO {
        return $this->pdo;
    }

    private function initSchema(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                role TEXT DEFAULT 'user',
                age INTEGER,
                gender TEXT,
                height REAL,
                weight REAL,
                activity_level TEXT DEFAULT 'moderate',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS bmi_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                height REAL NOT NULL,
                weight REAL NOT NULL,
                bmi REAL NOT NULL,
                category TEXT NOT NULL,
                recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS goals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                goal_type TEXT NOT NULL,
                target_weight REAL,
                target_calories INTEGER,
                target_date TEXT,
                notes TEXT,
                status TEXT DEFAULT 'active',
                ai_plan TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS food_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                log_date TEXT NOT NULL,
                meal_type TEXT DEFAULT 'other',
                food_name TEXT NOT NULL,
                calories REAL NOT NULL,
                protein REAL DEFAULT 0,
                carbs REAL DEFAULT 0,
                fat REAL DEFAULT 0,
                portion TEXT,
                image_path TEXT,
                ai_detected INTEGER DEFAULT 0,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS daily_summaries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                summary_date TEXT NOT NULL,
                total_calories REAL DEFAULT 0,
                total_protein REAL DEFAULT 0,
                total_carbs REAL DEFAULT 0,
                total_fat REAL DEFAULT 0,
                target_calories INTEGER DEFAULT 2000,
                notes TEXT,
                UNIQUE(user_id, summary_date),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
        ");

        // Insert default admin if not exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = 'admin@fittrack.com'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $this->pdo->prepare("
                INSERT INTO users (name, email, password, role)
                VALUES ('Admin', 'admin@fittrack.com', ?, 'admin')
            ")->execute([password_hash('admin123', PASSWORD_DEFAULT)]);
        }
    }

    public function query(string $sql, array $params = []): \PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array {
        return $this->query($sql, $params)->fetch() ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(string $sql, array $params = []): string {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }
}
