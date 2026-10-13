# AGENTS.todo.md — fitnessbooking

Stand: 2026-10-13. Nur offene TODO-Positionen.

- [ ] `features/` anlegen: erstes Feature als SOLL-Zustand dokumentieren (Architektur, Datenmodell, API-Verträge)
- [ ] Git remote (GitHub) anlegen und pushen; CI beobachten
- [ ] Nach dem ersten `git pull` im Skills-Repo: `AGENTS.skills.md` fortschreiben (skill `skills-marker`)
- [ ] PostgreSQL per `docker compose -f backend/docker-compose.yml up -d` starten, `php artisan migrate --seed` gegen pgsql verifizieren
- [ ] Erste echte Feature-/Unit-Tests schreiben (aktuell nur 2 Scaffold-ExampleTests) — AGENTS.md §4
- [ ] Frontend-Lint aufsetzen (ESLint/Prettier, `lint:fix`-Script) — AGENTS.md §5-Gate gilt, sobald Lint existiert
- [ ] Playwright-Config mit Tags `@smoke` / `@regression` / `@feature:<name>` — AGENTS.md §4
- [ ] i18n-Setup nach Skill `react-i18n` (Lingui), sobald erste deutsche UI-Strings landen
- [ ] Entscheiden: `backend/.claude/`-Skills (35 Dateien, von `boost:install`) im Repo behalten oder global ignorieren
