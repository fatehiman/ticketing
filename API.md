# Ticketing — Bot API

A small JSON API for bots (for example an AI Telegram bot in a customer group).
The bot can **create tickets**, **search / list tickets** and **read one ticket**.
No attachments, followups or history.

- Base URL: `https://ticketing.kimiasoft.ir/api` (LAN: `http://ticketing.localkimia.com/api`)
- Send and receive JSON. Every answer has `"ok": true` or `"ok": false`.
- After login, send the token in every call: `Authorization: Bearer <token>`
- Only **developers** can log in a bot. Admins (read-only supervisors) and customers cannot.

## 1. Login (3 steps)

The bot never sees a password. A developer approves the bot in the web app.

```
bot                                 developer                         ticketing
 │ POST /api/auth/start ─────────────────────────────────────────────▶ │
 │ ◀──────────── request_id, secret, login_url ─────────────────────── │
 │ sends login_url in the chat ──▶ │                                   │
 │                                 │ opens login_url (within 60 s) ──▶ │
 │                                 │ signs in, picks a project ──────▶ │
 │                                 │ ◀──── "The bot login was successful"
 │ ◀── "I have logged in" ──────── │                                   │
 │ POST /api/auth/token (request_id + secret) ───────────────────────▶ │
 │ ◀──────────────────────────── token (valid 30 days) ─────────────── │
```

### Step 1 — `POST /api/auth/start`

Body (optional): `{"name": "Telegram bot – ACME group"}` — shown to the developer and in their profile.

```json
{
  "ok": true,
  "request_id": "Yx1…32 chars",
  "secret": "…48 chars…",
  "login_url": "https://ticketing.kimiasoft.ir/bot-login/Yx1…",
  "open_within_seconds": 60,
  "next_step": "…"
}
```

- Send **only `login_url`** to the developer. **Never show the `secret`** — the chat is shared with customers.
  Without the secret nobody can take the token, even with the link.
- The link must be **opened within 60 seconds**. After it is opened, the developer has up to
  **10 minutes** (from step 1) to sign in and choose the project.

### Step 2 — the developer (web app)

The developer opens the link. If not signed in, the login page opens first, then it comes back.

- One active project → it is linked at once.
- More projects → the developer picks one, or *No fixed project*.
- The page says **"The bot login was successful"**. No token is shown on the page.

### Step 3 — `POST /api/auth/token`

Body: `{"request_id": "…", "secret": "…"}`. Call it when the developer says they have logged in.

| HTTP | `status` | Meaning |
|---|---|---|
| 200 | `approved` | Done. Answer has `token`, `expires_at`, `user`, `project`. Save the token. |
| 202 | `pending` | The developer has not finished yet. Ask them, then call again. |
| 410 | `expired` / `already_used` | Start again from step 1. |
| 404 | `not_found` | Wrong `request_id` or `secret`. Start again. |

```json
{
  "ok": true, "status": "approved",
  "token": "tkt_…", "token_type": "Bearer", "expires_at": "2026-10-24T10:00:00+03:30",
  "user": {"id": 3, "name": "Sara Ahmadi", "role": "developer"},
  "project": {"id": 2, "code": "SHOP", "name": "Online shop"}
}
```

The token can be taken **only once**, and must be taken within 10 minutes after approval.

### When the token stops working

Any call with a missing, wrong, expired (30 days) or revoked token returns:

```json
HTTP 401 {"ok": false, "error": "auth_required", "message": "…"}
```

Then run the 3 login steps again. A developer can revoke a bot in **Profile → Bot access (API)**.

## 2. Project

- Token **linked to a project** (normal case): do not send `project`. All calls use that project.
  Sending another project gives `wrong_project`.
- Token with **no fixed project**: send `project` (id or code) in every call, else `project_required`.
  `GET /api/me` lists the projects.

## 3. Calls

Values for `type`, `status` and `priority` accept the **key** (`in_progress`) or the **Persian or English label**
(`درحال انجام`, `In progress`). Dates accept **Gregorian** `2026-09-24` or **Jalali** `1405/07/02`.

### `GET /api/me`

Who the token belongs to, when it expires, its project (and `projects` when there is no fixed project).

### `GET /api/options`

Allowed values: `types`, `statuses`, `priorities`, `story_points`, `sprints` (number, name, status, dates)
and `assignees` (developers of the project: id, name). Call it when unsure about a value.

| Key | fa | en |
|---|---|---|
| **type** | | |
| `task` | تسک | Task |
| `bug` | باگ | Bug |
| `feature` | قابلیت جدید | New feature |
| `improvement` | بهبود | Improvement |
| `support` | پشتیبانی | Support |
| `question` | سوال | Question |
| **status** | | |
| `pending_review` | دردست بررسی | Pending review |
| `backlog` | درصف انجام | Backlog |
| `in_progress` | درحال انجام | In progress |
| `testing` | درحال تست | Testing |
| `done` | انجام شده | Done |
| `cancelled` | لغو شده | Cancelled |
| `rejected` | رد شده | Rejected |
| **priority** | | |
| `highest` / `high` / `medium` / `low` / `lowest` | خیلی بالا / بالا / متوسط / پایین / خیلی پایین | Highest … Lowest |

### `GET /api/tickets` — list and search

All parameters are optional (query string).

| Parameter | Example | Meaning |
|---|---|---|
| `title` | `payment` | title contains the text (`LIKE %text%`) |
| `description` | `timeout` | description contains the text |
| `q` | `login` | title **or** description contains the text, or ticket number |
| `status` | `in_progress,testing` | one or more (comma separated) |
| `type` | `bug` | one or more |
| `priority` | `high,highest` | one or more |
| `sprint` | `4` / `active` / `none` | sprint number, the active sprint, or tickets without a sprint |
| `assignee` | `me` / `none` / `7` / `sara` | assignee id, part of the name, me, or unassigned |
| `number` | `1047` | one ticket number |
| `date_from`, `date_to` | `1405/07/01` | date range (both ends included) |
| `date_field` | `created` (default) / `updated` / `due` | which date the range uses |
| `sort`, `dir` | `created_at`, `desc` | sort by `number`, `title`, `status`, `priority`, `created_at`, `updated_at` (default), `due_date`, `story_points` |
| `page`, `per_page` | `1`, `20` | paging, `per_page` max 100 (default 20) |
| `project` | `SHOP` | only for tokens without a fixed project |

```json
{
  "ok": true,
  "project": {"id": 2, "code": "SHOP", "name": "Online shop"},
  "total": 1, "page": 1, "per_page": 20, "last_page": 1,
  "tickets": [{
    "number": 1047, "title": "Payment page error",
    "type": "bug", "type_label": "باگ", "status": "in_progress", "status_label": "درحال انجام",
    "priority": "high", "priority_label": "بالا", "sprint": 4, "sprint_name": "Checkout",
    "assignee": "Sara Ahmadi", "reporter": "Ali Rezaei", "due_date": "2026-10-01",
    "created_at": "2026-09-20 10:15", "created_at_jalali": "1405/06/29 10:15",
    "updated_at": "2026-09-23 18:02", "url": "https://ticketing.kimiasoft.ir/tickets/1047"
  }]
}
```

### `GET /api/tickets/{number}` — one ticket

Same fields as the list, plus: `project`, `description` (plain text), `story_points`, `done_story_points`,
`estimated_time` / `logged_time` (`HH:MM`), `estimated_cost`, `cost`, `currency`, `due_date_jalali`,
`resolved_at`, `awaiting_reply_from` (`staff` / `customer` / null), `followups_count`, `attachments_count`.

### `POST /api/tickets` — create a ticket

Only `title` is required.

| Field | Default | Notes |
|---|---|---|
| `title` | — | required, max 255 |
| `description` | empty | plain text (new lines are kept) or simple HTML |
| `type` | `task` | key or label |
| `status` | `backlog` | key or label |
| `priority` | `medium` | key or label |
| `sprint` | none | sprint number, `active`, or part of the sprint name |
| `assignee` (or `assignee_id`) | you, if you are a developer of the project; else none | `me`, `none` (unassigned), developer id, or part of the name |
| `story_points` | none | 1, 2, 3, 5, 8, 13, 21 |
| `due_date` | none | Gregorian or Jalali |
| `estimated_time` | none | `HH:MM` |
| `estimated_cost` | none | whole number (no decimals) |
| `project` | token project | only for tokens without a fixed project |

```json
POST /api/tickets
{"title": "Customer cannot pay with card", "type": "باگ", "priority": "high", "description": "Error 500 after 3D secure."}

HTTP 201
{"ok": true, "message": "Ticket #1147 created.", "ticket": { …same as GET /api/tickets/1147… }}
```

The ticket reporter is the developer who logged in the bot. It is saved in the ticket history like a web edit.

## 4. Errors

```json
{"ok": false, "error": "invalid_value", "message": "Unknown type \"x\". Allowed: task, bug, feature, improvement, support, question."}
```

| HTTP | `error` | What to do |
|---|---|---|
| 401 | `auth_required` | log in again (section 1) |
| 403 | `forbidden` | the user lost access to the project — log in again |
| 404 | `not_found`, `project_not_found`, `sprint_not_found`, `assignee_not_found` | check the value (`GET /api/options`) |
| 422 | `validation` (with `errors` per field), `invalid_value`, `project_required`, `wrong_project` | fix the input |
| 429 | `too_many_requests` | wait a minute (limits: 10/min for login calls, 120/min for the rest) |

## 5. curl example

```bash
curl -s -X POST https://ticketing.kimiasoft.ir/api/auth/start -H 'Content-Type: application/json' -d '{"name":"My bot"}'
# … developer opens login_url and signs in …
curl -s -X POST https://ticketing.kimiasoft.ir/api/auth/token -H 'Content-Type: application/json' \
     -d '{"request_id":"…","secret":"…"}'
curl -s https://ticketing.kimiasoft.ir/api/tickets?status=in_progress -H 'Authorization: Bearer tkt_…'
curl -s -X POST https://ticketing.kimiasoft.ir/api/tickets -H 'Authorization: Bearer tkt_…' \
     -H 'Content-Type: application/json' -d '{"title":"Hello from the bot"}'
```
