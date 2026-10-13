# AGENTS.todo.md — fitnessbooking

Stand: 2026-10-13. Nur offene TODO-Positionen.

- [ ] `features/` anlegen: erstes Feature als SOLL-Zustand dokumentieren (Architektur, Datenmodell, API-Verträge)
- [ ] Nach dem ersten `git pull` im Skills-Repo: `AGENTS.skills.md` fortschreiben (skill `skills-marker`)
- [ ] GitHub-CI: Laravel-Workflows liegen unter `backend/.github/workflows/` — GitHub liest nur Root-`.github/workflows/`. Entweder nach Root verschieben oder per `paths`/Job-step auf `backend/` zeigen lassen (skill `github-ci-filters`)
- [ ] PostgreSQL-Migration gegen laufenden Container `fitnessbooking-db` verifizieren: `php artisan migrate --seed`
- [ ] Erste echte Feature-/Unit-Tests schreiben (aktuell nur 2 Scaffold-ExampleTests) — AGENTS.md §4
- [ ] Frontend-Lint aufsetzen (ESLint/Prettier, `lint:fix`-Script) — AGENTS.md §5-Gate gilt, sobald Lint existiert
- [ ] Playwright-Config mit Tags `@smoke` / `@regression` / `@feature:<name>` — AGENTS.md §4
- [ ] i18n-Setup nach Skill `react-i18n` (Lingui), sobald erste deutsche UI-Strings landen
- [ ] Entscheiden: `backend/.claude/`-Skills (35 Dateien, von `boost:install`) im Repo behalten oder global ignorieren
