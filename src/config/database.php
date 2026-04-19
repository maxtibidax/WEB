<?php
// config/database.php

function getDbConnection() {
    $host = getenv('DB_HOST') ?: 'db';
    $db   = getenv('DB_NAME') ?: 'cardb';
    $user = getenv('DB_USER') ?: 'postgres';
    $pass = getenv('DB_PASSWORD') ?: 'super_secret_pg';

    $dsn = "pgsql:host=$host;port=5432;dbname=$db;";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        initDatabase($pdo); // Запускаем проверку таблиц
        return $pdo;
    } catch (PDOException $e) {
        die("Ошибка подключения к базе данных.");
    }
}

function initDatabase($pdo) {
    // Создаем таблицы
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS brands (
            id SERIAL PRIMARY KEY, name VARCHAR(50) UNIQUE NOT NULL, country VARCHAR(50)
        );
        CREATE TABLE IF NOT EXISTS clients (
            id SERIAL PRIMARY KEY, full_name VARCHAR(150) NOT NULL, phone VARCHAR(20) UNIQUE NOT NULL
        );
        CREATE TABLE IF NOT EXISTS cars (
            id SERIAL PRIMARY KEY, brand_id INT NOT NULL REFERENCES brands(id),
            model_name VARCHAR(100) NOT NULL, production_year INT,
            price NUMERIC(10, 2) NOT NULL, status VARCHAR(20) DEFAULT 'available'
        );
        CREATE TABLE IF NOT EXISTS sales (
            id SERIAL PRIMARY KEY, car_id INT NOT NULL UNIQUE REFERENCES cars(id),
            client_id INT NOT NULL REFERENCES clients(id),
            sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, final_price NUMERIC(10, 2) NOT NULL
        );
    ");

    // Заполняем справочники, если они пустые (чтобы выпадающие списки в HTML работали)
    if ($pdo->query("SELECT COUNT(*) FROM brands")->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO brands (name, country) VALUES ('Toyota', 'Japan'), ('BMW', 'Germany'), ('Ford', 'USA');
            INSERT INTO clients (full_name, phone) VALUES ('Иванов И.И.', '+7-999-111-22-33'), ('Петров П.П.', '+7-900-555-44-11');
        ");
    }
}