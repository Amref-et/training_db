# Docker Deployment

This project can run in Docker with:

- `app`: Laravel application container with Nginx + PHP-FPM
- `queue`: Laravel queue worker using the same image
- `db`: MySQL 8

## 1. Prepare Environment

Copy the Docker env template:

```bash
cp .env.docker.example .env.docker
```

Set at least:

- `APP_KEY`
- `APP_URL`
- `DB_*`
- `MYSQL_*`

Generate an app key if needed:

```bash
php artisan key:generate --show
```

Copy the generated key into `.env.docker`.

If you do not want to rely on a host PHP install, you can build first and then generate the key from the container image:

```bash
docker compose build app
docker compose run --rm app php artisan key:generate --show
```

## 2. Build and Start

```bash
docker compose up -d --build
```

The app is exposed on:

```text
http://localhost:8080
```

## 3. First-Time Setup

Run migrations:

```bash
docker compose exec app php artisan migrate --force
```

If you need seeders:

```bash
docker compose exec app php artisan db:seed --force
```

## 4. Useful Commands

Application shell:

```bash
docker compose exec app sh
```

Queue logs:

```bash
docker compose logs -f queue
```

Application logs:

```bash
docker compose logs -f app
```

Rebuild after code changes:

```bash
docker compose up -d --build
```

## 5. Notes

- The setup uses MySQL by default, not SQLite.
- Uploaded files and Laravel runtime storage persist in the `storage-data` volume.
- MySQL data persists in the `db-data` volume.
- If you want Laravel caches enabled inside the container, set:

```env
CACHE_LARAVEL_BOOTSTRAP=true
```

- If you want migrations to run automatically on container boot, set:

```env
RUN_MIGRATIONS=true
```

Use that carefully in multi-instance deployments.
