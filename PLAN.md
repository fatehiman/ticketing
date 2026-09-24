# Ticketing — Architecture & Design Plan

A lightweight ticketing and project management app built with **Laravel 12**,
**Blade**, **Bootstrap 5** and a little vanilla JS.

Phases and progress are tracked in [PHASES.md](PHASES.md).

---

## 1. Decisions

| Topic | Decision |
|---|---|
| Framework | Laravel 12, PHP ≥ 8.3, MariaDB (prod) / SQLite (local dev + tests) |
| UI | Server-rendered Blade, Bootstrap 5, Bootstrap Icons, Vite build |
| Languages | Persian (`fa`, **default**) and English (`en`), PHP translation files in `lang/fa/*.php`, `lang/en/*.php` |
| Themes | Two separate themes, **tied to the language**: `fa` → **RTL theme** (Vazirmatn FD font with Persian digits, light purple/teal palette, `bootstrap.rtl`) and `en` → **LTR theme** (Inter font, light indigo/cyan palette, `bootstrap`). The theme is not chosen separately — it follows the language. Both are **light and colourful**: soft pastel gradients for the page, sidebar and cards; **buttons are always one solid colour** (no gradient). |
| Calendar | Chosen per user in the profile, **independent of the language**: `jalali` (default) or `gregorian`. The DB always stores Gregorian. Jalali input uses `@majidh1/jalalidatepicker`, Gregorian uses native `<input type=date>`. The parser accepts both formats (year < 1700 ⇒ Jalali). |
| Rich text | TinyMCE 7 (self-hosted, GPL licence key) with inline image upload (`POST /editor/upload`). |
| History | Tickets are never updated in place without a trace: each change writes a row to `ticket_revisions` (diff + full snapshot). Delete = **soft delete** (`deleted_at`, `deleted_by`). |
| Ticket number | `number = id * 100 + random(10..99)` → always increasing, integer, with 2 random digits (e.g. `#1047`, `#2083`). |
| Story points | Fibonacci with plain labels: 1 Tiny, 2 Very small, 3 Small, 5 Medium, 8 Large, 13 Very large, 21 Huge. |
| Time | Stored as **minutes** (int). UI input/output is `HH:MM`. "Spent time" is named **Time logged** (Jira wording). |
| Grids | Every table has a column chooser. Visible columns are saved **on the server** per user (`grid_preferences`). A grid can have a **totals row** (`Grid::totals()`): one aggregate query over **all filtered records** (not the page). The row is shown only while a money/number/time column with a total is visible. |
| Tickets grid defaults | Number, title, type, status, priority, sprint, cost. Each user can change it. |
| Transactions | Payments live in `payments`. Ticket costs are **not copied**: they are read live from `tickets` with a `UNION ALL`, so a ticket that stops being *done*, loses its cost or due date, or is deleted, disappears from the list and every total at once. |
| Followups | A ticket is a conversation: `ticket_followups` (sender, date/time, HTML body, attachments) shown below the ticket body. `tickets.awaiting_reply` (`staff` / `customer` / null) says which **side** must answer. All staff (admins + developers of the project) are one side, all customers of the project are the other. |
| Rating | `ticket_comments` holds the customer's **rating** of a closed ticket: 1–5 stars + optional text, **one per ticket** (unique `ticket_id`). Old free-text comments were moved to followups. |
| Money | **Always a whole number, for every currency** (IRT, IRR, USD, EUR, AED): `7,000,000`, never `7,000,000.00`. Columns are integers (`budget`, `estimated_cost`, `cost`, `amount`), inputs use `data-money="int"`, validation is `integer`, `Money::format()` shows no decimals. |
| LTR fields | Latin / numeric inputs (email, password, mobile, URL, code, money, `HH:MM`, dates) are `direction: ltr` and left-aligned in both themes (`.ltr-input`, plus every `email/password/url/tel/number/date` input). |
| Bot API | JSON under `/api` for AI bots (create / list / read tickets). Own small token system (no Sanctum): `api_tokens` stores only the **SHA-256 hash**, tokens last **30 days**. Login by link: the bot gets a public `login_url` and a private `secret`; a developer opens the link within 60 s, signs in and picks **one project** that is linked to the token. The bot then trades `request_id` + `secret` for the token (once). Details in [API.md](API.md). |
| Default assignee | A new ticket made by a **developer** (web form or bot token) is assigned to that developer, if they are a member of the project. Tickets made by customers or admins start **unassigned**. The bot can send `assignee: "none"` to skip it. |
| After saving a form | Every create / edit / delete goes **back to the list page the user came from** (folder, filtered list, page number). Each browser tab keeps its last list page in `sessionStorage` (`App\Support\ReturnTo` + `initReturnTo()` in `app.js`); detail (`*.show`) and form (`*.create`, `*.edit`) pages do not change it, and POST forms send it as `_back` (same-site URLs only). A page opened directly (no referrer from this site) has no list page → the default page (*All tickets*, *Users*, …; a new ticket / project opens its own page). |
| Files | Attachments on the private disk, served by an authorised controller. Inline editor images on the public disk. Max 10 MB each. |

## 2. Roles

| Role | Can do |
|---|---|
| **admin** | Everything. Manages all users (admins, developers, customers), all projects, assigns projects to developers and customers, sees all tickets. |
| **developer** | Creates/manages **own** projects (projects they are a member of), sprints of those projects, **own customers** (customers on their projects, or created by them) and assigns them to own projects. Sees tickets of own projects only. Can set **any status at any time**. Can edit/delete tickets (revision + soft delete). |
| **customer** | Sees their projects, their tickets and their transactions (read-only). Creates tickets (status starts at *Pending review*). Can edit/delete own ticket **only while** it is *Pending review*. Can cancel own ticket at any time. |

Followups: staff can reply at any time; customers only while the ticket is **not closed**.
Rating: only customers, only on closed tickets; the customer who rated can change it. Staff only read it.

Payments: admins and developers add them. A developer sees and manages (edit/delete) payments of
**their customers** that have no project or are on one of the developer's projects — also payments
added by another developer. Admins see and manage all payments.

Profile page (all roles): language, calendar, password, profile picture.
Customers and developers cannot edit their own name / email / mobile (read-only).
Developers edit their customers in *Customers*; admin edits everyone.

## 3. Ticket statuses

| Key | fa | en | Notes |
|---|---|---|---|
| `pending_review` | دردست بررسی | Pending review | Only for tickets created by customers |
| `backlog` | درصف انجام | Backlog | Default for developer/admin tickets |
| `in_progress` | درحال انجام | In progress (to do / doing) | |
| `testing` | درحال تست | Testing | |
| `done` | انجام شده | Done / deployed | |
| `cancelled` | لغو شده | Cancelled | Any time |
| `rejected` | رد شده | Rejected | Developer rejects the request |

Priorities (Jira colours): Highest `#CE0000`, High `#EA4444`, Medium `#EA7D24`, Low `#2A8735`, Lowest `#55A557`.

## 4. Data model

```
users            id, first_name, last_name, email, mobile, password, role, avatar_path,
                 locale(fa|en), calendar(jalali|gregorian), current_project_id,
                 is_active, created_by, soft deletes
projects         id, code, name, description(255), logo_path, status(active|on_hold|completed|archived),
                 start_date, end_date, budget(int), currency, phone1, phone2, email, website,
                 address, contact_person, notes, created_by, soft deletes
project_user     project_id, user_id                (developers + customers)
sprints          id, project_id, number, name, goal, start_date, end_date, status(planned|active|closed)
tickets          id, number, project_id, sprint_id, type, status, priority, title, content(html),
                 reporter_id, assignee_id, story_points, done_story_points,
                 estimated_minutes, logged_minutes, estimated_cost(int), cost(int), due_date,
                 resolved_at, updated_by, deleted_by, soft deletes
ticket_revisions id, ticket_id, user_id, action, changes(json), snapshot(json), created_at
tickets          … awaiting_reply(staff|customer|null), awaiting_since
ticket_followups id, ticket_id, user_id, body(html), awaits_reply(bool), timestamps
ticket_comments  id, ticket_id(unique), user_id, rating(1-5), body(nullable), timestamps   (rating)
attachments      id, ticket_id, followup_id(nullable), user_id, path, original_name, mime, size
ticket_menus     id, user_id, name, filters(json), sort_order      (custom "cartables")
grid_preferences id, user_id, grid_key, columns(json)
api_tokens       id, user_id, project_id(nullable = no fixed project), name, token_hash(sha256, unique),
                 last_used_at, expires_at(+30 days)
api_auth_requests id, public_id(in the link), secret_hash(sha256), name, user_id, project_id, api_token_id,
                 opened_at, approved_at, claimed_at      (one bot login attempt)
payments         id, customer_id, project_id(nullable), amount(int, no decimals), paid_on(date),
                 description(500), created_by, updated_by, soft deletes
```

## 5. Project switcher (top bar)

* Shows only **active** projects the user can access.
* Hidden when the user has only one project.
* Developers and admins get **All projects** at the end of the list. Customers must pick one.
* The choice is stored in `users.current_project_id` (null = all projects).
* All pages, menu badges and dashboards follow it.

## 6. Ticket folders (cartables) — one page, many menus

* `/tickets` is a single page: a filter box on top, a grid below, and a **Search** button.
* Every menu item under **Tickets** is just a **saved set of filters** that is loaded into the
  filter box. Each item shows a **badge** with the count of its query.
* Built-in items: *All tickets*, one per status, *Assigned to me*, *Reported by me*.
* Custom items: tick **Create menu (ایجاد کارتابل)** next to Search → JS asks a name → the
  filter set is saved → redirect to the new menu (highlighted, because the query matches).
* Custom items have a small ✎ icon → modal: rename, change order (number list), delete.
* The active menu is found by comparing the **normalised** current filters with each menu's filters.
* Filter `project`: empty → follow the top switcher; set → use only that project.

Filters: keyword, number, project, sprint, statuses, priorities, types, assignee, reporter,
unassigned, created date range, updated date range, due date range, sort, per page.

## 7. Followups and "waiting for reply"

| Event | `awaiting_reply` becomes |
|---|---|
| Customer sends a followup | `staff` |
| Staff sends a followup, box **Wait for the customer's reply** ticked (default) | `customer` |
| Staff sends a followup, box unticked | null |
| The waiting side clicks **I read it** | null (does not change `updated_at`) |

* Replying always removes the ticket from the writer's own red badge (their side has answered).
* Sidebar: every folder shows its count and, in red, how many of its tickets wait for **my side**.
  Built-in folder **Waiting for my reply** = filter `awaiting=me`. Grid rows show a red dot.
* After login a toast shows how many tickets (in all the user's projects) wait for the user's reply.
* `App\Events\FollowupPosted` is fired after each followup — the place to add SMS later.
* Creating a ticket does not set `awaiting_reply` (new customer tickets are in *Pending review*).

## 8. Transactions

* One page `/transactions` for every role. Sidebar: **Finance → Transactions**, and **Add payment** for staff.
* Rows = customer **payments** + **ticket costs**. A ticket is a cost row only while
  `status = done` **and** `cost > 0` **and** `due_date` is set. The row date is the due date.
* Costs belong to customers **by project**: a customer's costs are the costs of their projects.
  Staff filtering by one customer see that customer's payments + the costs of that customer's projects.
* Filters: customer (staff only), description / ticket title, type (payment / cost), date range,
  amount range, projects (multi-select, plus *No project*). Unlike tickets, this page does **not**
  follow the top-bar project switcher (so payments without a project are never hidden).
* Below the grid: **total payments**, **total costs**, **remaining = costs − payments**
  (positive = still to pay, negative = prepayment). All totals are for the filtered records, not the page.
* When the result covers more than one project, a second table shows the same totals per project.
* Amounts of different project currencies are added together as plain numbers; the per-project
  table shows each project's currency.

## 9. Directory map

```
app/Enums              Role, TicketStatus, TicketPriority, TicketType, StoryPoint, ProjectStatus
app/Support            Dates (Jalali/Gregorian), Duration (HH:MM), TicketFilter, Grid (+ totals), ProjectContext, Transactions
app/Policies           ProjectPolicy, TicketPolicy, PaymentPolicy
app/Http/Controllers   Auth, Dashboard, Profile, Locale, ProjectSwitch, Project, Sprint,
                       Customer (developer), Admin\User, Ticket, TicketMenu, Attachment,
                       Comment (rating), Followup, EditorUpload, GridPreference, Transaction, Payment
resources/css          app.css (shared) + theme-rtl.css + theme-ltr.css
resources/js           app.js (grid, cartable, pickers), editor.js (TinyMCE)
deploy/                nginx vhost
```

## 8. Bot API

* Only staff (developers, admins) can log in a bot. The token acts as that user (same project access).
* Login link rules: must be opened within **60 s** of `POST /api/auth/start`; sign-in + project choice within
  **10 min** of start; the bot must take the token within **10 min** of approval; the token is given **once**.
* The link is posted in a group with customers, so the link alone is not enough: taking the token needs the
  `secret` that only the bot has.
* One active project → linked without asking. More → the developer picks one, or *No fixed project*
  (then every call must send `project`).
* Any token problem → `401 {"error": "auth_required"}` so the bot knows to log in again.
* Tickets created by the bot: reporter = the developer, defaults type `task`, status `backlog`, priority `medium`,
  assignee = the developer (when they are a developer of the project),
  written through `TicketService` (revision history like the web).
* Developers see and revoke their bot tokens in **Profile → Bot access (API)**.
