# E-5 Pengawasan, Rapat, Risiko & Perubahan

Module E-5 of the PT Anaheim Nimbus Universal ERP project: weekly meetings, MoM,
action items, and the risk/change data feed for D-3. Requirements are in
[PRD.md](PRD.md), the API contract in [openapi.yaml](openapi.yaml).

**Stack:** Laravel 13, PHP 8.3+, MySQL (tests use SQLite in memory), Blade.

## Prerequisites

- PHP 8.3 or newer with `pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`
- Composer 2
- MySQL 8 (or MariaDB)
- Node.js, only for linting `openapi.yaml`

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create an empty database `e5_pengawasan` in MySQL. Then set `DB_USERNAME` and
`DB_PASSWORD` in `.env`. Sessions and cache are stored in files, so no
`sessions`, `cache` or `jobs` tables are needed.

```bash
php artisan migrate:fresh --seed   # E-5 tables + fixed fictional data (PRD §10)
php artisan serve                  # http://localhost:8000
```

Open http://localhost:8000/dev-login and pick a mock account.

F-1 accounts and D-1 projects do **not** live in the database. They are read from
`database/mocks/*.json` while `INTEGRATION_MODE=mock` (see `config/e5.php`).

| Account | Role | Projects |
|---|---|---|
| `acc-pm-0007` | PM | PRJ-2026-014, PRJ-2026-015 (inactive) |
| `acc-tl-0042` | TL | PRJ-2026-014 |
| `acc-tw-0091` | TW | PRJ-2026-014 |
| `acc-tw-0092` | TW | PRJ-2026-015 (inactive) |

## Calling the API

Base URL `http://localhost:8000/api/v1`. Until F-1 publishes its token scheme, the token
is `mock-<accountId>`:

```bash
curl -H "Authorization: Bearer mock-acc-pm-0007" http://localhost:8000/api/v1/me
curl -H "Authorization: Bearer mock-acc-pm-0007" "http://localhost:8000/api/v1/projects/PRJ-2026-014/meetings?status=DONE"
```

Responses are returned without a `data` wrapper. Every error is `{ "code", "message" }`.

## Tests and lint

```bash
php artisan test                      # SQLite in memory, no MySQL needed
npx @redocly/cli@1 lint openapi.yaml
```

CI (`.github/workflows/ci.yml`) runs both on every push and pull request.

## Adding an endpoint

Follow [DESIGN.md](DESIGN.md) (house rules for code and UI; AI agents read it through
`AGENTS.md` / `CLAUDE.md`).
Copy the reference endpoint `GET /projects/{projectId}/meetings`:
[MeetingController](app/Http/Controllers/Api/MeetingController.php),
[ListMeetingsRequest](app/Http/Requests/ListMeetingsRequest.php),
[MeetingPolicy](app/Policies/MeetingPolicy.php),
[MeetingResource](app/Http/Resources/MeetingResource.php),
[MeetingListTest](tests/Feature/MeetingListTest.php).
Update `openapi.yaml` in the same pull request.
