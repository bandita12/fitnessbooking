# AGENTS.todo.md — fitnessbooking

Stand: 2026-10-14. Nur offene TODO-Positionen.

- [ ] `features/` anlegen: erstes Feature als SOLL-Zustand dokumentieren (Architektur, Datenmodell, API-Verträge)
- [ ] Nach dem ersten `git pull` im Skills-Repo: `AGENTS.skills.md` fortschreiben (skill `skills-marker`)
- [ ] GitHub-CI: Laravel-Workflows liegen unter `backend/.github/workflows/` — GitHub liest nur Root-`.github/workflows/`. Entweder nach Root verschieben oder per `paths`/Job-step auf `backend/` zeigen lassen (skill `github-ci-filters`)
- [ ] PostgreSQL-Migration gegen laufenden Container `fitnessbooking-db` verifizieren: `php artisan migrate --seed`
- [ ] Erste echte Feature-/Unit-Tests schreiben (aktuell nur 2 Scaffold-ExampleTests) — AGENTS.md §4
- [ ] Frontend-Lint aufsetzen (ESLint/Prettier, `lint:fix`-Script) — AGENTS.md §5-Gate gilt, sobald Lint existiert
- [ ] Playwright-Config mit Tags `@smoke` / `@regression` / `@feature:<name>` — AGENTS.md §4
- [ ] i18n-Setup nach Skill `react-i18n` (Lingui), sobald erste deutsche UI-Strings landen
- [ ] Entscheiden: `backend/.claude/`-Skills (35 Dateien, von `boost:install`) im Repo behalten oder global ignorieren

## Erledigt (Stand: 2026-10-14)

- [x] PostgreSQL-Migration gegen laufenden Container `fitnessbooking-db` verifiziert — `php artisan migrate --seed` grün (10 Tabellen, 4 Migrationen); `DatabaseSeeder` auf idempotenten Admin-Seed umgestellt (`User::firstOrCreate` mit `ADMIN_EMAIL`/`ADMIN_PASSWORD`, in `.env` + `.env.example`); zweiter `db:seed` exit 0 ohne Unique-Verletzung; `php artisan test` (2/2) und `pint --test` grün.


## 🔐 Feature: Berechtigungssystem & Rollen (RBAC)

**Beschreibung:**
Implementierung eines rollenbasierten Zugriffssystems (Admins, Studio-Admins, Kursleiter, Kunden) für das Fitness-Buchungssystem.

### 🏢 Rollendefinitionen:
1. **Admin (Super-Admin):** Vollzugriff auf alle Systemfunktionen. Kann neue Fitnessstudios und deren Studio-Admins anlegen.
2. **Studio-Admin:** Verwendet eine `studio_id`. Verantwortlich für die Pflege der Stammdaten seines Studios (Adresse, Räume, Kurse).
3. **Kursleiter (Trainer):** Kann den eigenen Kursen zugewiesene Einträge kommentieren (Teilnehmerzahl/Beschreibung präzisieren) und Status-Updates wie Entfall oder Vertretung posten.
4. **Kunde:** Kann das Kursangebot aller Studios einsehen und sich für beliebige freie Kurse ein- oder austragen.

### 📋 Akzeptanzkriterien für die KI:

#### 1. Datenbank & Modelle (Laravel)
- [ ] Erstelle eine Migration für eine Tabelle `roles` (oder füge ein `role`-Feld zum `users`-Model hinzu: ENUM oder String).
- [ ] Füge dem `users`-Model ein optionales, nullbares Feld `studio_id` hinzu (Fremdschlüssel für Studio-Admins und Kursleiter).
- [ ] Erstelle eine Seeder-Datei (`RolesAndUsersSeeder`), die für jede Rolle einen Test-Benutzer anlegt.

#### 2. Backend-Schutz & API-Routen (Laravel)
- [ ] Implementiere Middleware oder Laravel Gates/Policies, um Routen nach Rollen zu schützen.
- [ ] **Admin-Routen:** `/api/admin/studios` (POST, PUT, DELETE) und `/api/admin/users` (Zuweisung Studio-Admins).
- [ ] **Studio-Admin-Routen:** `/api/studios/{id}/details` (PUT), `/api/studios/{id}/rooms` (POST, PUT, DELETE), `/api/studios/{id}/courses` (POST, PUT, DELETE). *Muss prüfen, ob der User zu diesem Studio gehört.*
- [ ] **Kursleiter-Routen:** `/api/courses/{id}/updates` (POST/PUT für Entfall, Vertretung, Beschreibung). *Muss prüfen, ob der User als Trainer für diesen Kurs eingetragen ist.*
- [ ] **Kunden-Routen:** `/api/bookings` (POST/DELETE für Kursbuchungen).

#### 3. Frontend-Integration (React)
- [ ] Erstelle einen React Auth-Context, der die Benutzerrolle und die `studio_id` nach dem Login global speichert.
- [ ] Baue eine Schutzkomponente `<ProtectedRoute allowedRoles={['admin', 'studio_admin']} />` für die Admin-Dashboards.
- [ ] Blende UI-Elemente (z. B. den Button "Studio anlegen" oder "Kurs absagen") visuell aus, wenn der eingeloggte User nicht die erforderliche Rolle besitzt.

### 🧭 Architektur-Entscheidungen (bestätigt 2026-10-14, Details: `features/rbac-architecture.md`)

1. **Rollen:** Enum-Einzelrolle — String-Spalte `users.role` + `App\Enums\UserRole` (`admin`, `studio_admin`, `trainer`, `customer`); **keine** `roles`-Tabelle.
2. **Auth:** Laravel Sanctum **SPA-Cookie-Modus** (nicht Token); Frontend-Origin in `sanctum.stateful`, Axios `withCredentials` + XSRF; behebt den 500er `Route [login] not defined`.
3. **Kapazität/Storno:** `courses.capacity` + `starts_at`/`ends_at`; kostenloses Storno bis 2 h vor Beginn (`now() <= starts_at->subHours(2)`).
4. **Trainer-Zuweisung:** Pivot `course_user` (unique course_id+user_id) — **nicht** `trainer_id` auf `courses` (Abweichung vom ersten Wunsch, im Plan dokumentiert).

### ✅ Umsetzungsplan (Reihenfolge einhalten: `course_updates` braucht `bookings`)

#### Schritt 1 — Fundament (erledigt)
- [x] `features/rbac-architecture.md` persistiert (23e88b7)
- [x] Migration `create_studios_table` (a8ead14) — noch nicht migriert

#### Schritt 2 — Migrationen 2–7 (je ein Commit, portabel PG/SQLite, `down()` leer)
- [ ] `add_role_and_studio_id_to_users` — `role` string(32) default `customer`, `studio_id` nullable FK → studios nullOnDelete + Index
- [ ] `create_rooms_table` — `studio_id` FK cascade, `name`, `capacity` unsignedInteger
- [ ] `create_courses_table` — `studio_id` FK cascade, `room_id` nullable FK nullOnDelete, `title`, `description` text nullable, `starts_at`, `ends_at`, `capacity`, `status` string(32) default `scheduled`; Index studio_id + starts_at
- [ ] `create_course_user_table` — Pivot Trainer, `course_id` + `user_id` FK cascade, **unique(course_id, user_id)**
- [ ] `create_bookings_table` — `user_id` + `course_id` FK cascade, **unique(user_id, course_id)** (keine Doppelbuchung)
- [ ] `create_course_updates_table` — `course_id`, `user_id` (Autor), `booking_id` nullable FK nullOnDelete, `type` (`note|cancelled|substitute`), `body`; Index course_id

#### Schritt 3 — Modelle & Enums
- [ ] `App\Enums\UserRole` (+ `CourseStatus`, `CourseUpdateType`); Cast auf `User::role`
- [ ] Models + Relations: `Studio`, `Room`, `Course` (trainers belongsToMany via `course_user`, room belongsTo, studio belongsTo), `CourseUpdate`, `Booking`; `User`: `studio()`, `taughtCourses()` via Pivot

#### Schritt 4 — Auth (Sanctum SPA)
- [ ] `/api/login`, `/api/logout`, `/api/me` Routen; `sanctum.stateful` enthält `localhost:5173`/`127.0.0.1:5173`; produktive Origin ist OFFEN (Deploy-Reminder)
- [ ] Axios-Client: `withCredentials: true` + XSRF-Token-Header; Auth-Context im Frontend (Rolle + `studio_id`)
- [ ] Hinweis: `course_updates` ist das **einzige** Änderungs-Protokoll für Kurse (kein Audit-Feld am Course)

#### Schritt 5 — Authorization
- [ ] Gates nach Capability (`studios.manage`, `users.assign-roles`, `studios.update`, `rooms.manage`, `courses.manage`, `course-updates.create`, `courses.view`, `bookings.manage`), Route-`can:`-Middleware
- [ ] Policies: `StudioPolicy` (studio_id-Vergleich), `CoursePolicy` (Pivot-Trainer oder Studio-Admin), `BookingPolicy` (Ownership); Fremd-Studio → **404**, nie 403
- [ ] Tenant-Scope serverseitig pro Request — nie aus Token-Claims

#### Schritt 6 — API-Routen (Tabelle im Plan)
- [ ] Admin: `/api/admin/studios`, `/api/admin/users`
- [ ] Studio-Admin: `/api/studios/{id}/details|rooms|courses`
- [ ] Kursleiter: `/api/courses/{id}/updates`
- [ ] Kunde: `/api/bookings` (POST/DELETE) — inkl. Kapazitäts- und Stornofrist-Prüfung

#### Schritt 7 — Seeder
- [ ] `RolesAndUsersSeeder`: je Rolle ein Test-User (`firstOrCreate`), Seed-if-fresh-Guard; danach `php artisan migrate --seed` gegen pgsql

#### Schritt 8 — Tests
- [ ] Matrix Rolle × Route: erlaubt, eigenes Studio, Fremd-Studio → 404, Doppelbuchung → 422, Storno nach Frist → 422, Trainer ohne Zuweisung → 404; SQLite `:memory:` + `RefreshDatabase`; Auth-Helper auf Base-`TestCase`
- [ ] **Migrations-Guard:** eine Feature-Test mit `RefreshDatabase`, die die volle Migrationskette auf SQLite läuft (Begründung: die Suite hatte bisher kein `RefreshDatabase` — Migrationen wurden von `php artisan test` nie ausgeführt; die Portabilitäts-Messungen liefen ad hoc auf Wegwerf-DBs. Für Schritte 3–7 gehört das als Regressionstest in die Suite.)

#### Schritt 9 — Frontend (nach Backend)
- [ ] Auth-Context (Rolle + studio_id), `<ProtectedRoute allowedRoles={[...]} />`, Rollen-basiertes Ausblenden von UI-Elementen
