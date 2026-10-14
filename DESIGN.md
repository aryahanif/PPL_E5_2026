# DESIGN.md: how code in this repo is written

The house rules for E-5. Every feature (US-1 to US-4 and later slices) must look
like the reference endpoint and reference page already in the repo. Humans and
AI agents follow this file. If it conflicts with your habits, this file wins. If it
conflicts with `PRD.md` or `openapi.yaml`, those win: fix this file in the same PR.

Sources of truth, in order:
1. `openapi.yaml`: exact request/response fields, status codes, error codes.
2. `PRD.md`: who may do what (§6), data model (§7), screens (§11).
3. This file: how to write it.
4. Reference code: copy it.

| Kind | Reference to copy |
|---|---|
| API endpoint | `app/Http/Controllers/Api/MeetingController.php` (`index`) |
| Form Request | `app/Http/Requests/ListMeetingsRequest.php` |
| Policy | `app/Policies/MeetingPolicy.php` |
| API Resource | `app/Http/Resources/MeetingResource.php` |
| Page | `app/Http/Controllers/Web/ProjectPageController.php` + `resources/views/meetings/index.blade.php` |
| Feature test | `tests/Feature/MeetingListTest.php` |

---

## 1. Golden rules

1. **The contract is law.** Field names, casing, enums and error codes come from
   `openapi.yaml`. Changing the API means changing `openapi.yaml` in the same PR.
2. **No `data` wrapper.** Objects and arrays are returned bare.
3. **Every error is `{ "code": "UPPER_SNAKE", "message": "Bahasa Indonesia" }`.**
   Never return a different error shape, never `abort()` in API code.
4. **E-5's database holds only E-5 tables.** Accounts (F-1) and projects (D-1) are
   read through `IdentityProvider` / `ProjectDirectory`, never stored, never joined,
   never migrated. Store their IDs as plain strings (`project_id`, `account_id`).
5. **Role rules live in Policies; membership checks live in `ProjectGuard`.** Not in
   controllers, not in Blade, not in models.
6. **Status changes go through a status service** that checks the transition, saves,
   and writes `StatusHistory` in one DB transaction (§5).
7. **Every endpoint ships with feature tests**: at least one success and every
   rejection listed in `openapi.yaml` for it.
8. **Don't add packages** (Composer or npm) without the team agreeing in the PR.
9. **Stay in your slice.** Don't refactor shared files (`bootstrap/app.php`,
   `AppServiceProvider`, layout, `ProjectGuard`) inside a feature PR. Propose it separately.

---

## 2. Request flow

```
routes/api.php (auth:api)            routes/web.php (auth:web)
        │                                    │
  Form Request ── 400 VALIDATION_ERROR       │ $request->validate() / Form Request
        │                                    │
  ProjectGuard ── 404 PROJECT_NOT_FOUND / 403 FORBIDDEN / 409 PROJECT_INACTIVE
        │
  Gate::authorize(policy) ── 403 FORBIDDEN
        │
  Status service / Eloquent query (eager-load relations)
        │
  API Resource (camelCase)             Blade view
```

Pages query Eloquent directly. They do **not** call the JSON API over HTTP. Pages
run the same guard and policy checks as the API.

---

## 3. Naming

| Thing | Convention | Example |
|---|---|---|
| DB tables / columns | `snake_case`, plural tables | `action_items.pic_account_id` |
| JSON fields (in and out) | `camelCase`, exactly as `openapi.yaml` | `picAccountId`, `scheduledAt` |
| Enums | PHP backed enum in `app/Enums`, UPPERCASE English values | `MomStatus::IN_REVIEW` |
| Error codes | `UPPER_SNAKE` from the `Error` schema list | `MOM_NOT_EDITABLE` |
| Controllers | `Api\XController` (JSON), `Web\XPageController` (pages) | `Api\MomController` |
| Form Requests | verb + noun + `Request` | `CreateMeetingRequest` |
| Status services | entity + `StatusService` | `MomStatusService` |
| Controller methods | Laravel resource names + explicit actions | `index`, `store`, `show`, `update`, `updateStatus` |
| Tests | one file per endpoint group, `test_<what>_<expected>()` | `test_non_member_is_403` |
| Code, comments, commits | English | |
| UI text, error messages | Bahasa Indonesia | `Hanya PM yang dapat menjadwalkan rapat` |

Link each controller method to the contract with a docblock:
`/** POST /api/v1/projects/{projectId}/meetings  (operationId: createMeeting) */`

---

## 4. Writing an API endpoint

**Route** in `routes/api.php`, inside the `auth:api` group. Use model binding for
E-5 entities so unknown IDs automatically become `MEETING_NOT_FOUND`,
`MOM_NOT_FOUND`, `ACTION_ITEM_NOT_FOUND`:

```php
Route::get('/meetings/{meeting}', [MeetingController::class, 'show']);
Route::patch('/moms/{mom}/status', [MomController::class, 'updateStatus']);
```

`{projectId}` is a plain string (D-1 owns it) and is checked by `ProjectGuard`.

**Form Request**, one per write endpoint. Rules use the camelCase field names from
`openapi.yaml`. `authorize()` returns `true`; permission checks happen in the controller.

```php
public function rules(): array
{
    return [
        'title' => ['required', 'string', 'max:150'],
        'scheduledAt' => ['required', 'date'],
        'participants' => ['required', 'array', 'min:1'],
    ];
}
```

**Controller**: thin. Guard, authorize, do the work, return a Resource.

```php
/** POST /api/v1/projects/{projectId}/meetings  (operationId: createMeeting) */
public function store(CreateMeetingRequest $request, string $projectId)
{
    $this->projects->forActiveMember($projectId, $request->user());
    Gate::authorize('create', Meeting::class);

    $meeting = DB::transaction(fn () => /* create meeting + participants */);

    return (new MeetingResource($meeting->load(['participants', 'mom'])))
        ->response()->setStatusCode(201);
}
```

For routes with a bound model, guard its project:
`$this->projects->forMember($meeting->project_id, $request->user());`

**Business-rule errors** are thrown, never returned:

```php
throw new ApiException(409, 'MOM_NOT_EDITABLE', 'MoM berstatus FINAL tidak dapat diubah');
```

The handler in `bootstrap/app.php` already maps 400 / 401 / 403 / 404 / 405. Don't add
try/catch for those.

**Resource**: one per `openapi.yaml` schema. Map snake_case to camelCase. Dates
become `->toIso8601ZuluString()` (UTC, `Z`). Date-only fields become
`->toDateString()`. Enums become `->value`. Nullable relations use `?->`. Never return
a model or `toArray()` directly.

---

## 5. Status changes

One service per entity in `app/Services`. The allowed transitions are a constant
copied from PRD §6. Write the history in the same transaction.

```php
class MomStatusService
{
    private const ALLOWED = [
        'DRAFT' => ['IN_REVIEW'],
        'IN_REVIEW' => ['FINAL', 'REVISION'],
        'REVISION' => ['IN_REVIEW'],
    ];

    public function __construct(private StatusHistoryRecorder $history) {}

    public function change(Mom $mom, MomStatus $to, Account $by, ?string $note = null): Mom
    {
        $from = $mom->status;

        if (! in_array($to->value, self::ALLOWED[$from->value] ?? [], true)) {
            throw new ApiException(409, 'INVALID_STATUS_TRANSITION', "Status {$from->value} tidak dapat diubah ke {$to->value}");
        }

        return DB::transaction(function () use ($mom, $from, $to, $by, $note) {
            $mom->update(['status' => $to]);
            $this->history->record($mom, $from, $to, $by, $note);

            return $mom;
        });
    }
}
```

The role check (who may make this transition) stays in the Policy and runs before the service.
`status_histories` is append-only: never update or delete rows.

---

## 6. Permissions

- Roles: `PM`, `TL`, `TW` (`App\Enums\Role`). The client has no account.
- One Policy per model (`MeetingPolicy`, `MomPolicy`, `ActionItemPolicy`), discovered
  automatically. The user type is `App\Data\Account`, not a User model.
- Each method returns `Response::allow()` or `Response::deny('<Indonesian reason>')`.
  The reason becomes the 403 `message`.
- Encode the PRD §6 matrix exactly. "Own items" means
  `$item->pic_account_id === $account->accountId`.
- Inactive project: reads are allowed (`forMember`); creating is not (`forActiveMember` → 409).

---

## 7. Data and time

- E-5 primary keys: UUID via `HasUuids`. Migrations use `$table->uuid('id')->primary()`
  and `foreignUuid(...)->constrained()->cascadeOnDelete()`.
- Status columns are `string(20)`, cast to an enum in the model's `casts()`.
- Comment each foreign-system column with its owner: `// F-1 account`, `// D-1`.
- Times are stored and returned in **UTC**. Convert to WIB only when displaying:
  `->timezone(config('e5.display_timezone'))`.
- "Overdue" is computed, never stored: `due_date < today in WIB AND status != DONE`.
- New migrations get a new timestamped file. Never edit a migration that is on `main`.
- Seed data: fixed UUIDs, `updateOrCreate`, dates relative to today. `migrate:fresh --seed`
  must give the same result every run. Fictional data only (PRD §10).

---

## 8. Pages (Blade): look and feel

The UI is plain and functional on purpose (PRD §11). Every page looks like
`projects/index` and `meetings/index`.

**Structure**

```blade
@extends('layouts.app')

@section('title', 'Rapat')

@section('content')
    <p><a href="/projects">&larr; Proyek</a></p>
    <h1>Rapat · {{ $project->name }}</h1>

    @if ($items->isEmpty())
        <p class="muted">Belum ada rapat.</p>
    @else
        <table>…</table>
    @endif
@endsection
```

- Always extend `layouts.app`. No Vite, Tailwind, Bootstrap or JS frameworks. The
  styles live in the `<style>` block of `resources/views/layouts/app.blade.php`.
- Page title: short Indonesian noun (`Rapat`, `MoM`, `Action item`). `h1` repeats it,
  with ` · <project name>` when inside a project.
- Back link at the top for nested pages: `<p><a href="…">&larr; Parent</a></p>`.
- Lists are `<table>` with a `<th>` header row. Empty state is `<p class="muted">…</p>`.
- Statuses are shown as `<span class="badge">{{ $x->status->value }}</span>` (raw
  uppercase enum). Missing values show `—`.
- Date-time format: `d M Y H:i` in WIB, column header says `(WIB)`. Date only: `d M Y`.
- Forms: `method="POST"` + `@csrf` (+ `@method('PATCH')` etc.). Main action
  `<button class="primary">`, secondary actions plain `<button>`. Labels in Indonesian.
- Show buttons only to roles allowed by the Policy (`@can('create', App\Models\Meeting::class)`),
  and still authorize on the server.
- Validation errors: show `$errors` above the form in a `<p class="muted">` list.

**Design tokens** (defined in `:root` in the layout, reuse them, don't add colors):

| Token | Value | Use |
|---|---|---|
| `--ink` | `#1f2933` | body text |
| `--muted` | `#616e7c` | secondary text, table headers |
| `--line` | `#d9dee3` | borders |
| `--accent` | `#1d4ed8` | links, primary button |
| `--bg` | `#f7f8fa` | page background (content sits on white tables) |

Available classes: `.badge`, `.muted`, `button.primary`. If a page truly needs a new
class, add it to the layout's `<style>` block using these tokens, in its own PR.

---

## 9. Tests

- Feature tests in `tests/Feature`, PHPUnit classes (not Pest).
- DB tests use `RefreshDatabase` and `protected $seed = true;` and assert against seeded fixed IDs.
- Authenticate API calls with `$this->withToken('mock-acc-pm-0007')->getJson(...)`.
  Pages: `$this->post('/dev-login', ['accountId' => '...'])` first.
- Required cases per endpoint: success (assert the exact shape), 400, 401, 403 (wrong
  role **and** non-member `acc-tw-0092`), 404, and each 409 it can return.
- Error assertions use `assertExactJson(['code' => ..., 'message' => ...])` or at least
  `assertJsonPath('code', ...)`.
- One account per test method: the mock guard keeps the first user it sees for the
  rest of that test.
- Don't assert array order the contract doesn't define.

---

## 10. Pull request checklist

- [ ] `openapi.yaml` updated if any request/response changed; `npx @redocly/cli@1 lint openapi.yaml` passes
- [ ] Fields and error codes match `openapi.yaml` exactly; no `data` wrapper
- [ ] Role rule in a Policy, membership via `ProjectGuard`, status change via a status service
- [ ] Feature tests for success and every rejection; `php artisan test` passes
- [ ] `php artisan migrate:fresh --seed` still works
- [ ] No F-1/D-1 data in migrations, no new packages, no secrets or real personal data
- [ ] UI text in Indonesian, uses `layouts.app` and existing classes only
