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
- [x] Customer payments: add / edit / delete by developers of the customer
- [x] Transactions page: payments + costs of done tickets (live, no copied rows), filters, totals,
      remaining, per-project summary; read-only for customers
- [x] Deploy to deb10 and to the waybill VPS (`ticketing.kimiasoft.ir`)

## Phase 1.2 — Conversations and LTR fields

- [x] Money without decimals everywhere (all currencies): integer columns, inputs, validation, display
- [x] Latin / numeric fields are LTR (and left-aligned) in the RTL theme too
- [x] Ticket followups (replies) with rich text and attachments, from staff and customers
- [x] "Waiting for reply": red badge next to each folder badge, *Waiting for my reply* folder, grid dot,
      login toast, **I read it** button; staff checkbox to not wait for the customer
- [x] Customers cannot reply to closed tickets
- [x] Comments replaced by a customer rating (1–5 stars + optional text) on closed tickets, one per ticket;
      old comments moved to followups
- [x] `FollowupPosted` event as the hook for future SMS notifications

## Phase 1.3 — Bot API

- [x] Ticket type *task* in Persian is now «تسک»
- [x] JSON API for AI bots: `GET /api/me`, `GET /api/options`, `GET /api/tickets` (filters: sprint, type, status,
      priority, assignee, date range, title / description `LIKE`), `GET /api/tickets/{number}`, `POST /api/tickets` (only title required)
- [x] Bot login by link: `POST /api/auth/start` → developer opens the link (60 s), signs in, picks the project →
      `POST /api/auth/token` gives a 30-day token linked to that project
- [x] Profile: list and revoke bot tokens
- [x] Docs for the bot: [API.md](API.md)

## Phase 1.4 — Form flow

- [x] New tickets are assigned to the logged-in developer by default (web form and bot API); customers'
      tickets start unassigned
- [x] After saving (create / edit / delete) every form goes back to the list page the user came from
      (folder, filters, page); opened directly → the default list (*All tickets*, *Users*, …)

## Phase 1.5 — Bot login with a fixed project

- [x] `POST /api/auth/start` takes `require_project: true`: the approval page then hides *No fixed project*,
      so the token is always linked to one project (used by the BrainyMemo Telegram bot)

## Phase 1.6 — Admin is a read-only supervisor

- [x] Admin cannot create, edit, delete, change status of or reply to tickets, cannot press *I read it*, and cannot
      write sprints or payments (policies + `role:developer` routes; buttons hidden). Admin still manages users and projects
- [x] Only developers can log in a bot; admin tokens stop working
- [x] Production data: tickets / revisions made by admin by mistake moved to the only developer

## Phase 1.7 — Bulk actions and per-folder columns

- [x] Developers check tickets on one page (or all of the page with the top checkbox) and change status, sprint,
      assignee, priority or type, or delete them, in one step. A sprint or assignee of another project is skipped
- [x] Each ticket folder (built-in or custom) keeps its own visible columns; the search page choice is the fallback
- [x] Sprint date conflicts: sprints of one project must not overlap (the next one starts the day after the previous
      one ends; gaps are fine). Only a warning: the overlapping dates are red on the sprints page, and saving shows a warning
- [x] *Current sprint* folder and sprint filter option: tickets of every sprint whose dates include today
- [x] Current sprints are green everywhere (grids, ticket page, dashboard, dropdowns)
- [x] Customers do not see history of price, time and story points (other changes stay visible)
- [x] Built-in folders: ✎ next to *Tickets* → modal to change their order and show / hide them, with *Reset to default*

## Phase 1.8 — SMS, password recovery, bills and vouchers

- [x] Tagline is now «سامانه پشتیبانی کیمیا» (main title stays «تیکتینگ»)
- [x] msgway SMS gateway: outbox table + queue worker (`ticketing-queue` systemd unit), retries 1 / 5 / 10 / 30 min
      (OTP: 1 min once), no retry on a bad request
- [x] SMS to developers when a customer creates a ticket or writes a followup (template 24564)
- [x] SMS to the customer when a developer's reply waits for them (template 24561)
- [x] Forgot password by email (reset link from `no-reply@peppasoft.com` through ger1) or mobile (6-digit code,
      1:50 countdown, same code on resend, 3rd time by a voice call), with rate limits
- [x] Bills: developer page to issue a bill from done tickets and manual items, SMS (template 24562) and colourful
      email to the customer, paid / partly paid / unpaid (payments pay the oldest bill first), print
- [x] Bills page for customers; payment vouchers (amount, pay date, time, tracking number) with the developer's card
      number / IBAN; developers accept / decline / edit / delete vouchers or add one for the customer
- [x] Developer bank details in the profile

## Phase 1.9 — Bills for many tickets

- [x] Done tickets without a cost can go on a bill (amount 0); a manual item carries the price (e.g. "Cost of phase 1")
- [x] Sprint picker on the new-bill page: ticks all done tickets of the sprint that are not on a bill
- [x] Bulk action **Set cost**: one fixed cost for many selected tickets (0 removes the cost)
- [x] Bulk actions ask for confirmation only for delete
- [x] The owner's folder order / show-hide and per-folder columns are the default for everyone (reset once for all users)
- [x] New-bill page: ticket list in pages of 25 with search; ticks kept across pages, "Select all" / "Clear selection"

## Phase 2 — Collaboration

- [ ] Mentions (@user) in followups
- [ ] More notifications (status change, assignment, voucher accepted) — SMS templates must be approved first
- [ ] Admin page for the SMS outbox (`sms_messages`) and failed jobs
- [ ] Time log entries (who, when, how long) that sum into *Time logged*
- [ ] Kanban board per sprint (drag & drop status change)
- [ ] Admin screen for deleted tickets (view / restore)
- [ ] Ticket watchers and labels

## Phase 3 — Reporting

- [ ] Sprint burndown and velocity charts
- [ ] Budget vs. cost report per project
- [ ] Export grid to Excel / CSV
- [ ] Customer satisfaction report (average stars per project / developer)

## Phase 4 — Integrations

- [ ] Bot API: update ticket (status, assignee), add followup
- [ ] Online payment gateway (today: bank vouchers)
- [ ] Create tickets from email
- [ ] Two-factor login / SSO
