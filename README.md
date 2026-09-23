# Ticketing

A lightweight ticketing and project management app built with **Laravel 12**, Blade and Bootstrap 5.

- Two languages: **Persian (default)** and **English** — PHP translation files in `lang/fa` and `lang/en`.
- Two themes that follow the language: **RTL theme** for Persian, **LTR theme** for English.
- Per-user **calendar** (Jalali or Gregorian), independent of the language.
- Three roles: **admin**, **developer**, **customer**.
- Tickets with status, priority (Jira colours), type, sprint, story points, estimates, time logged, costs,
  due date, rich-text content (TinyMCE with inline image upload) and attachments (10 MB each).
- Full ticket **history** (every edit is saved as a revision) and **soft delete**.
- Ticket folders ("cartables") with **badges**, one search page with many filters, and **custom menus**.
- Every grid has a **column chooser**; the choice is saved on the server.

Design and phases: [PLAN.md](PLAN.md) · [PHASES.md](PHASES.md)

## Requirements

- PHP 8.3+ with `pdo_mysql` (or `pdo_sqlite`), `mbstring`, `intl`, `gd`, `fileinfo`, `zip`
- Composer 2, Node 20+
- MariaDB 10.6+ / MySQL 8 (SQLite works for local development)

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# set DB_* in .env (default is SQLite: database/database.sqlite)
php artisan migrate --seed        # SEED_DEMO=true adds demo data (default in local env)
php artisan storage:link
npm install
npm run build                     # also copies TinyMCE into public/vendor/tinymce
php artisan serve
```

Default login: `admin@ticketing.local` / the `SEED_PASSWORD` value (`password` if not set).
Demo users (when demo data is seeded): `dev1@`, `dev2@`, `customer1@`, `customer2@`, `customer3@ticketing.local`.
You can sign in with the email **or** the mobile number.

## Tests

```bash
php artisan test
```

## How it works (short)

| Topic | Where |
|---|---|
| Roles and access rules | `app/Policies`, `app/Models/User.php` (`scopeCustomersOf`, `accessibleProjectIds`) |
| Top-bar project switcher | `app/Support/ProjectContext.php` |
| Ticket filters, folders, badges | `app/Support/TicketFilter.php`, `app/Support/TicketMenus.php` |
| History and soft delete | `app/Services/TicketService.php`, `ticket_revisions` table |
| Ticket number (`id × 100 + 2 random digits`) | `app/Models/Ticket.php` |
| Jalali / Gregorian dates | `app/Support/Dates.php`, `resources/views/components/date-input.blade.php` |
| Grid column chooser | `app/Support/Grid.php`, `resources/views/components/grid-columns.blade.php` |
| Themes | `resources/css/theme-rtl.css`, `resources/css/theme-ltr.css`, shared `app.css` |

## Deployment (deb10)

Served at `http://ticketing.localkimia.com` (LAN, hosts file → `192.168.1.10`).

- Code: `/var/www/ticketing`, owner `www-data`, `.env` is `chmod 600` and only on the server.
- nginx vhost: [deploy/nginx-ticketing.conf](deploy/nginx-ticketing.conf) (raises PHP upload limits for this site only).
- Database: MariaDB `ticketing`.

Update steps:

```bash
npm run build
tar czf /tmp/ticketing.tgz --exclude=.git --exclude=node_modules --exclude=./vendor \
    --exclude=storage/logs --exclude=.env --exclude='*.sqlite' --exclude='bootstrap/cache/*.php' \
    --exclude=public/storage --exclude=public/hot --exclude='storage/app/public/*' \
    --exclude='storage/app/private/*' --exclude='storage/framework/sessions/*' --exclude='storage/framework/views/*' .
scp /tmp/ticketing.tgz deb10:/tmp/
ssh deb10 'cd /var/www/ticketing && tar xzf /tmp/ticketing.tgz \
  && composer install --no-dev --optimize-autoloader \
  && php artisan migrate --force && php artisan optimize \
  && chown -R www-data:www-data . && systemctl reload php8.4-fpm'
```
