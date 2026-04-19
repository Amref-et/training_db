# Amref Training Database

This repository contains the Amref Training Database, a Laravel-based platform for:

- training event planning and delivery
- participant and organization management
- project and subawardee tracking
- workshop score capture and reporting
- public CMS pages and branded website settings
- role-based administration and dashboards

## Documentation

The full project documentation is available here:

- [docs/AMREF_TRAINING_DATABASE_DOCUMENTATION.md](docs/AMREF_TRAINING_DATABASE_DOCUMENTATION.md)

## Quick Start

### Requirements

- PHP 8.2+
- Composer
- Node.js and npm
- MySQL
- Apache with `mod_rewrite`

### Install

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
npm run build
```

### Local development

```bash
composer dev
```

## Important Notes

- The application is designed to run correctly from a subdirectory deployment such as `http://localhost/test/hil-v2`.
- Do not point `APP_URL` at `/public`.
- Large organization datasets are handled through on-demand searching in participant forms.
- Organization hierarchy CSV import can also be run with:

```bash
php artisan organizations:import-hierarchy "D:\path\to\file.csv"
```
