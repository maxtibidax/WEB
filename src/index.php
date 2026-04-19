<?php
require_once 'config/database.php';
require_once 'Router.php';
require_once 'controllers/MainController.php';

$pdo = getDbConnection();
$router = new Router();
$controller = new MainController($pdo);

// Настраиваем маршруты
$router->get('/', function() use ($controller) { $controller->index(); });
$router->post('/add-car', function() use ($controller) { $controller->addCar(); });
$router->post('/sell-car', function() use ($controller) { $controller->sellCar(); });

// Запускаем роутер
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);