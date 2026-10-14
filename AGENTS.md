# Instructions for AI coding agents

Before writing or changing code in this repo, read and follow **[DESIGN.md](DESIGN.md)**.
It defines the patterns, naming, error format, permissions, UI look, and tests every
change must use. `openapi.yaml` and `PRD.md` are the sources of truth for behaviour.

Hard rules (details in DESIGN.md):
- Responses match `openapi.yaml` exactly: camelCase fields, no `data` wrapper,
  errors are `{ "code", "message" }`.
- Copy the reference endpoint (`Api\MeetingController::index`) and page
  (`Web\ProjectPageController` + `meetings/index.blade.php`).
- F-1 accounts and D-1 projects stay in `database/mocks`. Never add tables for them.
- Role rules go in Policies, membership checks in `ProjectGuard`, status changes in a
  status service that writes `StatusHistory`.
- Add feature tests. `php artisan test` must pass.
- Don't add dependencies or refactor shared files in a feature PR.

Commands: `php artisan migrate:fresh --seed`, `php artisan test`,
`npx @redocly/cli@1 lint openapi.yaml`.
