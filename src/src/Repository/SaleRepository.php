<?php

namespace App\Repository;

use Exception;
use PDO;

final class SaleRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getClients(): array
    {
        return $this->pdo->query('SELECT * FROM clients ORDER BY full_name')->fetchAll();
    }

    public function getSalesHistory(): array
    {
        $sql = "
            SELECT sales.*, cars.model_name, brands.name AS brand_name, clients.full_name AS client_name
            FROM sales
            JOIN cars ON sales.car_id = cars.id
            JOIN brands ON cars.brand_id = brands.id
            JOIN clients ON sales.client_id = clients.id
            ORDER BY sales.sale_date DESC
        ";

        return $this->pdo->query($sql)->fetchAll();
    }

    public function sellCar(int $carId, int $clientId, float $finalPrice): bool
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare('
                UPDATE cars
                SET status = ?
                WHERE id = ? AND status = ?
            ');
            $stmt->execute(['sold', $carId, 'available']);

            if ($stmt->rowCount() !== 1) {
                $this->pdo->rollBack();

                return false;
            }

            $stmt = $this->pdo->prepare('
                INSERT INTO sales (car_id, client_id, final_price)
                VALUES (?, ?, ?)
            ');
            $stmt->execute([$carId, $clientId, $finalPrice]);

            $this->pdo->commit();

            return true;
        } catch (Exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return false;
        }
    }
}
