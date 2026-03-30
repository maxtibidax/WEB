<?php
// Router.php

class Router {
    private $routes = [];

    // Добавление маршрута
    public function add($path, $action) {
        $this->routes[$path] = $action;
    }

    // Обработка текущего URL
    public function dispatch($uri) {
        // Отрезаем GET-параметры (/?search_brand=BMW -> /)
        $path = parse_url($uri, PHP_URL_PATH);

        if (array_key_exists($path, $this->routes)) {
            // Вызываем функцию-обработчик маршрута
            call_user_func($this->routes[$path]);
        } else {
            http_response_code(404);
            echo "<h1>404 - Страница не найдена</h1>";
        }
    }
}