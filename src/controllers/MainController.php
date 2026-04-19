<?php
// controllers/MainController.php
require_once 'models/Car.php';
require_once 'models/Sale.php';

class MainController {
    private $carModel;
    private $saleModel;

    public function __construct($pdo) {
        $this->carModel = new Car($pdo);
        $this->saleModel = new Sale($pdo);
    }

    // Вывод главной страницы
    public function index() {
        $brands = $this->carModel->getBrands();
        $clients = $this->saleModel->getClients();
        $availableCars = $this->carModel->getAvailableCars();
        $salesHistory = $this->saleModel->getSalesHistory();

        require 'views/cars/dashboard.php';
    }

    // Обработка формы добавления машины
    public function addCar() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $brand_id = $_POST['brand_id'];
            $model = trim($_POST['model_name']);
            $year = $_POST['production_year'];
            $price = $_POST['price'];

            if (!empty($brand_id) && !empty($model) && $price > 0) {
                $this->carModel->add($brand_id, $model, $year, $price);
            }
        }
        header("Location: /"); // Редирект обратно
    }

    // Обработка формы продажи машины
    public function sellCar() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $car_id = $_POST['car_id'];
            $client_id = $_POST['client_id'];
            $final_price = $_POST['final_price'];

            if (!empty($car_id) && !empty($client_id) && $final_price > 0) {
                $this->saleModel->sellCar($car_id, $client_id, $final_price);
            }
        }
        header("Location: /");
    }
}