<?php

namespace App\Helpers;

use PDO;
use PDOException;
use RuntimeException;
use PDOStatement;
use App\Helpers\EnvParser;

class Database
{

    // Singleton instance storage.
    private static ?Database $instance = null;

    // Underlying PDO database connection.
    private ?PDO $pdo = null;

    // Configuration values loaded from environment.
    private $config;

    // Private constructor prevents external instantiation.
    private function __construct()
    {
        $env = new EnvParser();
        $env->load(__DIR__ . '/../../.env');
        $this->loadConfig();
        $this->connect();
    }

    // Load database connection settings from the environment.
    private function loadConfig()
    {
        $this->config = [
            'host' => getenv('DB_HOST') ?: 'localhost',
            'port' => getenv('DB_PORT') ?: '3306',
            'name' => getenv('DB_NAME'),
            'user' => getenv('DB_USER'),
            'password' => getenv('DB_PASSWORD'),
            'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
            'driver' => getenv('DB_DRIVER') ?: 'mysql'
        ];

        // Validate required fields
        if (!$this->config['name'] || !$this->config['user']) {
            throw new \Exception("Database name and user are required in .env file");
        }
    }

    // Establish a PDO connection using the loaded configuration.
    private function connect()
    {
        try {
            $dsn = sprintf(
                "%s:host=%s;port=%s;dbname=%s;charset=%s",
                $this->config['driver'],
                $this->config['host'],
                $this->config['port'],
                $this->config['name'],
                $this->config['charset']
            );

            $this->pdo = new PDO(
                $dsn,
                $this->config['user'],
                $this->config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    // Prevent cloning of the singleton instance.
    private function __clone() {}

    // Prevent unserialization of the singleton.
    public function __wakeup()
    {
        throw new RuntimeException("Cannot unserialize singleton");
    }

    // Get or create the singleton database instance.
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Return the raw PDO connection for direct usage.
    public function getConnection()
    {
        return $this->pdo;
    }

    // Example convenience helpers around PDO.
    public function prepare(string $sql): PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    public function query(string $sql): PDOStatement
    {
        return $this->pdo->query($sql);
    }

    // Return the ID of the last inserted row.
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    // Transaction management helpers.
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }
}
