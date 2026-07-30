# UK Sponsor Licence Checker

Production Laravel 12 application that imports the live GOV.UK Register of Licensed Sponsors (Workers) CSV and exposes fast web and JSON search.

## Installation

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan sponsors:update
php artisan serve
```

## Docker

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan sponsors:update
```

## Import command

```bash
php artisan sponsors:update
```

The importer downloads the current GOV.UK publication page, discovers the latest CSV link dynamically, validates the CSV, parses quoted rows with `SplFileObject`, upserts sponsors in chunks, records import logs and rolls back on failure.

## Scheduler

Add this cron entry on a non-Docker server:

```cron
* * * * * cd /var/www/sponsorlicensecheck && php artisan schedule:run >> /dev/null 2>&1
```

## Queue worker / Supervisor

```ini
[program:sponsor-worker]
command=php /var/www/sponsorlicensecheck/artisan queue:work --tries=3 --timeout=120
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/sponsor-worker.log
```

## Nginx

Use `docker/nginx/default.conf` as the production baseline and update `server_name`, TLS and root path.

## Default admin credentials

Create an admin user with Laravel Tinker or your identity provider and set `ADMIN_EMAILS=admin@example.com`. No password is hardcoded in this repository.

## API

- `GET /api/search?q=company&town=London&status=Licensed`
- `GET /api/company/{id}`
- `GET /api/statistics`

## Production deployment

1. Provision PHP 8.4, MySQL 8, Nginx, Supervisor and Composer.
2. Configure `.env` with production MySQL credentials, `APP_URL`, `APP_KEY`, `ADMIN_EMAILS`, `QUEUE_CONNECTION=database`, and `CACHE_STORE=database`.
3. Run `composer install --no-dev --optimize-autoloader`.
4. Run `php artisan migrate --force`.
5. Run `php artisan config:cache route:cache view:cache`.
6. Configure cron for `schedule:run` and Supervisor for `queue:work`.
7. Run `php artisan sponsors:update` once before opening traffic.
