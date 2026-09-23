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
| Themes | Two separate themes, **tied to the language**: `fa` → **RTL theme** (Vazirmatn FD font with Persian digits, purple/teal palette, `bootstrap.rtl`) and `en` → **LTR theme** (Inter font, indigo/cyan palette, `bootstrap`). The theme is not chosen separately — it follows the language. |
| Calendar | Chosen per user in the profile, **independent of the language**: `jalali` (default) or `gregorian`. The DB always stores Gregorian. Jalali input uses `@majidh1/jalalidatepicker`, Gregorian uses native `<input type=date>`. The parser accepts both formats (year < 1700 ⇒ Jalali). |
| Rich text | TinyMCE 7 (self-hosted, GPL licence key) with inline image upload (`POST /editor/upload`). |
| History | Tickets are never updated in place without a trace: each change writes a row to `ticket_revisions` (diff + full snapshot). Delete = **soft delete** (`deleted_at`, `deleted_by`). |
| Ticket number | `number = id * 100 + random(10..99)` → always increasing, integer, with 2 random digits (e.g. `#1047`, `#2083`). |
| Story points | Fibonacci with plain labels: 1 Tiny, 2 Very small, 3 Small, 5 Medium, 8 Large, 13 Very large, 21 Huge. |
| Time | Stored as **minutes** (int). UI input/output is `HH:MM`. "Spent time" is named **Time logged** (Jira wording). |
| Grids | Every table has a column chooser. Visible columns are saved **on the server** per user (`grid_preferences`). |
| Files | Attachments on the private disk, served by an authorised controller. Inline editor images on the public disk. Max 10 MB each. |

## 2. Roles

| Role | Can do |
|---|---|
| **admin** | Everything. Manages all users (admins, developers, customers), all projects, assigns projects to developers and customers, sees all tickets. |
| **developer** | Creates/manages **own** projects (projects they are a member of), sprints of those projects, **own customers** (customers on their projects, or created by them) and assigns them to own projects. Sees tickets of own projects only. Can set **any status at any time**. Can edit/delete tickets (revision + soft delete). |
| **customer** | Sees their projects and their tickets. Creates tickets (status starts at *Pending review*). Can edit/delete own ticket **only while** it is *Pending review*. Can cancel own ticket at any time. |

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
                 start_date, end_date, budget, currency, phone1, phone2, email, website,
                 address, contact_person, notes, created_by, soft deletes
project_user     project_id, user_id                (developers + customers)
sprints          id, project_id, number, name, goal, start_date, end_date, status(planned|active|closed)
tickets          id, number, project_id, sprint_id, type, status, priority, title, content(html),
                 reporter_id, assignee_id, story_points, done_story_points,
                 estimated_minutes, logged_minutes, estimated_cost, cost, due_date,
                 resolved_at, updated_by, deleted_by, soft deletes
ticket_revisions id, ticket_id, user_id, action, changes(json), snapshot(json), created_at
ticket_comments  id, ticket_id, user_id, body, timestamps
attachments      id, ticket_id, user_id, path, original_name, mime, size
ticket_menus     id, user_id, name, filters(json), sort_order      (custom "cartables")
grid_preferences id, user_id, grid_key, columns(json)
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

## 7. Directory map

```
app/Enums              Role, TicketStatus, TicketPriority, TicketType, StoryPoint, ProjectStatus
app/Support            Dates (Jalali/Gregorian), Duration (HH:MM), TicketFilter, Grid, ProjectContext
app/Policies           ProjectPolicy, TicketPolicy, UserPolicy, SprintPolicy
app/Http/Controllers   Auth, Dashboard, Profile, Locale, ProjectSwitch, Project, Sprint,
                       Customer (developer), Admin\User, Ticket, TicketMenu, Attachment,
                       Comment, EditorUpload, GridPreference
resources/css          app.css (shared) + theme-rtl.css + theme-ltr.css
resources/js           app.js (grid, cartable, pickers), editor.js (TinyMCE)
deploy/                nginx vhost
```
