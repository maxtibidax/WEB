<?php

namespace App\Repository;

use PDO;

final class UserRepository
{
    public const ROLE_LABELS = [
        'admin' => 'Администратор',
        'user' => 'Пользователь',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function create(string $fullName, string $email, string $password, string $role = 'user'): array
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO users (full_name, email, password_hash, role)
            VALUES (?, ?, ?, ?)
            RETURNING id, full_name, email, role, created_at
        ');

        $stmt->execute([
            $fullName,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $role,
        ]);

        return $stmt->fetch();
    }
}
