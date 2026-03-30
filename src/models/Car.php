<?php
// models/Car.php

class Car {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->initTable();
        $this->importCSV();
    }

    // Создание таблицы
    private function initTable() {
        $sql = "CREATE TABLE IF NOT EXISTS cars (
            id SERIAL PRIMARY KEY,
            brand VARCHAR(100) NOT NULL,
            model VARCHAR(100) NOT NULL,
            price NUMERIC(10, 2) NOT NULL,
            client VARCHAR(150) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
    }

    // Импорт из CSV
    private function importCSV() {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM cars");
        if ($stmt->fetchColumn() == 0 && file_exists('data.csv')) {
            $handle = fopen('data.csv', 'r');
            if ($handle !== false) {
                $this->pdo->beginTransaction();
                $insertStmt = $this->pdo->prepare("INSERT INTO cars (brand, model, price, client) VALUES (?, ?, ?, ?)");
                $isFirstRow = true;
                while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                    if ($isFirstRow) { $isFirstRow = false; continue; }
                    if (count($data) >= 4) {
                        $insertStmt->execute([trim($data[0]), trim($data[1]), floatval($data[2]), trim($data[3])]);
                    }
                }
                $this->pdo->commit();
                fclose($handle);
            }
        }
    }

    // ЗАДАНИЕ 3: Получение данных с фильтрацией
    public function getAll($filterBrand = '') {
        $sql = "SELECT * FROM cars";
        $params = [];

        // Если передан фильтр по марке
        if (!empty($filterBrand)) {
            // ILIKE - регистронезависимый поиск в PostgreSQL
            $sql .= " WHERE brand ILIKE :brand";
            $params[':brand'] = '%' . $filterBrand . '%';
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Добавление новой машины
    public function create($data) {
        $stmt = $this->pdo->prepare("INSERT INTO cars (brand, model, price, client) VALUES (:brand, :model, :price, :client)");
        return $stmt->execute([
            ':brand'  => $data['brand'],
            ':model'  => $data['model'],
            ':price'  => $data['price'],
            ':client' => $data['client']
        ]);
    }
}