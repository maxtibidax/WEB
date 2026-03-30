<?php
// controllers/CarController.php

require_once 'models/Car.php';

class CarController {
    private $model;

    public function __construct($pdo) {
        $this->model = new Car($pdo);
    }

    // Главная страница
    public function index() {
        $errors = [];
        $success = false;

        // Обработка POST-запроса (Добавление)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'brand'  => trim($_POST['brand'] ?? ''),
                'model'  => trim($_POST['model'] ?? ''),
                'price'  => trim($_POST['price'] ?? ''),
                'client' => trim($_POST['client'] ?? '')
            ];

            // Валидация
            if (empty($data['brand'])) $errors[] = "Марка обязательна.";
            if (empty($data['model'])) $errors[] = "Модель обязательна.";
            if (empty($data['price']) || !is_numeric($data['price'])) $errors[] = "Некорректная цена.";
            if (empty($data['client'])) $errors[] = "ФИО клиента обязательно.";

            if (empty($errors)) {
                $this->model->create($data);
                // PRG паттерн
                header("Location: /");
                exit;
            }
        }

        // Обработка GET-запроса (Фильтрация - Задание 3)
        $filterBrand = trim($_GET['search_brand'] ?? '');
        $cars = $this->model->getAll($filterBrand);

        // Подключаем View и передаем туда переменные
        require 'views/cars/index.php';
    }
}