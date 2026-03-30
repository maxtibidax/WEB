<?php
// index.php

require_once 'config/database.php';
require_once 'Router.php';
require_once 'controllers/CarController.php';

// Инициализация БД
$pdo = getDbConnection();

// Инициализация роутера
$router = new Router();

// Настраиваем маршруты
$router->add('/', function() use ($pdo) {
    $controller = new CarController($pdo);
    $controller->index();
});

// Запускаем роутинг
$router->dispatch($_SERVER['REQUEST_URI']);