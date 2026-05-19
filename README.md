# SOSS

System documentation for this application (built with Laravel).

## Quick links

- Deployment + manual testing: `docs/deploy_shipment_cancellation_rules.md`

## Tech stack

- PHP ^8.2 + Composer
- Laravel Framework ^12
- Node.js + npm (Vite + Tailwind)
- Database: MySQL/MariaDB recommended (SQLite exists in `.env.example` for convenience)
- Queue + cache + sessions: configured to use the database by default (`QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database`)
- Payments: Stripe (`stripe/stripe-php`, see `.env.example` Stripe keys)

## Local setup (Windows / XAMPP friendly)

From the repo root:

1. Install backend dependencies:
   - `composer install`
2. Create your environment file:
   - Copy `.env.example` to `.env`
3. Generate app key:
   - `php artisan key:generate`
4. Configure the database in `.env` (recommended):
   - Set `DB_CONNECTION=mysql`
   - Set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
5. Run migrations:
   - `php artisan migrate`
6. Install frontend dependencies + build assets:
   - `npm install`
   - `npm run build`

Optional (common in Laravel apps):

- Storage symlink (if your app serves uploaded files from `storage/app/public`):
  - `php artisan storage:link`

## Development

- Run the full dev stack (server + queue listener + logs + Vite):
  - `composer run dev`

If you prefer running pieces separately:

- Backend:
  - `php artisan serve`
- Frontend:
  - `npm run dev`
- Queue worker:
  - `php artisan queue:listen --tries=1`

## Tests

- `composer run test`

## Documentation

Project documentation lives in `docs/`.

- Shipment & cancellation rules: `docs/deploy_shipment_cancellation_rules.md`

## Notes

- This repository is an application that *uses* Laravel; the framework docs are at `https://laravel.com/docs`.
