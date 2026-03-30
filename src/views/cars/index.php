<!-- views/cars/index.php -->
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>MVC Автосалон</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <h1 class="mb-4">🚗 Автосалон (MVC Архитектура)</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Форма добавления (POST) -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">Новая продажа</div>
                <div class="card-body">
                    <form action="/" method="POST">
                        <div class="mb-3">
                            <label>Марка авто</label>
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
                        <button type="submit" class="btn btn-success w-100">Сохранить</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- Блок Фильтрации (GET) - ЗАДАНИЕ 3 -->
            <div class="card mb-3">
                <div class="card-body bg-white">
                    <form action="/" method="GET" class="d-flex">
                        <input type="text" name="search_brand" class="form-control me-2" 
                               placeholder="🔍 Фильтр по марке (например: Toyota)" 
                               value="<?= htmlspecialchars($filterBrand ?? '') ?>">
                        <button type="submit" class="btn btn-primary">Найти</button>
                        <a href="/" class="btn btn-outline-secondary ms-2">Сбросить</a>
                    </form>
                </div>
            </div>

            <!-- Таблица вывода -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($cars)): ?>
                        <p class="text-muted text-center">Записи не найдены 🤷‍♂️</p>
                    <?php else: ?>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Марка</th>
                                    <th>Модель</th>
                                    <th>Цена</th>
                                    <th>Клиент</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cars as $car): ?>
                                    <tr>
                                        <td><?= $car['id'] ?></td>
                                        <td><?= htmlspecialchars($car['brand']) ?></td>
                                        <td><?= htmlspecialchars($car['model']) ?></td>
                                        <td>$<?= number_format($car['price'], 2) ?></td>
                                        <td><?= htmlspecialchars($car['client']) ?></td>
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