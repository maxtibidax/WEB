FROM php:8.2-apache

# Устанавливаем системную библиотеку libpq-dev (нужна для компиляции драйвера Postgres)
# И устанавливаем расширения pdo_pgsql для PHP
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Включаем ЧПУ (на будущее)
RUN a2enmod rewrite