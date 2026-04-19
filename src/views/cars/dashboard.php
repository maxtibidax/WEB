<!-- views/dashboard.php -->
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>MVC Автосалон (4 таблицы)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light pb-5">

<div class="container mt-4">
    <h1 class="mb-4 text-center">🏢 Управление Автосалоном (MVC + 4 Таблицы)</h1>

    <!-- БЛОК 1: АВТОМОБИЛИ В НАЛИЧИИ -->
    <div class="row mb-5">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">Добавить авто в салон</div>
                <div class="card-body">
                    <form action="/add-car" method="POST">
                        <div class="mb-2">
                            <label>Бренд</label>
                            <select name="brand_id" class="form-select" required>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label>Модель</label>
                            <input type="text" name="model_name" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label>Год</label>
                            <input type="number" name="production_year" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Цена ($)</label>
                            <input type="number" step="0.01" name="price" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Добавить</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <h4>🚗 Машины в наличии</h4>
            <table class="table table-bordered bg-white">
                <thead class="table-dark">
                    <tr><th>ID</th><th>Бренд</th><th>Модель</th><th>Год</th><th>Цена</th></tr>
                </thead>
                <tbody>
                    <?php if(empty($availableCars)) echo "<tr><td colspan='5' class='text-center'>Нет машин</td></tr>"; ?>
                    <?php foreach ($availableCars as $car): ?>
                        <tr>
                            <td><?= $car['id'] ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($car['brand_name']) ?></span></td>
                            <td><?= htmlspecialchars($car['model_name']) ?></td>
                            <td><?= $car['production_year'] ?></td>
                            <td>$<?= number_format($car['price'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <hr>

    <!-- БЛОК 2: ПРОДАЖИ -->
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">Оформить продажу</div>
                <div class="card-body">
                    <form action="/sell-car" method="POST">
                        <div class="mb-2">
                            <label>Выберите машину (из наличия)</label>
                            <select name="car_id" class="form-select" required>
                                <?php foreach ($availableCars as $car): ?>
                                    <option value="<?= $car['id'] ?>">
                                        <?= htmlspecialchars($car['brand_name'] . ' ' . $car['model_name']) ?> 
                                        ($<?= $car['price'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label>Клиент</label>
                            <select name="client_id" class="form-select" required>
                                <?php foreach ($clients as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name'] . ' (' . $c['phone'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Итоговая цена сделки ($)</label>
                            <input type="number" step="0.01" name="final_price" class="form-control" placeholder="С учетом скидок" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Продать авто</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <h4>🧾 История продаж</h4>
            <table class="table table-bordered bg-white">
                <thead class="table-success">
                    <tr><th>ID чека</th><th>Дата</th><th>Клиент</th><th>Автомобиль</th><th>Сумма сделки</th></tr>
                </thead>
                <tbody>
                    <?php if(empty($salesHistory)) echo "<tr><td colspan='5' class='text-center'>Пока нет продаж</td></tr>"; ?>
                    <?php foreach ($salesHistory as $sale): ?>
                        <tr>
                            <td>#<?= $sale['id'] ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($sale['sale_date'])) ?></td>
                            <td><?= htmlspecialchars($sale['client_name']) ?></td>
                            <td><?= htmlspecialchars($sale['brand_name'] . ' ' . $sale['model_name']) ?></td>
                            <td class="fw-bold text-success">$<?= number_format($sale['final_price'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>