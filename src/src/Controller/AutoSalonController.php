<?php

namespace App\Controller;

use App\Repository\CarRepository;
use App\Repository\SaleRepository;
use App\Repository\UserRepository;
use App\Service\ReportExporter;
use PDOException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final class AutoSalonController extends AbstractController
{
    public function __construct(
        private readonly CarRepository $cars,
        private readonly SaleRepository $sales,
        private readonly UserRepository $users,
        private readonly ReportExporter $reportExporter,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getCurrentUser($request);

        if ($user === null) {
            return $this->render('auth/index.html.twig');
        }

        $availableCars = $this->cars->getAvailableCars();
        $isAdmin = $this->hasRole($request, ['admin']);
        $salesHistory = $isAdmin ? $this->sales->getSalesHistory() : [];

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'roleLabels' => UserRepository::ROLE_LABELS,
            'isAdmin' => $isAdmin,
            'availableCars' => $availableCars,
            'brands' => $isAdmin ? $this->cars->getBrands() : [],
            'clients' => $isAdmin ? $this->sales->getClients() : [],
            'salesHistory' => $salesHistory,
            'maxProductionYear' => ((int) date('Y')) + 1,
            ...$this->buildDashboardReportData($availableCars, $salesHistory),
        ]);
    }

    #[Route('/report', name: 'app_report', methods: ['GET'])]
    public function downloadReport(Request $request): Response
    {
        if ($this->getCurrentUser($request) === null) {
            $this->addFlash('danger', 'Сначала войдите в систему.');

            return $this->redirectToRoute('app_home');
        }

        $type = (string) $request->query->get('type', 'cars');
        $format = (string) $request->query->get('format', 'csv');

        if (!in_array($format, ['csv', 'excel', 'pdf'], true)) {
            $this->addFlash('danger', 'Неизвестный формат отчета.');

            return $this->redirectToRoute('app_home');
        }

        if ($type === 'sales' && !$this->hasRole($request, ['admin'])) {
            $this->addFlash('danger', 'У вашей роли недостаточно прав для этого отчета.');

            return $this->redirectToRoute('app_home');
        }

        $report = $this->buildReportData($type);

        if ($report === null) {
            $this->addFlash('danger', 'Неизвестный тип отчета.');

            return $this->redirectToRoute('app_home');
        }

        $pdfHtml = $format === 'pdf'
            ? $this->twig->render('reports/table_pdf.html.twig', [
                'title' => $report['title'],
                'headers' => $report['headers'],
                'rows' => $report['rows'],
                'generatedAt' => date('d.m.Y H:i'),
            ])
            : null;

        return $this->reportExporter->export(
            $format,
            $report['filenameBase'],
            $report['title'],
            $report['headers'],
            $report['rows'],
            $pdfHtml
        );
    }

    #[Route('/register', name: 'app_register', methods: ['POST'])]
    public function register(Request $request): RedirectResponse
    {
        $this->validateCsrf($request);

        $fullName = trim((string) $request->request->get('full_name', ''));
        $email = mb_strtolower(trim((string) $request->request->get('email', '')));
        $password = (string) $request->request->get('password', '');
        $passwordConfirmation = (string) $request->request->get('password_confirmation', '');
        $errors = [];

        if ($fullName === '') {
            $errors[] = 'Укажите имя пользователя.';
        } elseif (mb_strlen($fullName) > 150) {
            $errors[] = 'Имя пользователя слишком длинное.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Укажите корректный email.';
        }

        if (mb_strlen($password) < 6) {
            $errors[] = 'Пароль должен содержать минимум 6 символов.';
        }

        if ($password !== $passwordConfirmation) {
            $errors[] = 'Пароли не совпадают.';
        }

        if ($email !== '' && $this->users->findByEmail($email) !== null) {
            $errors[] = 'Пользователь с таким email уже существует.';
        }

        if ($errors !== []) {
            $this->flashErrors($errors);

            return $this->redirectToRoute('app_home');
        }

        try {
            $user = $this->users->create($fullName, $email, $password);
            $this->loginUser($request->getSession(), $user);
            $this->addFlash('success', 'Регистрация прошла успешно. Добро пожаловать в личный кабинет!');
        } catch (PDOException) {
            $this->addFlash('danger', 'Не удалось создать пользователя. Попробуйте еще раз.');
        }

        return $this->redirectToRoute('app_home');
    }

    #[Route('/login', name: 'app_login', methods: ['POST'])]
    public function login(Request $request): RedirectResponse
    {
        $this->validateCsrf($request);

        $email = mb_strtolower(trim((string) $request->request->get('email', '')));
        $password = (string) $request->request->get('password', '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $this->addFlash('danger', 'Введите email и пароль.');

            return $this->redirectToRoute('app_home');
        }

        $user = $this->users->findByEmail($email);

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            $this->addFlash('danger', 'Неверный email или пароль.');

            return $this->redirectToRoute('app_home');
        }

        $this->loginUser($request->getSession(), $user);
        $this->addFlash('success', 'Вы успешно вошли в систему.');

        return $this->redirectToRoute('app_home');
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(Request $request): RedirectResponse
    {
        $this->validateCsrf($request);
        $request->getSession()->invalidate();
        $this->addFlash('success', 'Вы вышли из аккаунта.');

        return $this->redirectToRoute('app_home');
    }

    #[Route('/add-car', name: 'app_add_car', methods: ['POST'])]
    public function addCar(Request $request): RedirectResponse
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $this->validateCsrf($request);

        $brandId = (int) $request->request->get('brand_id', 0);
        $model = trim((string) $request->request->get('model_name', ''));
        $year = (int) $request->request->get('production_year', 0);
        $price = (float) $request->request->get('price', 0);
        $errors = [];
        $maxYear = ((int) date('Y')) + 1;

        if ($brandId <= 0) {
            $errors[] = 'Пожалуйста, выберите бренд.';
        }
        if ($model === '') {
            $errors[] = "Поле 'Модель' не может быть пустым.";
        } elseif (mb_strlen($model) > 100) {
            $errors[] = 'Название модели слишком длинное (максимум 100 символов).';
        }
        if ($year < 1900 || $year > $maxYear) {
            $errors[] = "Год выпуска должен быть между 1900 и {$maxYear}.";
        }
        if ($price <= 0) {
            $errors[] = 'Цена автомобиля должна быть больше нуля.';
        }

        if ($errors !== []) {
            $this->flashErrors($errors);

            return $this->redirectToRoute('app_home');
        }

        try {
            $this->cars->add($brandId, $model, $year, $price);
            $this->addFlash('success', 'Автомобиль успешно добавлен в салон!');
        } catch (PDOException) {
            $this->addFlash('danger', 'Ошибка базы данных. Проверьте данные.');
        }

        return $this->redirectToRoute('app_home');
    }

    #[Route('/sell-car', name: 'app_sell_car', methods: ['POST'])]
    public function sellCar(Request $request): RedirectResponse
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $this->validateCsrf($request);

        $carId = (int) $request->request->get('car_id', 0);
        $clientId = (int) $request->request->get('client_id', 0);
        $finalPrice = (float) $request->request->get('final_price', 0);
        $errors = [];

        if ($carId <= 0) {
            $errors[] = 'Выберите автомобиль для продажи.';
        }
        if ($clientId <= 0) {
            $errors[] = 'Выберите клиента.';
        }
        if ($finalPrice <= 0) {
            $errors[] = 'Итоговая цена должна быть больше нуля.';
        }

        if ($errors !== []) {
            $this->flashErrors($errors);

            return $this->redirectToRoute('app_home');
        }

        if ($this->sales->sellCar($carId, $clientId, $finalPrice)) {
            $this->addFlash('success', 'Сделка успешно оформлена!');
        } else {
            $this->addFlash('danger', 'Ошибка при оформлении сделки. Возможно, машина уже продана.');
        }

        return $this->redirectToRoute('app_home');
    }

    private function getCurrentUser(Request $request): ?array
    {
        $sessionUser = $request->getSession()->get('user');
        $sessionUserId = (int) ($sessionUser['id'] ?? 0);

        if ($sessionUserId <= 0) {
            return null;
        }

        $user = $this->users->findById($sessionUserId);

        if ($user === null) {
            $request->getSession()->remove('user');

            return null;
        }

        $this->loginUser($request->getSession(), $user, false);

        return $request->getSession()->get('user');
    }

    private function loginUser(SessionInterface $session, array $user, bool $migrateSession = true): void
    {
        if ($migrateSession) {
            $session->migrate(true);
        }

        $session->set('user', [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'created_at' => $user['created_at'] ?? null,
        ]);
    }

    private function validateCsrf(Request $request): void
    {
        $token = (string) $request->request->get('csrf_token', '');

        if (!$this->isCsrfTokenValid('app', $token)) {
            throw new AccessDeniedHttpException('Ошибка CSRF: недействительный токен безопасности!');
        }
    }

    private function hasRole(Request $request, array $roles): bool
    {
        $user = $request->getSession()->get('user');
        $role = $user['role'] ?? null;

        return $role !== null && in_array($role, $roles, true);
    }

    private function requireRole(Request $request, array $roles): ?RedirectResponse
    {
        if ($this->getCurrentUser($request) === null) {
            $this->addFlash('danger', 'Сначала войдите в систему.');

            return $this->redirectToRoute('app_home');
        }

        if (!$this->hasRole($request, $roles)) {
            $this->addFlash('danger', 'У вашей роли недостаточно прав для этого действия.');

            return $this->redirectToRoute('app_home');
        }

        return null;
    }

    private function buildDashboardReportData(array $availableCars, array $salesHistory): array
    {
        $brandStats = [];
        $salesByMonth = [];

        foreach ($availableCars as $car) {
            $brand = $car['brand_name'] ?? 'Без бренда';
            $brandStats[$brand] = ($brandStats[$brand] ?? 0) + 1;
        }

        foreach ($salesHistory as $sale) {
            $month = date('m.Y', strtotime($sale['sale_date']));
            $salesByMonth[$month] = ($salesByMonth[$month] ?? 0) + (float) $sale['final_price'];
        }

        $salesCount = count($salesHistory);
        $salesTotal = array_sum(array_map(static fn (array $sale): float => (float) $sale['final_price'], $salesHistory));

        return [
            'availableCarsCount' => count($availableCars),
            'availableCarsTotal' => array_sum(array_map(static fn (array $car): float => (float) $car['price'], $availableCars)),
            'salesCount' => $salesCount,
            'salesTotal' => $salesTotal,
            'averageSale' => $salesCount > 0 ? $salesTotal / $salesCount : 0,
            'brandChartLabels' => array_keys($brandStats),
            'brandChartValues' => array_values($brandStats),
            'salesChartLabels' => array_keys($salesByMonth),
            'salesChartValues' => array_values($salesByMonth),
        ];
    }

    private function buildReportData(string $type): ?array
    {
        if ($type === 'cars') {
            $availableCars = $this->cars->getAvailableCars();

            return [
                'filenameBase' => 'cars-report',
                'title' => 'Отчет по автомобилям в наличии',
                'headers' => ['ID', 'Бренд', 'Модель', 'Год', 'Цена'],
                'rows' => array_map(static fn (array $car): array => [
                    $car['id'],
                    $car['brand_name'],
                    $car['model_name'],
                    $car['production_year'],
                    number_format((float) $car['price'], 2, '.', ''),
                ], $availableCars),
            ];
        }

        if ($type === 'sales') {
            $salesHistory = $this->sales->getSalesHistory();

            return [
                'filenameBase' => 'sales-report',
                'title' => 'Отчет по продажам',
                'headers' => ['ID чека', 'Дата', 'Клиент', 'Автомобиль', 'Сумма сделки'],
                'rows' => array_map(static fn (array $sale): array => [
                    '#'.$sale['id'],
                    date('d.m.Y H:i', strtotime($sale['sale_date'])),
                    $sale['client_name'],
                    $sale['brand_name'].' '.$sale['model_name'],
                    number_format((float) $sale['final_price'], 2, '.', ''),
                ], $salesHistory),
            ];
        }

        return null;
    }

    private function flashErrors(array $errors): void
    {
        foreach ($errors as $error) {
            $this->addFlash('danger', $error);
        }
    }
}
