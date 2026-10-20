# PRD — E-5 Monitoring, Meetings, Risks & Changes

| | |
|---|---|
| Module | E-5 — Pengawasan, rapat, risiko, dan perubahan |
| Client | PT Anaheim Nimbus Universal (ERP project) |
| Team | Kelompok E-5, class 5-E |
| Author | Arya Raihan Hanif |
| Version | v0.3 — 2026-10-20 |
| Status | Draft — barebones implementation, slice 1 |
| Stack | Laravel (decided by the team) — see §13 |

**Changes in v0.3:** backlog references follow Backlog v0.2 (BL-01 to BL-13); TL can read all action items of the project (as in Backlog BL-05 and FTR-K05-02); non-formal adjustments (`RECORDED`) are part of the change log sent to D-3; §15 owners follow Backlog v0.2.

**Changes in v0.2:** stack decided (Laravel, MySQL, Blade) and §13 rewritten as the technical baseline; §9 adapters described in Laravel terms; §8 states where `openapi.yaml` lives; new §15 on who sets up what; §14 adds the OpenAPI lint check.

Sources this PRD is built from: the work-division document (*Pembagian pekerjaan*), the company SOP deck (Level 1 Pengendalian, SOP-06, WI-05, WI-06, RACI), the E-5 Backlog, Peta Aktor Proses, Spesifikasi Fitur Minimum, Skema Data Awal, Daftar Kontrak API, and the meeting-3 OpenAPI document. Where this PRD differs from those, it says so.

---

## 1. Purpose

Every week the PM, TL, TW and the client (User) hold a project meeting (SOP-06). Today the outputs of that meeting — minutes (MoM), action items, risks/issues and the change log — live in separate places. Decisions are hard to trace, delays are noticed late, and changes are not linked to the baseline or tasks.

E-5 puts that loop into one system:

**meeting → MoM → TL review → action items → change requests → risks/issues → project indicator**

## 2. Scope boundary

E-5 owns only what the work-division document assigns to it: weekly meetings, MoM, decisions/action items, risks and issues, change requests with impact and approval, the change log, and the safe/late indicator.

E-5 **does not** build, store or change anything owned by another group:

| Not ours | Owner | What E-5 does instead |
|---|---|---|
| Login, accounts, roles | F-1 | Reads identity and role; uses a mock until F-1 is ready |
| Project registration and status | D-1 | Reads project list/status; uses a mock until D-1 is ready |
| Baseline (scope, timeline, targets) | E-3 | Stores a link only (slice 2+) |
| Tasks and progress | E-4 | Stores a link only (slice 2+) |
| Monthly report, closing | D-3 | Provides raw data via API-K05-09 (slice 4) |

Also out of scope for now: automatic notifications/escalation, KPI dashboard, MoM export to PDF.

## 3. Users and roles

All users are the client company's staff. The development team is not a user.

| Role | What they do in E-5 |
|---|---|
| **PM** — Project Manager | Schedules meetings, marks them done/cancelled, reads everything in their projects; later decides CRs and manages risks |
| **TL** — Technical Lead | Reviews MoM (finalize or send back for revision); later analyzes CR impact |
| **TW** — Technical Writer | Writes the MoM, submits it for review |
| **User** — client | Attends meetings; **no login**, recorded by name only (assumption, see §12) |

PM, TL and TW can also be the **PIC** of an action item and update its status.

## 4. Delivery slices

| Slice | Backlog | Contents | Depends on |
|---|---|---|---|
| **1 — this PRD** | BL-01 to BL-05 | Meetings, MoM, TL review, action items | F-1, D-1 (mocked) |
| 2 | BL-06 to BL-09 | Change requests (`RECORDED` adjustments and formal CRs), impact analysis, decision, change log | E-3, E-4 (mocked) |
| 3 | BL-10 to BL-12 | Risks/issues, `ON_TRACK`/`DELAYED` indicator | E-3, E-4 (mocked) |
| 4 | BL-13 | API-K05-09 for D-3; replace mocks with real F-1/D-1/E-3/E-4 | All of the above |

Slices 2–4 get their own PRD section when slice 1 is done. Only slice 1 is specified in detail below.

---

## 5. Slice 1 — user stories and acceptance criteria

### US-1 Schedule a meeting (BL-01, SOP-06)
*As a PM, I schedule a weekly meeting for a project with its participants, so the meeting is officially recorded.*

- Only a PM can create a meeting.
- Only **active** projects (from D-1) can be selected. An inactive or unknown project is rejected.
- Required: project, title, scheduled date/time, at least one participant.
- Internal participants are picked from project accounts (F-1). The client is added as a free-text name.
- The same person cannot be added twice to one meeting.
- A new meeting has status `SCHEDULED`.
- The PM can mark a `SCHEDULED` meeting as `DONE` or `CANCELLED`. No other transitions.

### US-2 Write the MoM (BL-02, BL-03, WI-05)
*As a TW, I write the MoM for a finished meeting, so its decisions are documented.*

- Only a TW can create or edit a MoM.
- A MoM can only be created for a meeting with status `DONE`. One meeting has at most one MoM.
- The form follows the 8 items of WI-05:
  1. Meeting date (required)
  2. Participants — TW ticks who attended
  3. Discussion (required)
  4. Decision (required)
  5–7. Action items — each needs a description, a PIC (PM, TL or TW of the project) and a deadline not earlier than the meeting date
  8. Change/adjustment (optional free text; turning it into a CR is slice 2)
- Saving with an empty date, discussion or decision is rejected with a message naming the empty fields (FTR-K05-01).
- The TW can edit while the MoM is `DRAFT` or `REVISION`, then **submit for review** (`IN_REVIEW`).
- A non-TW user who opens the MoM form gets "access denied" (FTR-K05-01).

### US-3 Review the MoM (BL-04, WI-05 "MoM → TL")
*As a TL, I approve the MoM or send it back with notes, so it is accurate before anyone acts on it.*

- Only a TL can review, and only a MoM in `IN_REVIEW`.
- **Approve** → `FINAL`. `finalizedAt` is set automatically.
- **Request revision** → `REVISION`. A review note is required and is shown to the TW.
- A `FINAL` MoM cannot be edited by anyone. Corrections go into the next meeting's MoM (or a CR in slice 2).

### US-4 Follow up action items (BL-05, WI-05)
*As the PIC of an action item, I see my items and update their status, so follow-up is tracked.*

- Action items appear in lists only after their MoM is `FINAL`.
- "My action items" shows items where I am the PIC, with deadline and status.
- The PM and TL see all action items of their projects (read-only for items they are not PIC of). A TW sees only their own.
- Only the PIC can change an item's status: `TODO` → `IN_PROGRESS` → `DONE` (forward only — see §12).
- **Overdue** is calculated, not stored: deadline has passed (WIB) and status is not `DONE`. It shows as a badge next to the real status, and the item can still be updated.
- An unknown or empty status is rejected; the item keeps its last valid status (FTR-K05-02).
- A user who is not the PIC cannot change the item; the control is disabled and the API returns 403 (FTR-K05-02).

### Status history (all of the above)
Every status change of a meeting, MoM or action item is recorded automatically with who, when, from/to and an optional note. History can't be edited or deleted.

---

## 6. Status flows and permissions

```
Meeting:      SCHEDULED ──► DONE
                       └──► CANCELLED                        (PM)

MoM:          DRAFT ──► IN_REVIEW ──► FINAL                  (TW submits, TL approves)
                            │
                            └──► REVISION ──► IN_REVIEW      (TL sends back, TW resubmits)

Action item:  TODO ──► IN_PROGRESS ──► DONE                  (PIC only)
              overdue = dueDate < today (WIB) AND status ≠ DONE   (computed)
```

| Action | PM | TL | TW |
|---|:-:|:-:|:-:|
| Create meeting, mark done/cancelled | ✓ | | |
| Read meetings of own projects | ✓ | ✓ | ✓ |
| Create / edit MoM (DRAFT, REVISION) | | | ✓ |
| Submit MoM for review | | | ✓ |
| Approve / request revision | | ✓ | |
| Read MoM of own projects | ✓ | ✓ | ✓ |
| Update action item status | own items | own items | own items |
| Read all action items of project | ✓ | ✓ | own |

"Own projects" = projects where the account is a member. Until F-1/D-1 provide membership, the mock defines it (§9).

---

## 7. Data model (slice 1)

Follows the Skema Data Awal, with one change: people are referenced by **`accountId` from F-1**, not `employeeId` from F-2, because F-2 and F-6 are not E-5 dependencies in the work-division document. *The Skema still says `employeeId`/`clientContactId` and needs the same update.*

Conventions: E-5 IDs are UUID strings; other groups' IDs are stored as strings in the owner's format. Datetimes are ISO 8601 in UTC, shown in WIB (UTC+7). Dates are `YYYY-MM-DD`. Enums are uppercase English. `null` means "not filled yet".

**Meeting** (ENT-K05-01)
| Field | Type | Req | Notes |
|---|---|:-:|---|
| meetingId | UUID | ✓ | PK |
| projectId | string | ✓ | D-1; must be active at creation |
| title | string ≤150 | ✓ | |
| scheduledAt | datetime | ✓ | |
| location | string ≤200 | | Room or online link |
| status | enum | ✓ | SCHEDULED, DONE, CANCELLED |
| createdBy | string | ✓ | F-1 account, role PM |
| createdAt, updatedAt | datetime | ✓ | System |

**MeetingParticipant** (ENT-K05-02)
| Field | Type | Req | Notes |
|---|---|:-:|---|
| participantId | UUID | ✓ | PK |
| meetingId | UUID | ✓ | FK |
| participantType | enum | ✓ | INTERNAL, CLIENT |
| accountId | string | if INTERNAL | F-1; unique per meeting |
| displayName | string ≤150 | if CLIENT | Client name; no account |
| attended | boolean | ✓ | Default false; set by TW in the MoM |

**MoM** (ENT-K05-03)
| Field | Type | Req | Notes |
|---|---|:-:|---|
| momId | UUID | ✓ | PK |
| meetingId | UUID | ✓ | FK, unique (1 meeting = 1 MoM); meeting must be DONE |
| meetingDate | date | ✓ | WI-05 #1 |
| discussion | text | ✓ | WI-05 #3 |
| decision | text | ✓ | WI-05 #4 |
| changeSummary | text | | WI-05 #8 |
| status | enum | ✓ | DRAFT, IN_REVIEW, REVISION, FINAL |
| writtenBy | string | ✓ | F-1 account, role TW |
| reviewedBy | string | | F-1 account, role TL |
| reviewNote | text | if REVISION | |
| finalizedAt | datetime | | Set on FINAL |
| createdAt, updatedAt | datetime | ✓ | System |

**ActionItem** (ENT-K05-04)
| Field | Type | Req | Notes |
|---|---|:-:|---|
| actionItemId | UUID | ✓ | PK |
| momId | UUID | ✓ | FK |
| description | text | ✓ | WI-05 #5 |
| picAccountId | string | ✓ | WI-05 #6; F-1 account with role PM/TL/TW in the project |
| dueDate | date | ✓ | WI-05 #7; ≥ meetingDate |
| status | enum | ✓ | TODO, IN_PROGRESS, DONE; default TODO |
| completedAt | datetime | | Set on DONE |
| createdAt, updatedAt | datetime | ✓ | System |

**StatusHistory** (ENT-K05-08)
| Field | Type | Req | Notes |
|---|---|:-:|---|
| historyId | UUID | ✓ | PK |
| entityType | enum | ✓ | MEETING, MOM, ACTION_ITEM (more in later slices) |
| entityId | UUID | ✓ | |
| fromStatus | string | | null on creation |
| toStatus | string | ✓ | |
| changedBy | string | ✓ | F-1 account |
| changedAt | datetime | ✓ | System |
| note | text | | e.g. review note |

---

## 8. API (slice 1)

Base path `/api/v1`. Every request carries `Authorization: Bearer <token>` (mock token in slice 1). These operations are **internal** (used by E-5's own pages) and match API-K05-01 to API-K05-04 in the Daftar Kontrak API.

| ID | Method and path | Who | Purpose |
|---|---|---|---|
| — | `GET /me` | any | Current account, role, member projects (mock identity) |
| — | `GET /projects` | any | Active projects the user belongs to (via D-1 adapter) |
| K05-01 | `POST /projects/{projectId}/meetings` | PM | Create meeting with participants |
| K05-01 | `GET /projects/{projectId}/meetings` | members | List meetings |
| K05-01 | `GET /meetings/{meetingId}` | members | Meeting detail with participants and MoM status |
| K05-01 | `PATCH /meetings/{meetingId}/status` | PM | `{ "status": "DONE" \| "CANCELLED" }` |
| K05-02 | `POST /meetings/{meetingId}/mom` | TW | Create MoM (with action items, attendance) |
| K05-02 | `GET /moms/{momId}` | members | Read MoM |
| K05-02 | `PUT /moms/{momId}` | TW | Edit MoM while DRAFT/REVISION |
| K05-03 | `PATCH /moms/{momId}/status` | TW, TL | `{ "status": "IN_REVIEW" }` (TW) or `{ "status": "FINAL" \| "REVISION", "reviewNote": "..." }` (TL) |
| K05-04 | `GET /action-items?projectId=&mine=true&overdue=true` | members | List action items of FINAL MoMs; filters optional |
| K05-04 | `PATCH /action-items/{actionItemId}/status` | PIC | `{ "status": "IN_PROGRESS" \| "DONE" }` |

**Error shape** (from the meeting-3 material; not yet agreed class-wide):
```json
{ "code": "MOM_NOT_EDITABLE", "message": "MoM berstatus FINAL tidak dapat diubah" }
```

| HTTP | When | Example codes |
|---|---|---|
| 400 | Missing/invalid field | VALIDATION_ERROR (list fields in message) |
| 401 | No or invalid token | UNAUTHORIZED |
| 403 | Wrong role or not PIC / not a project member | FORBIDDEN |
| 404 | Unknown ID | PROJECT_NOT_FOUND, MEETING_NOT_FOUND, MOM_NOT_FOUND, ACTION_ITEM_NOT_FOUND |
| 409 | Wrong status for this action | PROJECT_INACTIVE, MEETING_NOT_DONE, MOM_ALREADY_EXISTS, MOM_NOT_EDITABLE, INVALID_STATUS_TRANSITION |

The `openapi.yaml` in the repo must describe these operations **and** API-K05-09 (the D-3 contract from the meeting-3 document). API-K05-09 is only a stub in slice 1: it returns `examples/success.json`, so D-3 can start mocking against it.

**Where the contract lives:** `openapi.yaml` (OpenAPI 3.1, hand-written) and `examples/success.json` / `examples/error.json` at the repo root. The YAML is the source of truth: when an endpoint changes, the YAML changes in the same pull request. It must stay identical to the submitted *OpenAPI Versi Awal* document for API-K05-09, including `baselineItemId` (from E-3) and `affectedTaskIds` (from E-4).

---

## 9. External dependencies — adapters and mocks

All calls to other groups go through an adapter, so each mock can be swapped for the real API without touching business logic.

| Adapter | Real owner | Slice 1 behaviour |
|---|---|---|
| IdentityProvider | F-1 | Dev login: pick a seeded account; returns accountId, displayName, role. Token is a fixed mock value per account |
| ProjectDirectory | D-1 | Returns seeded projects with status and member accountIds |

In Laravel: each adapter is a PHP interface (`app/Contracts/IdentityProvider.php`, `app/Contracts/ProjectDirectory.php`) with a `Mock…` implementation now and an `Http…` implementation later. A service provider binds one or the other based on `INTEGRATION_MODE=mock|live` in `.env`.

Mock data for other groups' records (accounts, projects) lives in JSON fixtures such as `database/mocks/f1_accounts.json` and `database/mocks/d1_projects.json`, **not** in E-5's database tables. E-5's database only holds E-5's own entities (§7), so switching to the real APIs later doesn't require a migration.

Mock authentication: a middleware reads `Authorization: Bearer mock-<accountId>` (e.g. `Bearer mock-acc-pm-0007`), looks the account up through `IdentityProvider`, and attaches it to the request. The dev-login page (screen 1) does the same through the session. No secrets in the repo; `.env.example` holds placeholders.

---

## 10. Seed data (fictional)

| Kind | Values |
|---|---|
| Mock F-1 accounts (fixture) | `acc-pm-0007` (PM), `acc-tl-0042` (TL), `acc-tw-0091` (TW), `acc-tw-0092` (TW, not in PRJ-2026-014 — for access-denied tests) |
| Mock D-1 projects (fixture) | `PRJ-2026-014` active, members PM/TL/TW above; `PRJ-2026-015` inactive (for rejection tests) |
| Meetings | One SCHEDULED, one DONE without MoM, one DONE with a FINAL MoM |
| Action items | On the FINAL MoM: one TODO due in the future, one IN_PROGRESS overdue, one DONE |

The seed must give the same result every time it runs (fixed IDs, no random values).

---

## 11. Screens (slice 1)

Plain and functional; no design work required yet.

1. **Dev login** — choose a seeded account.
2. **Projects** — active projects the user belongs to.
3. **Meetings of a project** — list with status; PM sees "Schedule meeting" (form: title, date/time, location, participants, client names).
4. **Meeting detail** — participants; PM buttons Done/Cancel; link to MoM or "Write MoM" for TW when DONE.
5. **MoM editor** (TW) — WI-05 fields, attendance ticks, action item rows (description, PIC, deadline), Save and Submit for review. Shows TL's review note when in REVISION.
6. **MoM view/review** — read-only view; TL sees Approve / Request revision (note required).
7. **Action items** — "Mine" tab for everyone, "All in project" tab for PM; status dropdown enabled only for the PIC; overdue badge.

---

## 12. Open questions (affect slice 1)

These are assumptions until the partner (mitra) confirms them. Record answers in Pertanyaan Mitra and update this PRD.

| Topic | Assumption used now |
|---|---|
| Who finalizes the MoM — TL only, or also PM? | TL only (WI-05 flow "MoM → TL") |
| MoM deadline H-0 or H+1 after the meeting | Not enforced in slice 1 |
| Action item statuses and whether they can move backward | TODO → IN_PROGRESS → DONE, forward only |
| Can Developer/TA be a PIC? (SOP-06 vs RACI conflict) | No — PIC is PM, TL or TW |
| Does the client log in? | No — recorded by name only |
| Where project membership comes from (F-1 or D-1) | Mock defines it |

---

## 13. Tech stack and project conventions

**Decided:** Laravel (PHP). Use the latest stable Laravel release and the PHP version it requires; record both in the README.

**Also decided:** MySQL as the database; Blade for pages. Tests run on SQLite in memory so CI needs no database server.

**Conventions** — follow these so every feature looks the same:

| Concern | Laravel tool | Rule |
|---|---|---|
| Routes | `routes/api.php` (JSON), `routes/web.php` (pages) | All API routes under prefix `api/v1` |
| Validation | Form Request classes | One per write endpoint; failures return the §8 error shape with code `VALIDATION_ERROR` |
| Permissions | Policies | Every role rule in §6 lives in a Policy, not in controllers |
| Status changes | One service class per entity (e.g. `MomStatusService`) | Checks the allowed transition (§6), saves, and writes `StatusHistory` in one DB transaction |
| IDs | Eloquent `HasUuids` | E-5 entities use UUID primary keys; other groups' IDs are plain string columns |
| Dates | Stored UTC, app timezone UTC | Convert to WIB only when displaying; "overdue" compares dates in WIB |
| Errors | One exception handler mapping | Every error, including 404 and 403, returns `{ code, message }` |
| JSON shape | API Resources with `JsonResource::withoutWrapping()` | Objects and lists are returned bare (no `data` wrapper), matching `openapi.yaml` |
| Seed | Seeders with fixed UUIDs | `php artisan migrate:fresh --seed` gives the same data every run |
| Tests | Laravel feature tests | At least one success and one rejection test per endpoint |
| OpenAPI | Hand-written `openapi.yaml` at repo root | Linted in CI (e.g. `npx @redocly/cli lint openapi.yaml`) |
| CI | GitHub Actions | On push and pull request: install, lint OpenAPI, run tests |

Suggested folders, in addition to Laravel's defaults:

```
app/Contracts/            IdentityProvider, ProjectDirectory (interfaces)
app/Integrations/Mock/    Mock implementations reading database/mocks/*.json
app/Services/             Status services (transition + history)
app/Policies/             Meeting, Mom, ActionItem policies
database/mocks/           f1_accounts.json, d1_projects.json (fake F-1/D-1 data)
examples/                 success.json, error.json (contract examples)
openapi.yaml
PRD.md
```

## 14. Definition of done — slice 1

- [ ] US-1 to US-4 acceptance criteria pass, including every rejection case
- [ ] Automated tests cover the permission matrix (§6) and status transitions
- [ ] Seed data loads repeatably; README explains install → configure → migrate → seed → run → test
- [ ] `openapi.yaml` describes slice-1 operations and the API-K05-09 stub; `examples/success.json` and `examples/error.json` are in the repo
- [ ] CI runs the OpenAPI lint and tests on every push and shows pass/fail
- [ ] No secrets or real personal data in the repo

---

## 15. Who sets up what

The project lead builds the shared foundation once; everyone else builds features on top of it by copying the reference endpoint.

| Part | Owner | Contents |
|---|---|---|
| Skeleton and plumbing | Arya Raihan Hanif | Laravel project, `.env.example`, folder conventions (§13), adapters + mock fixtures (§9), mock-auth middleware, error handler, CI workflow, README, branch protection on `main` |
| Contract | Arya Raihan Hanif | `openapi.yaml` + `examples/`, kept identical to the submitted OpenAPI document |
| Reference endpoint | Arya Raihan Hanif | `GET /api/v1/projects/{projectId}/meetings` end to end: migration, model, seeder, policy, controller, 404/403 cases, feature tests. Every other endpoint copies this pattern |
| US-1 Schedule a meeting (BL-01) | Arya Raihan Hanif | Builds on the reference endpoint |
| US-2 Write the MoM (BL-02, BL-03) | Arya Raihan Hanif | Includes action items and attendance |
| US-3 Review the MoM (BL-04) | Harits Fadhil Kurnia | Status service + review note |
| US-4 Action items (BL-05) | I Made Surya Kartika | List with filters + PIC status update |

Later slices, per Backlog v0.2: BL-06 to BL-07 Iqbal Nurfikri; BL-08 to BL-09 Arya Raihan Hanif; BL-10 to BL-11 Muhammad Fabregas Garda Samudra; BL-12 Harits Fadhil Kurnia; BL-13 I Made Surya Kartika.

Rules: one branch per task, a pull request into `main`, one teammate approves before merge. Each member builds their own backlog items, so every member has their own commits.

