<?php

namespace App\Infrastructure;

use PDO;
use PDOException;

final class DatabaseConnectionFactory
{
    public static function create(): PDO
    {
        $host = self::env('DB_HOST', 'db');
        $db = self::env('DB_NAME', 'cardb');
        $user = self::env('DB_USER', 'postgres');
        $password = self::env('DB_PASSWORD', 'super_secret_pg');
        $dsn = "pgsql:host={$host};port=5432;dbname={$db};";

        try {
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::initDatabase($pdo);

            return $pdo;
        } catch (PDOException) {
            throw new PDOException('Ошибка подключения к базе данных.');
        }
    }

    private static function env(string $name, string $default): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return $value !== false && $value !== null && $value !== '' ? (string) $value : $default;
    }

    private static function initDatabase(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS brands (
                id SERIAL PRIMARY KEY,
                name VARCHAR(50) UNIQUE NOT NULL,
                country VARCHAR(50)
            );
            CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                full_name VARCHAR(150) NOT NULL,
                email VARCHAR(150) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'user' CHECK (role IN ('admin', 'user')),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS clients (
                id SERIAL PRIMARY KEY,
                full_name VARCHAR(150) NOT NULL,
                phone VARCHAR(20) UNIQUE NOT NULL
            );
            CREATE TABLE IF NOT EXISTS cars (
                id SERIAL PRIMARY KEY,
                brand_id INT NOT NULL REFERENCES brands(id),
                model_name VARCHAR(100) NOT NULL,
                production_year INT,
                price NUMERIC(10, 2) NOT NULL,
                status VARCHAR(20) DEFAULT 'available'
            );
            CREATE TABLE IF NOT EXISTS sales (
                id SERIAL PRIMARY KEY,
                car_id INT NOT NULL UNIQUE REFERENCES cars(id),
                client_id INT NOT NULL REFERENCES clients(id),
                sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                final_price NUMERIC(10, 2) NOT NULL
            );
        ");

        $pdo->exec("
            ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) NOT NULL DEFAULT 'user';
            ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
        ");

        $pdo->exec("UPDATE users SET role = 'admin' WHERE role = 'manager'");

        if ((int) $pdo->query('SELECT COUNT(*) FROM brands')->fetchColumn() === 0) {
            $pdo->exec("
                INSERT INTO brands (name, country)
                VALUES ('Toyota', 'Japan'), ('BMW', 'Germany'), ('Ford', 'USA');
            ");
        }

        if ((int) $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn() === 0) {
            $pdo->exec("
                INSERT INTO clients (full_name, phone)
                VALUES ('Иванов И.И.', '+7-999-111-22-33'), ('Петров П.П.', '+7-900-555-44-11');
            ");
        }

        if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, email, password_hash, role)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute(['Администратор', 'admin@example.com', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
            $stmt->execute(['Пользователь', 'user@example.com', password_hash('user123', PASSWORD_DEFAULT), 'user']);
        }
    }
}
