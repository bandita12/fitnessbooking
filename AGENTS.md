# AGENTS.md — fitnessbooking

## 1. Project

Booking platform for fitness services. Backend: Laravel + PostgreSQL. Frontend: React (Vite) + TypeScript. One repository, monorepo layout:

    fitnessbooking/
    ├── backend/     Laravel application (composer, artisan, PHPUnit)
    ├── frontend/    React application (pnpm, Vite, Vitest, Playwright)
    ├── features/    permanent SOLL documentation (architecture, data model, API)
    ├── .githooks/   git hooks (codegraph sync)
    ├── .codegraph/  code index (artifacts git-ignored)
    └── AGENTS.todo.md / AGENTS.skills.md

## 2. Language

Code and documentation: English. UI strings: German. German terms inside docs are allowed.

## 3. Tooling

- Backend: Composer, Laravel, PostgreSQL (the only database).
- Frontend: pnpm only — never npm or yarn. Node LTS.
- Tests: `php artisan test` (backend), Vitest (frontend logic), Playwright (E2E).

## 4. Definition of Done — tests

- Backend behaviour → PHPUnit Feature/Unit tests.
- Frontend logic → Vitest.
- UI flows → Playwright E2E; every test carries `{ tag: [...] }`: `@smoke` on the critical path, `@regression` before deploy, `@feature:<name>` for feature-specific selection.
- Bug fixes need at least one regression test.
- Refactorings may skip tests but must justify it in the commit.

## 5. Quality gates

- `pnpm lint:fix` (never plain `lint`), `pnpm build` / `tsc -b`, `php artisan test`.
- No `any`, no `@ts-ignore`, no `eslint-disable`.
- Safe patching: validate every search/replace before applying.
- Zero pre-existing failures.
- Max 3 fix attempts, then hand back to the user.

## 6. Documentation

- `features/` = permanent SOLL state: architecture, data models, API contracts.
- `AGENTS.todo.md` = temporary tasks, review notes, session tracking — a log, not an order.
- Every feature gets actionable TODOs, including test-writing TODOs.

## 7. Build-agent rules (STRICT, established 2026-07-31, not to be bypassed)

- The build agent is orchestrator only. It may read/edit only `AGENTS.md` and `AGENTS.todo.md` (plus tiny typo/policy fixes in those files).
- Every other file MUST be delegated to the `general` subagent — never to `build`.
- Delegate independent tasks in parallel when sensible.
- A separate subagent verifies each implementation (verifier ≠ implementer).
- Visual/layout/screenshot checks the orchestrator does itself; escalate only ambiguous or high-stakes visual calls to `vision-creative`.
- Commits: Conventional Commits, explicit paths, never `git add -A`, `git show --stat` before committing, `Verify:` footer.

## 8. Skills marker

`AGENTS.skills.md` records which state of the central skills repo this project consumes. Advance it on every `git pull` there — see skill `skills-marker`.

## 9. Environment and secrets

- `.env` is never committed; maintain `.env.example` for backend and frontend.
- Local PostgreSQL; credentials stay in `.env`.
