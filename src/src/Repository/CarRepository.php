<?php

namespace App\Repository;

use PDO;

final class CarRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getBrands(): array
    {
        return $this->pdo->query('SELECT * FROM brands ORDER BY name')->fetchAll();
    }

    public function getAvailableCars(): array
    {
        $sql = "
            SELECT cars.*, brands.name AS brand_name
            FROM cars
            JOIN brands ON cars.brand_id = brands.id
            WHERE cars.status = 'available'
            ORDER BY cars.id DESC
        ";

        return $this->pdo->query($sql)->fetchAll();
    }

    public function add(int $brandId, string $modelName, int $year, float $price): bool
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO cars (brand_id, model_name, production_year, price)
            VALUES (?, ?, ?, ?)
        ');

        return $stmt->execute([$brandId, $modelName, $year, $price]);
    }
}
