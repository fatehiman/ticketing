# Ticketing — Phases

Design details: [PLAN.md](PLAN.md).

## Phase 1 — Core (this release)

- [x] Laravel 12 project, Bootstrap 5, Vite build, TinyMCE self-hosted
- [x] Languages fa (default) / en with PHP translation files
- [x] Two themes (RTL for fa, LTR for en), switched automatically with the language
- [x] Jalali / Gregorian calendar per user (profile), date pickers for both
- [x] Login by email or mobile, 3 roles: admin, developer, customer
- [x] Profile page: language, calendar, password, avatar (upload/remove); base info read-only for non-admins
- [x] Admin: user management (admins, developers, customers) + project assignment
- [x] Developer: customer management scoped to own projects
- [x] Projects: all fields, logo, members (developers by admin, customers by admin/developer)
- [x] Sprints per project
- [x] Top project switcher (active projects only, "All projects" for developers/admins)
- [x] Tickets: number (id×100 + 2 random digits), status, priority (Jira colours), type, sprint,
      story points (Fibonacci labels), done points, estimated time / time logged (HH:MM),
      estimated cost / cost, due date, rich HTML content with inline images, attachments (10 MB)
- [x] Ticket revisions (full history) + soft delete; customer edit/delete only in *Pending review*
- [x] Ticket folders (cartables) with badges, filter page, custom menus (create / rename / reorder / delete)
- [x] Grid column chooser saved on the server
- [x] Simple ticket comments
- [x] Dashboard with counts
- [x] Demo seeder
- [x] Deploy to deb10 (`ticketing.localkimia.com`)

## Phase 1.1 — Finance and UI polish

- [x] Lighter, colourful themes (pastel gradients for backgrounds/cards, solid-colour buttons)
- [x] Tickets grid default columns: number, title, type, status, priority, sprint, cost (saved choices reset once)
- [x] Grid totals row (money / number / HH:MM) over all filtered records, shown only when such a column is visible
- [x] Customer payments: add / edit / delete by developers of the customer and admins
- [x] Transactions page: payments + costs of done tickets (live, no copied rows), filters, totals,
      remaining, per-project summary; read-only for customers
- [x] Deploy to deb10 and to the waybill VPS (`ticketing.kimiasoft.ir`)

## Phase 2 — Collaboration

- [ ] Rich-text comments with mentions and inline images
- [ ] Email / in-app notifications (status change, new comment, assignment)
- [ ] Time log entries (who, when, how long) that sum into *Time logged*
- [ ] Kanban board per sprint (drag & drop status change)
- [ ] Admin screen for deleted tickets (view / restore)
- [ ] Ticket watchers and labels

## Phase 3 — Reporting

- [ ] Sprint burndown and velocity charts
- [ ] Budget vs. cost report per project
- [ ] Export grid to Excel / CSV
- [ ] Customer satisfaction rating on done tickets

## Phase 4 — Integrations

- [ ] REST API with tokens (Sanctum)
- [ ] Create tickets from email
- [ ] Two-factor login / SSO
