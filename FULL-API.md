# Ticketing — Full access API

A JSON API for **scripts and AI assistants that work for a person**, not for a bot in a customer group.
One key reaches **all projects of the user**. It can **create, read, update and delete tickets**, and
**create, read and update projects**.

This is a separate API from the bot API ([API.md](API.md)). The bot API is not changed.

| | Bot API ([API.md](API.md)) | Full access API (this file) |
|---|---|---|
| Base path | `/api` | `/api/full` |
| Login | 3 steps, developer approves, token 30 days | one **key** made in *Profile*, no expiry, you can rotate it |
| Header | `Bearer tkt_…` | `Bearer tkf_…` |
| Project | locked to one project by the token | **`project` is sent in the payload** (mandatory where a project is needed) |
| Tickets | create, list, read | create, list, read, **update, delete** |
| Projects | — | list, read, **create, update** |
| Who | developers | developers and admins (admins are read-only for tickets) |

- Base URL: `https://ticketing.kimiasoft.ir/api/full` (LAN: `http://ticketing.localkimia.com/api/full`)
- Send and receive JSON. Every answer has `"ok": true` or `"ok": false`.
- Header in every call: `Authorization: Bearer tkf_…`

## 1. Get a key

1. Sign in to the web app and open **Profile**.
2. In the box **Full access API key** click **Create key**.
3. Copy the key. **It is shown only once** (only a hash is stored).
4. **Rotate key** makes a new key and the old one stops working at once. **Delete key** removes it.

One key per user. The key has the same access as the user: the projects the user is a member of
(an admin sees all projects). Keep it secret: it can change and delete tickets.

A wrong, rotated or deleted key, or an inactive user, gives:

```json
HTTP 401 {"ok": false, "error": "auth_required", "message": "…"}
```

A bot token (`tkt_…`) does not work here, and a full key does not work on `/api`.

## 2. The `project` field

A project is named by its **id** (`2`), **code** (`SHOP`) or exact **name** (`Online shop`, not case sensitive).
If two projects have the same name, use the id or code (`project_ambiguous`).

| Call | `project` |
|---|---|
| `GET /options`, `GET /tickets`, `POST /tickets` | **mandatory** (query string for GET, JSON body for POST) → else `project_required` |
| `GET/PATCH/DELETE /tickets/{number}` | not needed (a ticket number is unique). If you send it, it must match → else `wrong_project` |
| `/projects/...` | the project is in the URL: `/projects/{id or code or name}` |

Unknown project, or a project the user cannot see → `404 project_not_found`.

## 3. Values

Same as the bot API. `type`, `status`, `priority` accept the **key** (`in_progress`) or the Persian / English label.
Dates accept Gregorian `2026-09-24` or Jalali `1405/07/02`. Money is a whole number (no decimals).
Keys and labels are in [API.md](API.md) section 3, or call `GET /api/full/options?project=SHOP`.

## 4. Calls

### `GET /api/full/me`

The user, the end of the key (`key_hint`) and `projects` (id, code, name) the key can use.

### `GET /api/full/options?project=SHOP`

Allowed `types`, `statuses`, `priorities`, `story_points`, `sprints` and `assignees` of the project.

### Projects

| Call | Meaning |
|---|---|
| `GET /projects` | list. Optional: `q` (name or code), `status`, `page`, `per_page` (max 100, default 50) |
| `GET /projects/{project}` | one project, with `developers` and `customers` (id, name) |
| `POST /projects` | create |
| `PATCH /projects/{project}` | update only the sent fields (`PUT` is the same) |

There is no delete.

Fields of create / update: `name` and `code` (**required on create**; code is upper case letters, digits, `-`, `_`,
max 12, unique), `description`, `status` (`active` default, `on_hold`, `completed`, `archived`), `start_date`, `end_date`,
`budget`, `currency` (`IRT` default, `IRR`, `USD`, `EUR`, `AED`), `phone1`, `phone2`, `email`, `website`,
`contact_person`, `address`, `notes`, and the member lists:

- `customers`: array of customer user ids (only customers you can see).
- `developers`: **admin only**. A developer who creates a project is added as its developer automatically.
- On update, a list that is **not sent** does not change; a list that is sent **replaces** the old one.
- On update, a developer can only change projects they are a member of; an admin can change any project.

```json
POST /api/full/projects
{"name": "Online shop", "code": "shop", "currency": "IRT", "budget": 150000000}

HTTP 201
{"ok": true, "message": "Project SHOP created.", "project": {"id": 7, "code": "SHOP", "name": "Online shop", "status": "active", "budget": 150000000, …, "developers": [{"id": 3, "name": "Sara Ahmadi"}], "customers": []}}
```

### Tickets

| Call | `project` | Meaning |
|---|---|---|
| `GET /tickets` | **mandatory** | list and search |
| `POST /tickets` | **mandatory** | create |
| `GET /tickets/{number}` | no | read one |
| `PATCH /tickets/{number}` | no | update only the sent fields (`PUT` is the same) |
| `DELETE /tickets/{number}` | no | delete (soft delete: the history stays) |

**List / search** and **create** work exactly like the bot API (same filters, same fields, same answer) — see
[API.md](API.md) sections 3 (`GET /api/tickets`, `POST /api/tickets`). The only difference is `project`.

**Update** fields (all optional, send at least one): `title`, `description`, `type`, `status`, `priority`,
`sprint` (number, `active`, part of the name, or `none` to clear), `assignee` / `assignee_id` (`me`, id, part of the name,
or `none`), `story_points` (1, 2, 3, 5, 8, 13, 21), `done_story_points`, `due_date`, `estimated_time`, `logged_time`
(`HH:MM`), `estimated_cost`, `cost`. Send `null` to clear a field.
Every change is saved in the ticket history. A change to *Done* sends the customer an SMS, like in the web app.

```json
PATCH /api/full/tickets/1147
{"status": "done", "logged_time": "02:30", "cost": 500000}

HTTP 200
{"ok": true, "message": "Ticket #1147 updated.", "ticket": { …same as GET /api/full/tickets/1147… }}
```

The reporter of a ticket created with the key is the key's user. When `assignee` is not sent on create,
the ticket is assigned to the key's user if they are a developer of the project.

## 5. Errors

Same format as the bot API: `{"ok": false, "error": "…", "message": "…"}`.

| HTTP | `error` | What to do |
|---|---|---|
| 401 | `auth_required` | create or rotate the key in *Profile* |
| 403 | `forbidden` | the user is not allowed (an admin cannot create, edit or delete tickets) |
| 404 | `not_found`, `project_not_found`, `sprint_not_found`, `assignee_not_found` | check the value |
| 422 | `validation` (with `errors` per field), `invalid_value`, `project_required`, `project_ambiguous`, `wrong_project` | fix the input |
| 429 | `too_many_requests` | wait a minute (limit: 120 calls per minute) |

## 6. curl example

```bash
KEY=tkf_…
BASE=https://ticketing.kimiasoft.ir/api/full
curl -s $BASE/me -H "Authorization: Bearer $KEY"
curl -s "$BASE/tickets?project=SHOP&status=in_progress" -H "Authorization: Bearer $KEY"
curl -s -X POST $BASE/tickets -H "Authorization: Bearer $KEY" -H 'Content-Type: application/json' \
     -d '{"project":"SHOP","title":"Payment page error","type":"bug","priority":"high"}'
curl -s -X PATCH $BASE/tickets/1147 -H "Authorization: Bearer $KEY" -H 'Content-Type: application/json' \
     -d '{"status":"done"}'
curl -s -X DELETE $BASE/tickets/1147 -H "Authorization: Bearer $KEY"
curl -s -X PATCH $BASE/projects/SHOP -H "Authorization: Bearer $KEY" -H 'Content-Type: application/json' \
     -d '{"status":"on_hold"}'
```
