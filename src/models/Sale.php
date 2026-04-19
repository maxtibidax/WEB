<?php
// models/Sale.php
class Sale {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }

    // Справочник клиентов
    public function getClients() {
        return $this->pdo->query("SELECT * FROM clients ORDER BY full_name")->fetchAll();
    }

    // История продаж (Многоэтапный JOIN всех 4 таблиц!)
    public function getSalesHistory() {
        $sql = "SELECT sales.*, cars.model_name, brands.name AS brand_name, clients.full_name AS client_name 
                FROM sales 
                JOIN cars ON sales.car_id = cars.id 
                JOIN brands ON cars.brand_id = brands.id 
                JOIN clients ON sales.client_id = clients.id 
                ORDER BY sales.sale_date DESC";
        return $this->pdo->query($sql)->fetchAll();
    }

    // Оформление продажи (ТРАНЗАКЦИЯ)
    public function sellCar($car_id, $client_id, $final_price) {
        try {
            $this->pdo->beginTransaction(); // Начинаем транзакцию

            // 1. Записываем продажу
            $stmt = $this->pdo->prepare("INSERT INTO sales (car_id, client_id, final_price) VALUES (?, ?, ?)");
            $stmt->execute([$car_id, $client_id, $final_price]);

            // 2. Меняем статус машины на 'sold' (продана)
            $stmt = $this->pdo->prepare("UPDATE cars SET status = 'sold' WHERE id = ?");
            $stmt->execute([$car_id]);

            $this->pdo->commit(); // Подтверждаем изменения
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack(); // Если ошибка - откатываем всё назад
            return false;
        }
    }
}