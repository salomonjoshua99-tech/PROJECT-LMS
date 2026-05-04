<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class UserModel{

    public function __construct(private PDO $pdo){
    }

    public function findByEmail(string $email): ?array{

        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(string $name, string $email, string $passwordHash, string $role): int{

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => $role,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function recordLogin(int $userId, ?string $ipAddress, string $userAgent): void{

        $stmt = $this->pdo->prepare(
            'INSERT INTO login_records (user_id, ip_address, user_agent, logged_in_at)
             VALUES (:user_id, :ip_address, :user_agent, NOW())'
        );
        $stmt->execute([
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public function updateProfile(int $userId, string $name, string $email): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET name = :name, email = :email WHERE id = :id'
        );
        return $stmt->execute([
            'name' => $name,
            'email' => $email,
            'id' => $userId
        ]);
    }

    public function changePassword(int $userId, string $newPasswordHash): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET password_hash = :password_hash WHERE id = :id'
        );
        return $stmt->execute([
            'password_hash' => $newPasswordHash,
            'id' => $userId
        ]);
    }
}
