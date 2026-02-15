<?php
// Имя файла для хранения данных
$csvFile = 'data.csv';

// === ЧАСТЬ 1: ОБРАБОТКА POST (Сохранение) ===
// Проверяем, пришла ли форма методом POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем данные и очищаем их от лишних пробелов и опасных символов
    $brand  = trim(htmlspecialchars($_POST['brand']));
    $model  = trim(htmlspecialchars($_POST['model']));
    $price  = floatval($_POST['price']); // Преобразуем в число
    $client = trim(htmlspecialchars($_POST['client']));

    // Простая валидация: поля не должны быть пустыми
    if (!empty($brand) && !empty($model) && !empty($price) && !empty($client)) {
        // Формируем массив данных
        $row = [$brand, $model, $price, $client, date('Y-m-d H:i:s')];

        // Открываем файл в режиме 'a' (append - добавление в конец)
        $handle = fopen($csvFile, 'a');
        if ($handle !== false) {
            // Записываем массив в формате CSV
            fputcsv($handle, $row, ';'); // Используем точку с запятой как разделитель
            fclose($handle);
        }
        
        // Перезагружаем страницу, чтобы сбросить POST-запрос (паттерн PRG)
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// === ЧАСТЬ 2: ОБРАБОТКА GET (Фильтрация/Поиск) ===
$searchQuery = '';
if (isset($_GET['search'])) {
    $searchQuery = trim(htmlspecialchars($_GET['search']));
}

// === ЧАСТЬ 3: ЧТЕНИЕ ДАННЫХ ИЗ CSV ===
$cars = [];
if (file_exists($csvFile)) {
    $handle = fopen($csvFile, 'r');
    if ($handle !== false) {
        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            // $data - это массив [0=>Brand, 1=>Model, ...]
            
            // Если есть поисковый запрос, проверяем совпадение
            if ($searchQuery) {
                // Ищем строку поиска в Марке или Клиенте (регистронезависимо)
                if (stripos($data[0], $searchQuery) === false && stripos($data[3], $searchQuery) === false) {
                    continue; // Пропускаем эту запись, если не нашли
                }
            }
            $cars[] = $data;
        }
        fclose($handle);
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Лабораторная 2: Автосалон</title>
    <!-- Немного стилей для красоты (Bootstrap CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <h1 class="mb-4">🚗 Учет продаж автосалона</h1>

    <div class="row">
        <!-- Левая колонка: Форма добавления (POST) -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">Новая продажа</div>
                <div class="card-body">
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label>Марка авто</label>
                            <input type="text" name="brand" class="form-control" placeholder="BMW" required>
                        </div>
                        <div class="mb-3">
                            <label>Модель</label>
                            <input type="text" name="model" class="form-control" placeholder="X5" required>
                        </div>
                        <div class="mb-3">
                            <label>Цена ($)</label>
                            <input type="number" name="price" class="form-control" placeholder="50000" required>
                        </div>
                        <div class="mb-3">
                            <label>ФИО Клиента</label>
                            <input type="text" name="client" class="form-control" placeholder="Иванов И.И." required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Сохранить (POST)</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Правая колонка: Таблица и Поиск (GET) -->
        <div class="col-md-8">
            <!-- Форма поиска -->
            <form action="" method="GET" class="d-flex mb-3">
                <input type="text" name="search" class="form-control me-2" 
                       placeholder="Поиск по марке или клиенту..." 
                       value="<?= $searchQuery ?>">
                <button type="submit" class="btn btn-outline-primary">Найти (GET)</button>
                <?php if($searchQuery): ?>
                    <a href="index.php" class="btn btn-outline-secondary ms-2">Сброс</a>
                <?php endif; ?>
            </form>

            <!-- Таблица -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($cars)): ?>
                        <p class="text-muted text-center">Записей нет 🤷‍♂️</p>
                    <?php else: ?>
                        <table class="table table-hover">
                            <thead>
                                <tr>
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
                                        <td><?= $car[0] ?></td>
                                        <td><?= $car[1] ?></td>
                                        <td>$<?= number_format((float)$car[2]) ?></td>
                                        <td><?= $car[3] ?></td>
                                        <td class="text-muted small"><?= $car[4] ?></td>
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