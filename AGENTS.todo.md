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
3. **Regeltermin / Tagesinstanz:** `courses` (Vorlage: day_of_week, start_time, end_time, capacity nullable) + `course_sessions` (date, room_id nullable, trainer_id für Vertretung, status, session_info, capacity nullable). Capacity-Kaskade `room→course→session` (erstes Nicht-NULL). Buchungen und `course_updates` referenzieren `course_session_id`. Storno bis 2 h vor Termin-Beginn.
4. **Trainer-Zuweisung (revidiert 2026-10-15):** `trainer_id` auf `courses` (Lead-Trainer) und `course_sessions` (Termin-Trainer / Vertretung) eingeführt; Trainer arbeiten n:m via Pivot `studio_user` (`users.studio_id` nur für Studio-Admins). Die Roster-Pivot `course_user` bleibt deferred/optional.

### ✅ Umsetzungsplan (Reihenfolge einhalten: `course_updates` braucht `bookings`)

#### Schritt 1 — Fundament (erledigt)
- [x] `features/rbac-architecture.md` persistiert (23e88b7)
- [x] Migration `create_studios_table` (a8ead14) — noch nicht migriert

#### Schritt 2 — Migrationen 2–7 (je ein Commit, portabel PG/SQLite, `down()` leer)
- [ ] `add_role_and_studio_id_to_users` — `role` string(32) default `customer`, `studio_id` nullable FK → studios nullOnDelete + Index
- [ ] `create_rooms_table` — `studio_id` FK cascade, `name`, `capacity` unsignedInteger
- [ ] `create_courses_table` — `studio_id` FK cascade, `trainer_id` nullable FK nullOnDelete (Lead-Trainer), `title`, `description`/`thumbnail_url`/`image_url`/`web_link` nullable, `day_of_week` unsigned tiny, `start_time`/`end_time` time, `capacity` nullable; Index studio_id + trainer_id
- [ ] `create_course_sessions_table` — `course_id` FK cascade, `room_id` nullable FK nullOnDelete, `trainer_id` nullable FK nullOnDelete (Termin-Trainer), `date`, `status` string(16) default `scheduled`, `session_info` nullable, `capacity` nullable; Index (course_id, date) + trainer_id
- [ ] `create_course_user_table` — Optional (slim, 2026-10-15): Pivot Trainer-Roster zurzeit nicht implementiert; `trainer_id` auf `courses` und `course_sessions` plus Studio-Pivot `studio_user` genügen. Nachrüstung bei Mehr-Trainer-Kursen möglich.
- [x] `create_studio_user_table` (2026-10-15) — Trainer ↔ Studios n:m: `user_id` + `studio_id` FK cascade, **unique(user_id, studio_id)**; Tabelle `studio_user`
- [ ] `create_bookings_table` — `user_id` FK cascade, `course_session_id` FK cascade, **unique(user_id, course_session_id)** (keine Doppelbuchung desselben Termins)
- [ ] `create_course_updates_table` — `course_session_id` FK cascade, `user_id` (Autor), `booking_id` nullable FK nullOnDelete, `type` (`note|cancelled|substitute`), `body`; Index course_session_id
- **Hinweis (Kurs-Modell):** Regeltermin `courses` (Vorlage) + konkrete Einheit `course_sessions` (Tagesinstanz); Trainer-Zuordnung `trainer_id` auf beiden (course_sessions-Trainer ermöglicht Vertretung). Bookings & course_updates referenzieren ab sofort `course_session_id` statt `course_id`.

#### Schritt 3 — Modelle & Enums
- [ ] `App\Enums\UserRole` (+ `CourseStatus`, `CourseUpdateType`); Cast auf `User::role`
- [ ] Models + Relations: `Studio`, `Room`, `Course` (Trainer über `courses.trainer_id` und `course_sessions.trainer_id`; pivot `course_user` optional, später nachrüstbar), `CourseUpdate`, `Booking`; `User`: `studio()`, `taughtCourses()` via optional Pivot

#### Schritt 4 — Auth (Sanctum SPA)
- [x] `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/me` (AuthController, Session-Cookie via `web`-Middleware); `SANCTUM_STATEFUL_DOMAINS` enthält `localhost:5173`/`127.0.0.1:5173` (`.env` + `.env.example`); produktive Origin ist OFFEN (Deploy-Reminder)
- [x] 401-statt-500: `AuthenticationException` auf `api/*` rendert immer `401`-JSON (kein Redirect auf nichtexistente `login`-Route); `api/*` von CSRF ausgenommen (Same-Site-SPA, Lax-Cookies)
- [x] Regressionstest `tests/Feature/AuthApiTest.php` (6 Tests: Login ok/422, `/api/me` 200 + Gast-401-JSON, Logout zerstört Session) — SQLite + Wegwerf-PostgreSQL
- [ ] Axios-Client: `withCredentials: true` + XSRF-Token-Header; Auth-Context im Frontend (Rolle + `studio_id`)
- [ ] Hinweis: `course_updates` ist das **einzige** Änderungs-Protokoll für Kurse (kein Audit-Feld am Course)

#### Schritt 5 — Authorization
- [ ] Gates nach Capability (`studios.manage`, `users.assign-roles`, `studios.update`, `rooms.manage`, `courses.manage`, `course-updates.create`, `courses.view`, `bookings.manage`), Route-`can:`-Middleware
- [ ] Policies: `StudioPolicy` (studio_id-Vergleich), `CoursePolicy` (Pivot-Trainer oder Studio-Admin), `BookingPolicy` (Ownership); Fremd-Studio → **404**, nie 403
- [ ] Tenant-Scope serverseitig pro Request — nie aus Token-Claims

#### Schritt 5a — Capability-Layer: Enum, Gates & Policies (2026-10-15)
- [x] `App\Enums\UserRole` (`admin`, `studio_admin`, `trainer`, `customer`) + Cast auf `User::role`; `role`/`studio_id` ins Fillable aufgenommen (Seeder-Fix)
- [x] `AuthServiceProvider`: alle 8 Capabilities als Gates + `Gate::before` Admin-Bypass; Policies gemappt (`Studio` → `StudioPolicy`, `Course` → `CoursePolicy`)
- [x] `StudioPolicy` (`view`/`update`/`manageRooms`/`manageCourses`) und `CoursePolicy` (`update`/`createUpdate`); Trainer-Prüfung über `courses.trainer_id` (Lead) ODER `course_sessions.trainer_id` (Vertretung) — Pivot `course_user` bleibt optional/deferred
- [x] 404-statt-403: `ScopedByStudio`-Trait (`forTenant`) + `Course::assignedTo()` — Controller lösen per `findOrFail()` auf, Cross-Tenant → `ModelNotFoundException` (404)
- [x] Minimale Modelle als Policy-Voraussetzung angelegt: `Studio`, `Course`, `CourseSession` (volle Schritt-3-Modellierung folgt separat)
- [x] Regressionstest `tests/Feature/RbacAuthorizationTest.php` (Enum-Cast, Capability-Matrix, Studio-/Course-Policy, 404-Scopes, Seeder-Idempotenz, Trainer-n:m) — SQLite `:memory:` + Cross-Engine-Lauf auf Wegwerf-PostgreSQL
- [x] Trainer-n:m (2026-10-15): `User::studios()` / `Studio::trainers()` via `studio_user`; `users.studio_id` nur für Studio-Admins (Trainer NULL); `CoursePolicy` + Scopes pivot-basiert; Seeder verknüpft Trainer via Pivot
- [ ] Offen: `BookingPolicy` (Ownership) + Route-`can:`-Verdrahtung folgen mit Schritt 6 (API-Routen)

#### Schritt 6 — API-Routen (Tabelle im Plan)
- [ ] Admin: `/api/admin/studios`, `/api/admin/users`
- [ ] Studio-Admin: `/api/studios/{id}/details|rooms|courses`
- [ ] Kursleiter: `/api/courses/{id}/updates`
- [ ] Kunde: `/api/bookings` (POST/DELETE) — inkl. Kapazitäts- und Stornofrist-Prüfung

#### Schritt 6a — API-Routen & BookingPolicy (2026-10-15)
- [x] `BookingPolicy` (`view`/`delete`): Kunde nur eigene, Studio-Admin nur eigenes Studio (via `session.course.studio_id`), Admin alles; Trainer kein Zugriff
- [x] 2h-Stornofrist: `Booking::isCancellable()` (`now() <= startsAt - 2h`); Policy-`delete` prüft nur Wer-darf, Controller liefert bei Fristablauf **422** (nicht 403)
- [x] `CourseSession::startsAt()` (date + `courses.start_time`) und `effectiveCapacity()` (Kaskade session → course → room)
- [x] Controller: `StudioController`, `CourseController`, `CourseSessionController`, `BookingController` (minimal, tenant-scoped `findOrFail` → 404); Basis-`Controller` mit `AuthorizesRequests`; `HasApiTokens` auf `User`
- [x] `routes/api.php`: Studios-/Courses-/Sessions-/Bookings-Routen mit `auth:sanctum` + `can:`-Middleware je Capability
- [x] Regressionstest `tests/Feature/BookingApiTest.php` (10 Tests: Buchen, Doppelbuchung → 422, ausgebucht → 422, cancelled → 422, Storno >2h → 204, Storno <2h → 422, Fremd-Buchung → 404, Index-Scoping, Studio-Admin-Storno, Gast → 401) — SQLite + Wegwerf-PostgreSQL
- [ ] Offen: Rooms-CRUD-Routen (braucht `Room`-Modell-Vervollständigung — Modell existiert minimal), `/courses/{id}/updates`-Endpoints (braucht `CourseUpdate`-Modell), `/api/admin/users`, Login/Logout/Me (Schritt 4 Auth)

#### Schritt 7 — Seeder
- [ ] `RolesAndUsersSeeder`: je Rolle ein Test-User (`firstOrCreate`), Seed-if-fresh-Guard; danach `php artisan migrate --seed` gegen pgsql

#### Schritt 8 — Tests
- [ ] Matrix Rolle × Route: erlaubt, eigenes Studio, Fremd-Studio → 404, Doppelbuchung → 422, Storno nach Frist → 422, Trainer ohne Zuweisung → 404; SQLite `:memory:` + `RefreshDatabase`; Auth-Helper auf Base-`TestCase`
- [ ] **Migrations-Guard:** eine Feature-Test mit `RefreshDatabase`, die die volle Migrationskette auf SQLite läuft (Begründung: die Suite hatte bisher kein `RefreshDatabase` — Migrationen wurden von `php artisan test` nie ausgeführt; die Portabilitäts-Messungen liefen ad hoc auf Wegwerf-DBs. Für Schritte 3–7 gehört das als Regressionstest in die Suite.)

#### Schritt 9 — Frontend (nach Backend)
- [x] Axios-Client `src/api/client.ts` (baseURL `http://localhost:8000` via `VITE_API_BASE_URL`, `withCredentials` + `withXSRFToken`) + `src/api/auth.ts` (login/logout/me, CSRF-Cookie vor Login); alter `src/lib/api.ts` entfernt
- [x] Auth-Context (Rolle + studio_id): `AuthProvider` lädt `/api/me` beim Start, `useAuth`-Hook, `Login`/`Logout`, Tailwind-`Login`-Formular mit 422-Fehlermeldung
- [x] `<ProtectedRoute allowedRoles={[...]} />`; Rollen-Views: Staff (admin/studio_admin) → `DashboardPage`, Trainer/Customer → `CoursesPage`; Header mit Name/Rolle + Abmelden-Button
- [x] Tailwind CSS v4 (`@tailwindcss/vite`); `pnpm lint` (oxlint) sauber, `pnpm build` (`tsc -b` + vite) grün
- [x] Studio-/Admin-Verwaltung (2026-10-15): `DashboardPage` mit Tabs (Studios verwalten / Studio-Admins); `GET /api/admin/studios`-Liste, Studio-Create-Formular (Marketing-Felder), Admin-Create-Formular (Rolle hart `studio_admin`, 422-Mapping); Backend: `GET /api/admin/studios`, `POST /api/admin/users` (`Admin\UserController`) + `AdminApiTest` (6 Tests)
- [ ] Offen: E2E (Playwright-Tags) und Vitest-Setup für Frontend-Logik
- [x] Benutzer-Bearbeitung (2026-10-16): `PUT /api/admin/users/{user}` (`Admin\UserController@update`: Name/E-Mail/role/studio_id, E-Mail unique-ignore-self, Enum-Rolle, Passwort nur bei Befüllung, Selbst-Demotions-Schutz) + `GET /api/admin/users` (optionaler Role-Filter); Frontend: Admin-Liste mit Bearbeiten-Button, Formular-Edit-Modus mit Prefill, In-Place-Listenupdate, Status/Alert-Feedback; `AdminUserUpdateApiTest` (7 Tests)
- [x] Löschen & Studio-Edit (2026-10-16): `DELETE /api/admin/users/{user}` (Selbstlösch-Schutz → 422); Frontend: Studio-Edit-Modus (Prefill inkl. Marketing) + Löschen mit `window.confirm` (Kaskaden-Hinweis), Admin-Löschen mit Confirm; Tests: User-Delete/Selbstschutz, Studio-Cascade (Räume/Kurse/Sessions/Buchungen), Studio-Marketing-Update
- [x] Formular-Modus-Weiche (2026-10-16): „+ Neues Studio" / „+ Neuen Admin anlegen"-Buttons in beiden Formular-Headern (jederzeit sichtbar, beenden Edit-Modus + leeren Formular + Meldungen); „Abbrechen"-Button neben Speichern im Edit-Modus
- [x] Super-Admin-Direktfreischaltung (2026-10-16): Admin sieht alle Reiter [Studios | Admins | Räume verwalten | Kurse planen]; Studio-Dropdown für Räume/Kurse-Tabs (Auto-Vorauswahl, Key-Remount bei Wechsel; studio_admin sieht kein Dropdown, feste studio_id); Header-Navigation Dashboard/Kursübersicht für Admin (Teilnehmer-Sicht wie Kunde)
- [x] Bugfix Trainer-Registrierung (2026-10-16): `studio_user`-Tabelle fehlte auf der Dev-DB (Migration Pending) → Attach schlug fehl, User blieb verwaist zurück. Fix: `DB::transaction` um Create+Attach (keine Halb-Accounts mehr); Dev-DB per `migrate` nachgezogen (nur diese eine additive Migration, keine Seed-/Delete-Aktionen)
- [x] Kurslisten- & Buchungs-UI (2026-10-16): `GET /api/course_sessions`-Katalog (Sessions + Verfügbarkeit + Buchungsstatus, Filter from/to/studio); `CoursesPage` als Karten-Grid (Titel, Datum/Zeit, Raum mit m² + Beleuchtung, Trainer mit Vertretungs-Badge, session_info, freie Plätze); Buchen/Stornieren-Buttons mit 422-Alert pro Karte; `SessionCatalogApiTest` (4 Tests)
- [x] Studio-Admin-Verwaltung (2026-10-16): `DashboardPage`-Rollenweiche (`studio_admin` → eigenes Studio via `user.studio_id`); Räume-CRUD (Liste, Create/Update/Delete mit allen Attributen: Kapazität, Beschreibung, Bilder, HEX-Farbe, Indoor/Outdoor, Beleuchtung, m²/Breite/Länge/Höhe); Trainer-Sektion (Liste, Registrieren, per E-Mail freischalten, entfernen); Backend: `RoomController` + `StudioTrainerController` mit Routen, `Studio::rooms()`-Relation, `StudioManagementApiTest` (6 Tests)
- [x] Factory-Test-Baseline (2026-10-16): `StudioFactory` + `RoomFactory` (`HasFactory` auf `Studio`/`Room`); `StudioAdminDashboardTest` mit `setUp()`-Baseline (1 Studio, 1 Studio-Admin, 2 Pivot-Trainer) — 4 Tests: Dashboard-Baseline, Raum-Create mit allen Metriken, Pivot-Link, Trainer-Sichtbarkeit (eigenes Studio ja / fremdes 404)
- [x] Kursplanung & Sessions (2026-10-16): Reiter „Kurse verwalten" (Vorlagen-Liste + Create mit Wochentag/Zeiten/Lead-Trainer/Bildern/Kapazität); `POST .../sessions/generate` erzeugt idempotent 4-Wochen-Termine; Session-Edit (Vertretung, cancelled, session_info); Backend: `CourseSessionController@update` + `@generate`, Routen, `CoursePlanningApiTest` (4 Tests)
