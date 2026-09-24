# Ticketing

A lightweight ticketing and project management app built with **Laravel 12**, Blade and Bootstrap 5.

- Two languages: **Persian (default)** and **English** — PHP translation files in `lang/fa` and `lang/en`.
- Two themes that follow the language: **RTL theme** for Persian, **LTR theme** for English.
- Per-user **calendar** (Jalali or Gregorian), independent of the language.
- Three roles: **admin**, **developer**, **customer**.
- Tickets with status, priority (Jira colours), type, sprint, story points, estimates, time logged, costs,
  due date, rich-text content (TinyMCE with inline image upload) and attachments (10 MB each).
- Full ticket **history** (every edit is saved as a revision) and **soft delete**.
- **Followups**: a ticket is a conversation. Staff and customers reply (rich text + attachments) below the ticket.
  The ticket then **waits for a reply** from the other side: a **red badge** next to each folder's badge, a
  *Waiting for my reply* folder, a toast after login and an **I read it** button. A staff reply has a
  checkbox (on by default) to decide if the customer must answer. Customers cannot reply to closed tickets.
- **Rating**: on a closed ticket (done, cancelled, rejected) a customer gives 1–5 stars and an optional comment.
  One rating per ticket; staff can only read it.
- Ticket folders ("cartables") with **badges**, one search page with many filters, and **custom menus**.
- Every grid has a **column chooser**; the choice is saved on the server. Grids with money or `HH:MM`
  columns have a **totals row** (sums of all filtered records, not only the current page).
- **Transactions**: staff add customer payments; each done ticket with a cost and a due date is shown as a
  cost next to them. Totals (payments, costs, remaining) and a per-project summary. Customers see theirs read-only.
- Light, colourful themes: soft gradients for backgrounds and cards, solid colours for buttons.
- Money is **always a whole number** in every currency: `7,000,000`, never `7,000,000.00`.
- Latin / numeric fields (email, mobile, password, URL, code, amounts, times, dates) are always **LTR**, also in the RTL theme.
- **Bot API** (JSON) for AI bots: create, search / list and read tickets. A developer logs in the bot with a
  one-time link (no password in the chat) and links the bot to one project; tokens last 30 days. See [API.md](API.md).

Design and phases: [PLAN.md](PLAN.md) · [PHASES.md](PHASES.md) · Bot API: [API.md](API.md)

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
| Followups, "waiting for reply", I read it | `app/Http/Controllers/FollowupController.php`, `TicketService::addFollowup()`, `tickets.awaiting_reply` |
| Rating (stars) of closed tickets | `app/Http/Controllers/CommentController.php`, `ticket_comments` table, `TicketPolicy::comment()` |
| Notifications hook (future SMS) | `app/Events/FollowupPosted.php` (no listener yet) |
| History and soft delete | `app/Services/TicketService.php`, `ticket_revisions` table |
| Ticket number (`id × 100 + 2 random digits`) | `app/Models/Ticket.php` |
| Jalali / Gregorian dates | `app/Support/Dates.php`, `resources/views/components/date-input.blade.php` |
| Grid column chooser + totals row | `app/Support/Grid.php`, `resources/views/components/grid-columns.blade.php`, `resources/views/partials/grid-totals.blade.php` |
| Transactions (payments + ticket costs) | `app/Support/Transactions.php`, `app/Http/Controllers/TransactionController.php`, `PaymentController.php`, `app/Policies/PaymentPolicy.php` |
| Themes | `resources/css/theme-rtl.css`, `resources/css/theme-ltr.css`, shared `app.css` |
| Bot API (login link, tokens, tickets JSON) | `routes/api.php`, `app/Http/Controllers/Api/*`, `app/Http/Controllers/ApiAuthController.php` (web page of the link), `app/Http/Middleware/AuthenticateApiToken.php`, `app/Models/ApiToken.php`, `ApiAuthRequest.php` — docs in [API.md](API.md) |

## Deployment

The app runs on two servers with the same code. Build once, then copy the same archive to both.

| Server | URL | Notes |
|---|---|---|
| deb10 (LAN) | `http://ticketing.localkimia.com` | hosts file → `192.168.1.10`, PHP 8.4 |
| waybill VPS | `https://ticketing.kimiasoft.ir` | `130.185.76.10` behind ArvanCloud, Apache mod_php |

### deb10

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

### waybill (`ticketing.kimiasoft.ir`)

- Public DNS → **ArvanCloud CDN** → origin `130.185.76.10` (SSH: `ssh waybill`, port 38010).
- Apache **mod_php 8.4** serves `public/` directly (no PHP-FPM, no systemd unit, no port).
  Vhost: [deploy/apache-ticketing.kimiasoft.ir.conf](deploy/apache-ticketing.kimiasoft.ir.conf) — both `:80` and `:443`
  (self-signed origin cert in `/etc/ssl/{certs,private}/ticketing.kimiasoft.ir.*`, the CDN does not verify it).
- Code: `/var/www/ticketing`, owner `www-data`, `.env` `chmod 600`. MariaDB database and user `ticketing`.
- `.env` has `TRUSTED_PROXIES=*` (real visitor IP for the login rate limit) and `SESSION_SECURE_COOKIE=true`;
  an `https://` `APP_URL` makes every generated URL https.
- No demo data. Admin `admin@ticketing.local`; password in `E:wwwmyLanedentials.md`.

Update steps (same archive as deb10; mod_php needs no reload):

```bash
scp /tmp/ticketing.tgz waybill:/tmp/
ssh waybill 'cd /var/www/ticketing && mariadb-dump ticketing | gzip > /root/ticketing-db-$(date +%F-%H%M).sql.gz \
  && tar xzf /tmp/ticketing.tgz && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader \
  && php artisan migrate --force && php artisan optimize && chown -R www-data:www-data .'
```
