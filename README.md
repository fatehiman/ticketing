# Ticketing

**تیکتینگ — سامانه پشتیبانی کیمیا** (Kimia Support System): a lightweight ticketing and project management app
built with **Laravel 12**, Blade and Bootstrap 5.

- Two languages: **Persian (default)** and **English** — PHP translation files in `lang/fa` and `lang/en`.
- Two themes that follow the language: **RTL theme** for Persian, **LTR theme** for English.
- Per-user **calendar** (Jalali or Gregorian), independent of the language.
- Three roles: **admin**, **developer**, **customer**. The app is a tenant system of developers: the **admin is a
  supervisor** — manages users and projects and reads all tickets, sprints and transactions, but never writes tickets,
  followups, sprints or payments (no *New ticket* button, no bot login).
- Tickets with status, priority (Jira colours), type, sprint, story points, estimates, time logged, costs,
  due date, rich-text content (TinyMCE with inline image upload) and attachments (10 MB each).
- Full ticket **history** (every edit is saved as a revision) and **soft delete**. Customers do not see the history of
  price, time and story points (cost, estimated cost, estimated / logged time, story points, done story points);
  an edit with only those changes is hidden from them. Status, sprint, assignee and the rest stay visible.
- **Followups**: a ticket is a conversation. Staff and customers reply (rich text + attachments) below the ticket.
  The ticket then **waits for a reply** from the other side: a **red badge** next to each folder's badge, a
  *Waiting for my reply* folder, a toast after login and an **I read it** button. A staff reply has a
  checkbox (on by default) to decide if the customer must answer. Customers cannot reply to closed tickets.
- **Rating**: on a closed ticket (done, cancelled, rejected) a customer gives 1–5 stars and an optional comment.
  One rating per ticket; staff can only read it.
- **Sprints** per project, with a **Days** column (end − start + 1, weekends included: 5th → 12th = 8 days). Dates of two sprints of the same project should not overlap (the next sprint starts
  the day after the previous one ends; gaps are fine). This is only a warning: the overlapping dates are **red**
  on the sprints page (end of the earlier sprint and start of the later one), and saving shows a warning.
- Ticket folders ("cartables") with **badges**, one search page with many filters, and **custom menus**.
  A small ✎ next to *Tickets* opens a modal to **reorder** the built-in folders (drag or ↑ ↓) and **show / hide**
  each one, saved per user, with **Reset to default** (default: current order, all shown). Custom menus are not in it.
- **Current sprint** folder (اسپرینت حاضر), also an option of the sprint filter: tickets in any status of every sprint
  whose **dates include today** (both ends count). Overlapping sprints → tickets of all of them. The sprint status is not used.
  A current sprint is **green everywhere** (tickets grid and page, sprints page and project page rows, dashboard, sprint dropdowns).
- **Bulk actions** (developers): check tickets on the current page (the top checkbox checks the whole page), then
  change status, sprint, assignee, priority, type or **cost** (one fixed cost for many small tickets; 0 removes it),
  or delete them in one step. Every change goes into the history.
- Every grid has a **column chooser**; the choice is saved on the server. The tickets grid keeps **one choice per
  folder** (e.g. *Backlog* without cost, *Done* with cost); a folder without its own choice uses the search page choice. Grids with money or `HH:MM`
  columns have a **totals row** (sums of all filtered records, not only the current page).
- **Transactions**: accepted customer payments; each done ticket with a cost is shown as a
  cost next to them. Totals (payments, costs, remaining) and a per-project summary. Customers see theirs read-only.
- **Bills** (صورتحساب‌ها): a developer issues a bill to a customer of a project. Items are done tickets that are not
  on another bill — **with or without a cost** (a ticket without a cost is listed with amount 0, and a manual item
  such as "Cost of phase 1" carries the price of those tickets) — and/or **manual items** (title, amount, details —
  e.g. monthly support) that are saved as done tickets of the project, so they are costs on the transactions page too.
  A **sprint picker** ticks all done tickets of a sprint at once. Bill number starts at 1001.
  Checkboxes tell the customer by **SMS** (template 24562, `[param1]` = bill number) and/or a colourful **email**.
  Paid / partly paid / unpaid: the accepted payments of a customer pay their bills **oldest first**.
- **Payment vouchers** (no payment gateway yet): the customer pays to the developer's **card number / IBAN**
  (set in the developer's profile) and registers the voucher: amount (default = the **whole debt now**, can be less
  or more), pay date, pay time, tracking number, optional bill. It is *pending* until a developer **accepts** it
  (or declines, edits, deletes it). Developers can also register a payment for the customer (accepted at once).
  Only accepted payments count on the transactions page.
- **SMS** through [msgway.com](https://msgway.com) (template based). Every SMS is written to an outbox
  (`sms_messages`) and sent by the **queue worker**, so a slow or broken gateway never slows down the site.
  Failed sends are retried after **1, 5, 10 and 30 minutes**, then marked *failed*; OTP codes only once after 1 minute.
  - customer creates a ticket or writes a followup → every developer of the project (template 24564: project name, ticket number);
  - developer reply that **waits for the customer** → the customer (template 24561: ticket number).
- **Forgot password**: one box takes the email **or** the mobile number (an unknown one shows "not found").
  Email → a reset link (60 minutes) from `no-reply@peppasoft.com`. Mobile → a 6-digit code by SMS (template 3),
  valid 2 minutes; after a **1:50 countdown** the user can ask again: the 2nd request sends the **same code**
  by SMS, the 3rd one by a **voice call**. Limits: 5 requests per email / mobile in 12 h, 10 per IP in 24 h.
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
| Current sprint (by dates), green marker | `Sprint::scopeCurrent()`, `Sprint::isCurrent()`, `resources/views/components/sprint.blade.php` (`<x-sprint>`) |
| Order / show-hide of built-in folders | `TicketMenus::applySettings()`, `FolderSettingsController`, `users.folder_settings`, `resources/views/partials/folders-modal.blade.php`, `initFolders()` in `resources/js/app.js` |
| Bulk actions on tickets | `TicketController::bulk()`, bulk bar in `resources/views/tickets/index.blade.php`, `initBulk()` in `resources/js/app.js` |
| Followups, "waiting for reply", I read it | `app/Http/Controllers/FollowupController.php`, `TicketService::addFollowup()`, `tickets.awaiting_reply` |
| Rating (stars) of closed tickets | `app/Http/Controllers/CommentController.php`, `ticket_comments` table, `TicketPolicy::comment()` |
| SMS (msgway client, outbox, queue job, retries) | `app/Sms/MsgwayClient.php`, `app/Sms/Sms.php` (`Sms::send()`), `app/Jobs/SendSms.php`, `sms_messages` table, template IDs in `config/sms.php` |
| SMS about tickets | `app/Listeners/SendTicketSms.php` (on `TicketCreated` and `FollowupPosted`) |
| Forgot password (email link + SMS code / call) | `app/Http/Controllers/PasswordResetController.php`, `password_otps` table, `app/Mail/PasswordResetLink.php`, `resources/views/auth/{forgot,otp,new-password}.blade.php`, `initCountdown()` in `app.js` |
| Bills, paid status | `app/Http/Controllers/BillController.php`, `app/Models/Bill.php`, `BillItem.php`, `app/Support/Bills.php` (`allocate()`, `debtOf()`), `app/Mail/BillIssued.php`, `initBillForm()` in `app.js` |
| Payment vouchers (pending / accepted / declined) | `app/Http/Controllers/PaymentController.php`, `app/Policies/PaymentPolicy.php`, `resources/views/payments/*` |
| Mail layout (colourful, inline styles) | `resources/views/mail/*` |
| History and soft delete | `app/Services/TicketService.php`, `ticket_revisions` table; customer view: `RevisionPresenter::STAFF_ONLY` / `visibleChanges()` |
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

Background jobs (SMS and mail) need the **queue worker**: systemd unit
[deploy/ticketing-queue.service](deploy/ticketing-queue.service) (`systemctl enable --now ticketing-queue`).
deb10 has no `MSGWAY_API_KEY` (SMS rows end as *failed* with "not set") and `MAIL_MAILER=log`, so it never texts or mails real people.

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
  && chown -R www-data:www-data . && systemctl reload php8.4-fpm && php artisan queue:restart'
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
- Queue worker: systemd unit `ticketing-queue` (same file as deb10).
- `.env` has `MSGWAY_API_KEY` (msgway SMS) and mail through **ger1** Postfix: `MAIL_MAILER=smtp`,
  `MAIL_HOST=smtp.peppasoft.com`, `MAIL_PORT=25`, `MAIL_FROM_ADDRESS=no-reply@peppasoft.com`. ger1 trusts the waybill IP
  `130.185.76.10` (Postfix `mynetworks` and OpenDKIM `InternalHosts`, so the mail is DKIM-signed for `peppasoft.com`);
  the Hetzner cloud firewall allows TCP 25 / 587 from that IP.

Update steps (same archive as deb10; mod_php needs no reload):

```bash
scp /tmp/ticketing.tgz waybill:/tmp/
ssh waybill 'cd /var/www/ticketing && mariadb-dump ticketing | gzip > /root/ticketing-db-$(date +%F-%H%M).sql.gz \
  && tar xzf /tmp/ticketing.tgz && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader \
  && php artisan migrate --force && php artisan optimize && chown -R www-data:www-data . && php artisan queue:restart'
```
