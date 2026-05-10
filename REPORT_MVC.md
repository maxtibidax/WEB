# Отчет по переделке проекта на Symfony

## Цель изменений

Проект автосалона был перенесен с самописного PHP MVC на фреймворк Symfony.

Главная цель переделки:

- заменить самописный роутер на маршруты Symfony;
- перенести контроллеры в стандартную структуру `src/src/Controller`;
- перенести работу с базой данных в репозитории;
- заменить PHP-шаблоны на Twig;
- оставить прежнюю бизнес-логику: авторизацию, роли, автомобили, продажи и отчеты;
- сохранить PostgreSQL и запуск через Docker.

## Что изменилось в архитектуре

Раньше входной точкой был файл:

```text
src/index.php
```

Теперь входная точка Symfony находится в:

```text
src/public/index.php
```

Старый файл `Router.php` больше не нужен. Маршруты описаны атрибутами Symfony в контроллере:

```text
src/src/Controller/AutoSalonController.php
```

Примеры маршрутов:

- `/` - личный кабинет или страница входа;
- `/login` - вход;
- `/register` - регистрация;
- `/logout` - выход;
- `/add-car` - добавление автомобиля;
- `/sell-car` - оформление продажи;
- `/report` - скачивание отчетов.

## Структура Symfony-проекта

Основные файлы после переделки:

```text
src/
  public/
    index.php
    .htaccess

  config/
    bundles.php
    routes.yaml
    services.yaml
    packages/
      framework.yaml
      twig.yaml

  src/
    Kernel.php
    Controller/
      AutoSalonController.php
    Infrastructure/
      DatabaseConnectionFactory.php
    Repository/
      CarRepository.php
      SaleRepository.php
      UserRepository.php
    Service/
      ReportExporter.php

  templates/
    base.html.twig
    auth/
      index.html.twig
    dashboard/
      index.html.twig
      _cars_table.html.twig
    reports/
      table_pdf.html.twig
```

## Работа с базой данных

В проекте сохранена работа через PDO и PostgreSQL.

Подключение к базе вынесено в фабрику:

```text
src/src/Infrastructure/DatabaseConnectionFactory.php
```

Фабрика:

- создает PDO-подключение;
- использует переменные окружения `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`;
- создает таблицы, если их еще нет;
- добавляет тестовые бренды, клиентов и пользователей.

Данные теперь получают репозитории:

- `CarRepository` - бренды и автомобили;
- `SaleRepository` - клиенты, продажи и оформление сделки;
- `UserRepository` - поиск и создание пользователей.

## Контроллер

Вся обработка пользовательских запросов перенесена в Symfony-контроллер:

```text
src/src/Controller/AutoSalonController.php
```

Контроллер теперь использует:

- маршруты Symfony через PHP-атрибуты `#[Route(...)]`;
- flash-сообщения Symfony вместо ручного хранения ошибок в `$_SESSION`;
- встроенную CSRF-защиту Symfony;
- сервисы и репозитории через dependency injection;
- Twig для HTML-страниц и PDF-шаблона.

## Шаблоны Twig

Обычные PHP-view заменены на Twig:

- `templates/auth/index.html.twig` - вход и регистрация;
- `templates/dashboard/index.html.twig` - личный кабинет;
- `templates/dashboard/_cars_table.html.twig` - таблица автомобилей;
- `templates/reports/table_pdf.html.twig` - HTML для PDF-отчета;
- `templates/base.html.twig` - общий базовый шаблон.

Формы теперь отправляются на маршруты Symfony через `path(...)`, например:

```twig
{{ path('app_login') }}
```

CSRF-токен формируется через:

```twig
{{ csrf_token('app') }}
```

## Отчеты

Сервис отчетов находится в:

```text
src/src/Service/ReportExporter.php
```

Он формирует:

- CSV;
- Excel `.xlsx` через `PhpSpreadsheet`;
- PDF через `Dompdf`.

В отличие от старой версии, сервис больше не вызывает `header()` и `exit`. Теперь он возвращает Symfony `Response` или `StreamedResponse`, что лучше соответствует архитектуре фреймворка.

## Docker

Docker оставлен как основной способ запуска.

В `Dockerfile` Apache теперь настроен на Symfony-папку:

```text
/var/www/html/public
```

В `docker-compose.yml` добавлены переменные окружения для Symfony и базы данных:

- `APP_ENV`;
- `APP_SECRET`;
- `DB_HOST`;
- `DB_NAME`;
- `DB_USER`;
- `DB_PASSWORD`.

При запуске контейнера выполняется установка Composer-зависимостей:

```bash
composer install --no-interaction --prefer-dist --no-progress && apache2-foreground
```

Так как старый `composer.lock` относился к прежнему проекту без Symfony, он удален. При первом запуске Docker Composer создаст новый lock-файл.

## Подключенные библиотеки

В `src/composer.json` добавлены зависимости:

- `symfony/framework-bundle`;
- `symfony/twig-bundle`;
- `symfony/runtime`;
- `symfony/console`;
- `symfony/dotenv`;
- `symfony/yaml`;
- `phpoffice/phpspreadsheet`;
- `dompdf/dompdf`.

Также на странице личного кабинета по CDN используются:

- Bootstrap;
- Bootstrap Icons;
- Chart.js.

## Как объяснить преподавателю

Проект теперь построен на Symfony:

- маршрутизация выполняется фреймворком;
- контроллер принимает HTTP-запросы и возвращает `Response`;
- Twig отвечает за отображение страниц;
- репозитории отвечают за работу с базой данных;
- сервис `ReportExporter` отвечает за генерацию файлов;
- зависимости подключаются через Composer;
- объекты автоматически передаются через dependency injection.

При этом функциональность старого проекта сохранена: регистрация, вход, роли пользователя и администратора, добавление машин, оформление продаж и скачивание отчетов.

## Проверка работоспособности

На текущем компьютере локально нет `php` и `composer`, а Docker Desktop не был запущен, поэтому автоматическую проверку через Symfony CLI выполнить не удалось.

Для проверки после запуска Docker Desktop нужно выполнить:

```bash
docker compose up --build
```

После запуска приложение будет доступно по адресу:

```text
http://localhost/
```

Тестовые пользователи:

- администратор: `admin@example.com`, пароль `admin123`;
- пользователь: `user@example.com`, пароль `user123`.

## Краткий вывод

Проект переделан с самописного PHP MVC на Symfony. Код стал ближе к промышленной структуре: маршруты, контроллеры, сервисы, репозитории, Twig-шаблоны и Symfony Response вместо ручной обработки HTTP.
