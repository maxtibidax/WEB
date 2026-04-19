<?php
class Router {
    private $routes = [];

    // Добавили разделение на GET и POST методы
    public function get($path, $action) { $this->routes['GET'][$path] = $action; }
    public function post($path, $action) { $this->routes['POST'][$path] = $action; }

    public function dispatch($uri, $method) {
        $path = parse_url($uri, PHP_URL_PATH);
        if (isset($this->routes[$method][$path])) {
            call_user_func($this->routes[$method][$path]);
        } else {
            http_response_code(404);
            echo "404 - Страница не найдена";
        }
    }
}