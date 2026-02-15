# Берем официальный образ PHP с Apache (веб-сервером)
FROM php:8.2-apache

# Устанавливаем расширения для работы с базой данных (PDO и MySQLi)
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Включаем модуль rewrite для Apache (пригодится в будущем)
RUN a2enmod rewrite