<?php
// === 1. ПОДКЛЮЧЕНИЕ К БАЗЕ ДАННЫХ ===
$host = getenv('DB_HOST') ?: 'db';
$db   = getenv('DB_NAME') ?: 'cardb';
$user = getenv('DB_USER') ?: 'postgres';
$pass = getenv('DB_PASSWORD') ?: 'super_secret_pg';

$dsn = "pgsql:host=$host;port=5432;dbname=$db;";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // В реальном проекте ошибку пишут в лог (error_log), а пользователю показывают общую фразу,
    // чтобы не "светить" пароли и IP-адреса базы данных.
    die("Ошибка подключения к базе данных. Проверьте настройки.");
}

// === 2. СОЗДАНИЕ ТАБЛИЦЫ, ЕСЛИ ЕЁ НЕТ ===
$sqlCreate = "
    CREATE TABLE IF NOT EXISTS cars (
        id SERIAL PRIMARY KEY,
        brand VARCHAR(100) NOT NULL,
        model VARCHAR(100) NOT NULL,
        price NUMERIC(10, 2) NOT NULL,
        client VARCHAR(150) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
";
$pdo->exec($sqlCreate);

// === 3. МИГРАЦИЯ ИЗ CSV В БАЗУ ДАННЫХ ===
$stmt = $pdo->query("SELECT COUNT(*) FROM cars");
$count = $stmt->fetchColumn();

if ($count == 0 && file_exists('data.csv')) {
    $handle = fopen('data.csv', 'r');
    if ($handle !== false) {
        // Оборачиваем вставку в транзакцию для ускорения работы с большим CSV
        $pdo->beginTransaction();
        $insertStmt = $pdo->prepare("INSERT INTO cars (brand, model, price, client) VALUES (?, ?, ?, ?)");
        
        $isFirstRow = true;
        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            // Пропускаем первую строку с заголовками (Brand;Model;Price;Client)
            if ($isFirstRow) {
                $isFirstRow = false;
                continue;
            }
            
            // Защита от пустых строк
            if (count($data) >= 4) {
                $insertStmt->execute([trim($data[0]), trim($data[1]), floatval($data[2]), trim($data[3])]);
            }
        }
        $pdo->commit(); // Применяем изменения в БД разом
        fclose($handle);
    }
}

// === 4. ОБРАБОТКА ФОРМЫ И ВАЛИДАЦИЯ ===
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем данные
    $brand  = trim($_POST['brand'] ?? '');
    $model  = trim($_POST['model'] ?? '');
    $price  = trim($_POST['price'] ?? '');
    $client = trim($_POST['client'] ?? '');

    // --- НАЧАЛО ВАЛИДАЦИИ ---
    if (empty($brand)) {
        $errors[] = "Марка автомобиля обязательна для заполнения.";
    } elseif (mb_strlen($brand) > 100) { // Длина синхронизирована с БД (VARCHAR 100)
        $errors[] = "Марка автомобиля слишком длинная (максимум 100 символов).";
    }

    if (empty($model)) {
        $errors[] = "Модель обязательна для заполнения.";
    } elseif (mb_strlen($model) > 100) {
        $errors[] = "Модель слишком длинная (максимум 100 символов).";
    }

    if (empty($price) || !is_numeric($price) || $price <= 0) {
        $errors[] = "Цена должна быть положительным числом.";
    }

    if (empty($client)) {
        $errors[] = "ФИО клиента обязательно для заполнения.";
    } elseif (mb_strlen($client) > 150) { // Длина синхронизирована с БД (VARCHAR 150)
        $errors[] = "ФИО клиента слишком длинное (максимум 150 символов).";
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $client)) {
        $errors[] = "ФИО клиента может содержать только буквы, пробелы и дефисы.";
    }
    // --- КОНЕЦ ВАЛИДАЦИИ ---

    // Если ошибок нет, сохраняем в БД
    if (empty($errors)) {
        // Данные в БД сохраняем "как есть" (сырые), экранировать их для БД НЕ НУЖНО!
        // Подготовленные запросы (PDO prepare) уже гарантируют 100% защиту от SQL инъекций.
        $stmt = $pdo->prepare("INSERT INTO cars (brand, model, price, client) VALUES (:brand, :model, :price, :client)");
        $stmt->execute([
            ':brand'  => $brand,
            ':model'  => $model,
            ':price'  => floatval($price),
            ':client' => $client
        ]);

        // Паттерн PRG (Post/Redirect/Get) - защита от повторной отправки формы по F5
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// === 5. ПОЛУЧЕНИЕ ДАННЫХ ДЛЯ ВЫВОДА ===
$stmt = $pdo->query("SELECT * FROM cars ORDER BY id DESC");
$cars = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Лабораторная 3: Базы Данных</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <h1 class="mb-4">🚗 Автосалон (PostgreSQL)</h1>

    <!-- ВЫВОД ОШИБОК ВАЛИДАЦИИ -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Ошибка сохранения:</strong>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Форма -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-success text-white">Новая продажа</div>
                <div class="card-body">
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label>Марка авто</label>
                            <!-- ОБЯЗАТЕЛЬНО htmlspecialchars для защиты от XSS в форме -->
                            <input type="text" name="brand" class="form-control" value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label>Модель</label>
                            <input type="text" name="model" class="form-control" value="<?= htmlspecialchars($_POST['model'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label>Цена ($)</label>
                            <input type="text" name="price" class="form-control" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label>ФИО Клиента</label>
                            <input type="text" name="client" class="form-control" value="<?= htmlspecialchars($_POST['client'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-success w-100">Сохранить в БД</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Таблица -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($cars)): ?>
                        <p class="text-muted text-center">В базе данных пока нет записей 🤷‍♂️</p>
                    <?php else: ?>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Марка</th>
                                    <th>Модель</th>
                                    <th>Цена</th>
                                    <th>Клиент</th>
                                    <th>Дата</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cars as $car): ?>
                                    <tr>
                                        <td><?= $car['id'] ?></td>
                                        <!-- ОБЯЗАТЕЛЬНО htmlspecialchars для вывода данных из БД в браузер (защита от XSS) -->
                                        <td><?= htmlspecialchars($car['brand']) ?></td>
                                        <td><?= htmlspecialchars($car['model']) ?></td>
                                        <td>$<?= number_format($car['price'], 2) ?></td>
                                        <td><?= htmlspecialchars($car['client']) ?></td>
                                        <td class="text-muted small"><?= date('d.m.Y H:i', strtotime($car['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>