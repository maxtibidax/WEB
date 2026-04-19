<?php
// models/Car.php
class Car {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }

    // Получаем справочник брендов для выпадающего списка
    public function getBrands() {
        return $this->pdo->query("SELECT * FROM brands ORDER BY name")->fetchAll();
    }

    // Получаем список машин В НАЛИЧИИ (используем JOIN для получения имени бренда)
    public function getAvailableCars() {
        $sql = "SELECT cars.*, brands.name AS brand_name 
                FROM cars 
                JOIN brands ON cars.brand_id = brands.id 
                WHERE cars.status = 'available' 
                ORDER BY cars.id DESC";
        return $this->pdo->query($sql)->fetchAll();
    }

    // Добавление новой машины
    public function add($brand_id, $model_name, $year, $price) {
        $stmt = $this->pdo->prepare("INSERT INTO cars (brand_id, model_name, production_year, price) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$brand_id, $model_name, $year, $price]);
    }
}