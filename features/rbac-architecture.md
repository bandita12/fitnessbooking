# RBAC-Architektur — Berechtigungssystem & Rollen

Stand: 2026-10-14. Dieses Dokument beschreibt den **SOLL-Zustand** des rollenbasierten
Berechtigungssystems. Quelle der Anforderungen: `AGENTS.todo.md`, Abschnitt
„🔐 Feature: Berechtigungssystem & Rollen (RBAC)". Prosa Deutsch, Code/Identifier Englisch.

## Zielbild & Rollen

**Single-Role-Modell:** Jeder Benutzer hat **genau eine** Rolle. Abgebildet als
String-Spalte `users.role` plus PHP-Backed-Enum `App\Enums\UserRole`
(`admin`, `studio_admin`, `trainer`, `customer`). **Keine `roles`-Tabelle**, keine
`role_user`-Pivot, keine Mehrfachrollen. Diese Entscheidung ersetzt die ursprüngliche
Formulierung im TODO („Tabelle `roles` oder Role-Feld"): das Role-Feld als String-Enum
wurde gewählt, weil Single-Role die Anforderungen vollständig abdeckt und Joins/Rollen-
Wechsel-Logik entfallen.

| Rolle | Scope-Beschreibung (aus den Akzeptanzkriterien) |
|---|---|
| `admin` | Super-Admin. Vollzugriff auf alle Systemfunktionen. Legt neue Studios an und weist Studio-Admins zu (`users.assign-roles`). Hat keinen eigenen `studio_id` (globaler Scope). |
| `studio_admin` | Verwendet `users.studio_id`. Verantwortlich für die Stammdaten **seines** Studios: Adresse/Details, Räume, Kurse. Fremde Studios sind für ihn nicht sichtbar (404). |
| `trainer` | Arbeitet **n:m für mehrere Studios** (Pivot `studio_user`); `users.studio_id` ist für Trainer `NULL`. Darf Kurse leiten/editieren, wenn er Lead-Trainer, Termin-Vertretung oder für das Studio freigeschaltet ist, sowie Updates (`note`, `cancelled`, `substitute`) posten. |
| `customer` | Sieht das Kursangebot **aller** Studios und bucht frei verfügbare Kurse ein (`POST /api/bookings`) oder bis 2 Stunden vor Kursbeginn kostenlos wieder aus (`DELETE /api/bookings/{booking}`). Kein `studio_id`. |

Zusätzliche Felder am User: `users.role` (String, Default `customer`) und
`users.studio_id` (nullable FK → `studios`, **nur für Studio-Admins** ausgewertet;
für Trainer `NULL`, deren Studio-Zuordnung läuft über `studio_user`). Relationen:
`User::studios()` / `Studio::trainers()` (BelongsToMany über `studio_user`).

## Migrationen

**Portabilitäts-Regeln (gelten für ALLE Migrationen dieses Features):**

1. **Kein PostgreSQL-spezifisches SQL.** Ausschließlich den Laravel-Schema-Builder
   verwenden; kein rohes `DB::statement` mit PG-Dialekt (keine PG-Only-Typen, -Operatoren,
   `SERIAL`, `generate_series` o. Ä.). Dev läuft auf PostgreSQL (Container
   `fitnessbooking-db`), Tests auf SQLite `:memory:` — die Migrationen müssen auf beiden
   laufen.
2. **`json`, nicht `jsonb`.** Wo JSON-Spalten gebraucht werden: `$table->json(...)`.
   Laravel mapped auf PG `json` (nicht `jsonb`); keine PG-JSONB-Operatoren oder GIN-Indizes
   verwenden. (In diesem Feature vorerst keine JSON-Spalte nötig.)
3. **Datums-Arithmetik über Query-Builder/Carbon**, nie über PG-`INTERVAL`-Syntax oder
   Raw-SQL-Funktionen (z. B. Stornofrist `starts_at - 2h` im Code via
   `$course->starts_at->subHours(2)`, nicht in SQL).
4. **`down()` bleibt absichtlich LEER** (Kommentar erlaubt). Rollbacks werden in diesem
   Projekt nie ausgeführt; eine fehlerhafte `down()` ist gefährlicher als keine.
5. **Laravel-Timestamp-Namensgebung:** Dateinamen `YYYY_MM_DD_HHMMSS_<snake_case_action>.php`,
   `created_at`/`updated_at` via `$table->timestamps()`.
6. **Deployed migrations sind immutable:** Eine Migration, die auf irgendeiner Umgebung
   gelaufen ist, wird nicht mehr editiert — Korrekturen kommen in eine NEUE Migration.

**Reihenfolge (mit Abhängigkeitsbegründung):**

1. **`create_studios_table`** — Basis-Tabelle; FK-Ziel für `users.studio_id`, `rooms`, `courses`.
2. **`add_role_and_studio_id_to_users`** — ALTER `users`: `role` string(32) Default `'customer'`,
   `studio_id` foreignId nullable → `studios`, constrained, nullOnDelete, Index auf `studio_id`.
   (Benötigt Tab existiert ab Schritt 1.)
3. **`create_rooms_table`** — Räume gehören zu einem Studio.
   - **`add_marketing_fields_to_studios_table`** (Korrektur-Migration, **FK-frei**) —
     chronologisch direkt **nach** `create_rooms_table` generiert, benötigt aber nur
     die ab Schritt 1 existierende `studios`-Tabelle. Erweitert `studios` per
     `Schema::table` um vier Marketing-/Bild-Felder (`description`, `thumbnail_url`,
     `image_url`, `web_link`, alle nullable) — kein Fremdschlüssel, keine
     CHECK-Constraint, reiner Laravel-Schema-Builder.
4. **`create_courses_table`** — **Arbeits-/Zeitplan (Vorlage)** des Regeltermins:
   Studio (cascade), `trainer_id` nullable FK → `users` (nullOnDelete, Lead-Trainer),
   Titel + Marketing-Felder, `day_of_week` (Mo=1 … So=7), `start_time`/`end_time`,
   `capacity` unsigned, **nullable** (`NULL` = erbt vom zugewiesenen Raum).
5. **`create_course_sessions_table`** — **konkrete Kurseinheit an einem Tag**: `course_id`
   (cascade), `room_id` nullable (nullOnDelete, Raumwechsel pro Termin), `trainer_id`
   nullable (nullOnDelete, Termin-Trainer / Vertretung), `date`, `status` default
   `'scheduled'` (`scheduled|cancelled|substituted`), `session_info` nullable, `capacity`
   nullable (`NULL` = erbt von `courses.capacity`).
6. **`create_course_user_table`** (Pivot, **optional/deferred**) — Kurs ↔ Trainer
   (Roster der berechtigten Kursleiter). Zurückgestellt (slim architecture):
   Trainer-Berechtigung läuft über `courses.trainer_id` / `course_sessions.trainer_id`
   plus die Studio-Pivot `studio_user` (siehe 9).
9. **`create_studio_user_table`** (Pivot, Tabelle `studio_user`) — Trainer ↔ Studios
   (n:m): `user_id` + `studio_id` FK cascade, **unique(user_id, studio_id)**.
   `users.studio_id` gilt nur für Studio-Admins; Trainer hängen via dieser Pivot
   an beliebig vielen Studios. Timestamp-sortiert nach `course_updates`, FK-Ziele
   (`users`, `studios`) existieren längst.
7. **`create_bookings_table`** — Kundenbuchungen. **(Muss VOR `course_updates` entstehen —
   siehe Hinweis.)** Fremdschlüssel auf **`course_session_id`** (nullable FK →
   `course_sessions`), nicht `course_id`. Unique auf `(user_id, course_session_id)`
   verhindert Doppelbuchung desselben Termins.
8. **`create_course_updates_table`** — Update-/Protokoll-Einträge zu Kursen. Fremdschlüssel
   auf **`course_session_id`** (nullable FK → `course_sessions`); optionales
   `booking_id` nullable FK → `bookings`.

> **Abhängigkeitsnotiz:** `course_updates` referenziert `bookings` (Spalte `booking_id`
> nullable FK → `bookings`) und nun `course_sessions` (Spalte `course_session_id`).
> Ursprünglich war `course_updates` VOR `bookings` geplant; die Reihenfolge wurde
> getauscht, damit das FK-Ziel beim Anlegen existiert (relevant besonders für SQLite
> `:memory:`-Testläufe, wo FK-Reihenfolgen strikt geprüft werden). Finale Kette:
> `studios → users-alter → rooms → courses → course_sessions → bookings → course_updates → studio_user`.

**Kurs-Spalten im Detail:**

```php
// 1) create_studios_table
$table->id();
$table->string('name');
$table->string('street');
$table->string('zip');
$table->string('city');
$table->timestamps();

// 1b) add_marketing_fields_to_studios_table — Korrektur-Migration (Schema::table),
// erweitert die bestehende studios-Tabelle; FK-frei, nur Marketing-/Bild-Felder
// (alle nullable, reiner Schema-Builder, keine CHECK-Constraint):
$table->text('description')->nullable();
$table->string('thumbnail_url')->nullable();
$table->string('image_url')->nullable();
$table->string('web_link')->nullable();

// 2) add_role_and_studio_id_to_users (Schema::table)
$table->string('role', 32)->default('customer');
$table->foreignId('studio_id')->nullable()->constrained('studios')->nullOnDelete();
$table->index('studio_id');

// 3) create_rooms_table
$table->id();
$table->foreignId('studio_id')->constrained()->cascadeOnDelete();
$table->string('name');
$table->unsignedInteger('capacity');
$table->text('description')->nullable();
$table->string('thumbnail_url')->nullable();
$table->string('image_url')->nullable();
$table->string('color_code', 7)->nullable();
$table->string('location_type', 16)->default('indoor');
$table->string('lighting', 16)->default('none');
$table->unsignedInteger('size_sqm')->nullable();
$table->decimal('width_meters', 5, 2)->nullable();
$table->decimal('length_meters', 5, 2)->nullable();
$table->decimal('height_meters', 5, 2)->nullable();
$table->timestamps();

// 4) create_courses_table (Regeltermin / Vorlage)
$table->id();
$table->foreignId('studio_id')->constrained()->cascadeOnDelete();
$table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete(); // Lead-Trainer (regulär)
$table->string('title');
$table->text('description')->nullable();
$table->string('thumbnail_url')->nullable();
$table->string('image_url')->nullable();
$table->string('web_link')->nullable();
$table->unsignedTinyInteger('day_of_week');                 // 1 = Mo ... 7 = So (ISO)
$table->time('start_time');
$table->time('end_time');
$table->unsignedInteger('capacity')->nullable();           // NULL = erbt rooms.capacity
$table->timestamps();
$table->index('studio_id');
$table->index('trainer_id');

// 4b) create_course_sessions_table (konkrete Einheit an Tag X)
$table->id();
$table->foreignId('course_id')->constrained()->cascadeOnDelete();
$table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete(); // Raumwechsel pro Termin
$table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete(); // Termin-Trainer (Vertretung)
$table->date('date');
$table->string('status', 16)->default('scheduled'); // 'scheduled' | 'cancelled' | 'substituted'
$table->text('session_info')->nullable();
$table->unsignedInteger('capacity')->nullable();           // NULL = erbt courses.capacity
$table->timestamps();
$table->index(['course_id', 'date']);
$table->index('trainer_id');

// 5) create_course_user_table (Pivot: berechtigte Trainer eines Kurses – derzeit optional; Trainer-ID geführt über courses.trainer_id und course_sessions.trainer_id, Pivot kann bei Bedarf nachgerüstet werden)
$table->id();
$table->foreignId('course_id')->constrained()->cascadeOnDelete();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->unique(['course_id', 'user_id']);

// 5) create_bookings_table
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->foreignId('course_session_id')->constrained('course_sessions')->cascadeOnDelete();
$table->unique(['user_id', 'course_session_id']); // verhindert Doppelbuchung desselben Termins
$table->timestamps();

// 7) create_course_updates_table (Protokoll: Entfall/Vertretung/Notiz)
$table->id();
$table->foreignId('course_session_id')->constrained('course_sessions')->cascadeOnDelete();
$table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Autor
$table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
$table->string('type', 32); // 'note' | 'cancelled' | 'substitute'
$table->text('body');
$table->timestamps();
$table->index('course_session_id');

// 9) create_studio_user_table (Pivot Trainer ↔ Studios, n:m)
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->foreignId('studio_id')->constrained()->cascadeOnDelete();
$table->unique(['user_id', 'studio_id']); // keine Doppelzuweisung ans selbe Studio
$table->timestamps();
```

## Authorization

**Trennung Gate vs. Policy:** Gates (benannt nach der Capability) beantworten
„darf die Rolle das prinzipiell?", Policies beantworten „darf der User DIESES Objekt?".
Die Route prüft beides: `->can('capability')` für die Rolle, `authorize()`/Policy für das Objekt.

**Capability-Matrix:**

| Capability | admin | studio_admin | trainer | customer |
|---|---|---|---|---|
| `studios.manage` | ✅ | ❌ | ❌ | ❌ |
| `users.assign-roles` | ✅ | ❌ | ❌ | ❌ |
| `studios.update` | ✅ | ✅ (nur eigenes Studio) | ❌ | ❌ |
| `rooms.manage` | ✅ | ✅ (nur eigenes Studio) | ❌ | ❌ |
| `courses.manage` | ✅ | ✅ (nur eigenes Studio) | ❌ | ❌ |
| `course-updates.create` | ✅ | ✅ | ✅ (nur zugewiesene Kurse) | ❌ |
| `courses.view` | ✅ | ✅ | ✅ | ✅ (alle Studios) |
| `bookings.manage` | ✅ | ✅ | ✅ | ✅ (Feinscoping in `BookingPolicy`: Ownership) |

**Gates (in `AppServiceProvider` oder `AuthServiceProvider`, benannt nach Capability):**

```php
Gate::define('studios.manage',          fn (User $u) => $u->role === UserRole::Admin);
Gate::define('users.assign-roles',      fn (User $u) => $u->role === UserRole::Admin);
Gate::define('studios.update',          fn (User $u) => $u->role === UserRole::Admin || $u->role === UserRole::StudioAdmin);
Gate::define('rooms.manage',            fn (User $u) => $u->role === UserRole::Admin || $u->role === UserRole::StudioAdmin);
Gate::define('courses.manage',          fn (User $u) => $u->role === UserRole::Admin || $u->role === UserRole::StudioAdmin);
Gate::define('course-updates.create',   fn (User $u) => in_array($u->role, [UserRole::Admin, UserRole::StudioAdmin, UserRole::Trainer], true));
Gate::define('courses.view',            fn (User $u) => true);        // jede authentifizierte Rolle
Gate::define('bookings.manage',         fn (User $u) => true);        // jede authentifizierte Rolle; Feinscoping in BookingPolicy
```

**Policies:**

- **`StudioPolicy`** — `update`/`manageRooms`/`manageCourses`: nur wenn
  `$user->studio_id === $studio->id` (Studio-Admin des eigenen Studios). Der Admin
  umgeht das via `Gate::before` oder Admin-Zweig in der Policy.
- **`CoursePolicy`** — `createUpdate`/`update`: Trainer dann, wenn er Lead-Trainer
  (`courses.trainer_id === $user->id`), Termin-Trainer einer Session des Kurses
  (`course_sessions.trainer_id`) oder **via Pivot `studio_user` für das Studio des
  Kurses freigeschaltet** ist (n:m, 2026-10-15 — die Pivot `course_user` bleibt
  deferred/optional); Studio-Admin, wenn `$user->studio_id === $course->studio_id`
  (nur Studio-Admins werden über `users.studio_id` ausgewertet); Admin immer.
  Umsetzung zusätzlich über Scopes abgesichert: `Course::assignedTo($user)->findOrFail($id)`
  liefert 404 für nicht zugewiesene Trainer.
- **`BookingPolicy`** — `view`/`delete`: Kunde nur eigene Buchungen
  (`$user->id === $booking->user_id`), Studio-Admin alle Buchungen des eigenen
  Studios (via `session.course.studio_id`), Admin alles, Trainer keine.
  (Austragen der eigenen Buchung ist kein Rollenprivileg, sondern Ownership-Check.)
  Das 2-Stunden-Fenster prüft NICHT die Policy, sondern `Booking::isCancellable()`
  im Controller → Fristablauf liefert **422** (Validierung), nicht 403.

**Route-Middleware:** Capabilities werden über `->can('...')` an die Route gebunden,
z. B. `Route::post('/studios', ...)->can('studios.manage');`. Damit scheitert die
Rollen-Prüfung, bevor Controller/Policy laufen.

**Cross-Tenant-Zugriff liefert 404, nicht 403.** Ein Studio-Admin, der auf ein fremdes
Studio (bzw. dessen Kurse/Räume) zugreift, erhält 404 — damit existenz keine
Information leaked. Umsetzung: Ressourcen im Controller **studioseitig scoped** auflösen,
z. B. `Course::where('studio_id', $user->studio_id)->findOrFail($id)` — fremde Studio-ID
fällt als `ModelNotFoundException` zu 404 durch. Die Policy prüft dann nur noch das
bereits gefundene Objekt und liefert nie 403 auf Fremd-Studio-Ressourcen.

**Tenant-Scope wird serverseitig aufgelöst.** `$user->studio_id` kommt aus der
Datenbank — nie aus Token-/Session-Claims. Ein gem-inteter Claim ist replayable und
kann stale sein (Studio-Wechsel würden sonst nicht greifen); ein JWT-Cookie-Ansatz mit
`studio_id`-Claim ist ausgeschlossen.

## Auth (Sanctum SPA cookie mode)

**Entscheidung: Laravel Sanctum im SPA-Cookie-Modus** (nicht Token-Modus). Abhängigkeit
`laravel/sanctum` bleibt unverändert. Dev-Frontend (Vite, `localhost:5173`) und API
(`localhost:8000`) sind same-site → Cookie-Flow funktioniert ohne Token-Handling im
Frontend.

**Scaffold-Anforderungen:**

- Routen: `GET /sanctum/csrf-cookie` (Sanctum-Controller, gibt XSRF-Cookie aus),
  `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/me` (`AuthController`,
  umgesetzt in Schritt 4). Authentifizierte Routen hängen hinter `auth:sanctum`;
  die Auth-Routen tragen zusätzlich die `web`-Middleware (Session für den
  Cookie-Flow). `api/*` ist von der CSRF-Prüfung ausgenommen (Same-Site-SPA mit
  Lax-Cookies); `AuthenticationException` auf `api/*` rendert immer `401`-JSON —
  damit ist der `Route [login] not defined`-500er behoben.
- `config/sanctum.php`: `stateful` muss die Dev-Origin enthalten:
  `http://localhost:5173` (und `http://127.0.0.1:5173`). Produktions-Origin ergänzen,
  sobald Deployment steht (siehe Offene Punkte).
- Frontend-Axios: `withCredentials: true`; den XSRF-Token aus dem `XSRF-TOKEN`-Cookie
  als Header `X-XSRF-TOKEN` mitsenden (Axios-Defaultverhalten bei `withCredentials`).
- `SESSION_DRIVER` persistent wählen (`database` oder `file`, nicht `array`) — die
  Dev-`.env` nutzt bereits `database`; `.env.example` dokumentiert das.
- **Behebt den aktuellen Fehler `Route [login] not defined` (500):** Die Anwendung
  leitet aktuell auf die benannte Route `login` um, die es nicht gibt (Sanctum
  SPA-Modus liefert von Haus aus KEINE Login-Route). Mit den expliziten
  `/api/login`-Routen plus Handler, der unauthentifizierte API-Requests als 401-JSON
  beantwortet (statt `route('login')` aufzulösen), entfällt die Exception.

## Seeding

**`RolesAndUsersSeeder`** legt pro Rolle genau einen Test-User an (läuft aus dem
`DatabaseSeeder`):

| E-Mail | Rolle | `studio_id` | Besonderheit |
|---|---|---|---|
| `admin@example.com` | `admin` | NULL | Vollzugriff; falls der User aus dem bestehenden `DatabaseSeeder` schon existiert, wird sein `role` auf `admin` promoviert (updateOrCreate statt blind firstOrCreate, weil die Migration Default `customer` setzt). |
| `studio1@example.com` | `studio_admin` | → erstes Studio | Stammdaten-Pflege des eigenen Studios. |
| `trainer1@example.com` | `trainer` | NULL | via Pivot `studio_user` mit dem Demo-Studio verknüpft (`syncWithoutDetaching`, idempotent). |
| `kunde@example.com` | `customer` | NULL | plus eine Buchung auf den Demo-Kurs. |

Regeln:

- **Idempotent via `firstOrCreate`** mit der E-Mail als Suchschlüssel; Rollen-Attribute
  nur beim Anlegen gesetzt. Ein zweiter `db:seed`-Lauf erzeugt keine Duplikate und
  keine Unique-Verletzung.
- **Dev-Passwörter aus Env mit Defaults:** `ADMIN_PASSWORD`, `STUDIO_ADMIN_PASSWORD`,
  `TRAINER_PASSWORD`, `CUSTOMER_PASSWORD` (Defaults im Seeder, dokumentiert in
  `backend/.env.example`).
- Benötigte Demodaten (erstes Studio, Demo-Kurs) legt der Seeder per `firstOrCreate`
  an, falls noch keine existieren (Studios/Kurse haben keine eigenen Seeder).
- **Seed-if-fresh-Guard:** Der Seeder läuft nur auf frischer DB — z. B.
  `if (User::where('role', '!=', UserRole::Customer->value)->exists()) { return; }`
  oder `User::count() === 0`. Verhindert, dass ein späteres `db:seed` auf einer
  bestückten Dev-/Prod-DB erneut Test-User anlegt oder Passwörter zurücksetzt.
- Deploy-Hinweis: Nach `add_role_and_studio_id_to_users` einmal `db:seed` laufen lassen,
  damit der vorbestehende Dev-Admin die Rolle `admin` erhält (Default wäre `customer`).

## Fachlogik

- **Kapazitätsregel:** Eine Buchung ist nur erfolgreich, solange
  `bookings->where('course_session_id', $courseSession->id)->count() < $courseSession->capacity`.
  `$courseSession->capacity` löst über die Kaskade auf (erstes Nicht-NULL wins):
  `session.capacity ?? course.capacity ?? room.capacity` → das zugewiesene effective
  Capacity ist der Reservierungs-Peak. Bei vollständig belegtem Termin →
  **422** (Validierungsfehler „Kurs ausgebucht").
- **Kapazitäts-Kaskade (Dreistufig):** `rooms.capacity → courses.capacity (nullable) →
  course_sessions.capacity (nullable)`. Erstes Nicht-NULL wins; NULL auf einer Stufe
  weicht auf die vorige Stufe zurück. Trainer-Override pro Termin: `course_sessions.trainer_id`
  NULL → Fallback `courses.trainer_id` (Lead-Trainer).
- **Stornofrist:** Ein Kunde darf seine Buchung kostenlos entfernen, solange
  `now() <= $sessionStartsAt->subHours(2)` („bis 2 Stunden vor Kursbeginn");
  Der Startzeitpunkt des Termins ergibt sich aus `course_sessions.date`
  (Tag des Termins) plus `courses.start_time` (Recurring start window).
  Danach → **422**. Es gibt keine Zahlungsintegration; „kostenlos" ist hier reines
  Zeitfenster, keine Gebührenlogik.
- **Kurs-Status als Aggregat:** `course_sessions.status` ∈ `scheduled | cancelled
  | substituted` (das frühere, reine `course`-Statusfeld entfällt; Status ist eine
  Eigenschaft der konkreten Einheit). Postet ein Trainer/Studio-Admin ein
  `course_updates`-Element vom Typ `cancelled`/`substitute`, wird der Terminstatus
  entsprechend gesetzt **und** der Eintrag im `course_updates`-Log protokolliert
  (Statusänderungen werden in das Update-Log gespiegelt; das Log ist die Audit-Trail,
  `status` der schnelle Lesezugriff). Typen: `note` (Kommentar/Präzisierung),
  `cancelled` (Entfall), `substitute` (Vertretung).

## Tests

- **Rolle × Route-Matrix** als Feature-Tests: jede Rolle gegen jede API-Route — erlaubt
  ergibt 2xx, verboten ergibt 403/404 gemäß Regeln unten.
- **Own-studio-only:** `studio_admin` von Studio A darf Ressourcen von Studio B **nicht**
  verändern.
- **Foreign studio → 404** (nicht 403) — Cross-Tenant-Zugriff leakt keine Existenz.
- **Doppelbuchung → 422** — zweite Buchung desselben Users auf denselben Kurs
  (Unique-Constraint `(user_id, course_id)` als DB-Rückwand; Service liefert saubere
  422-Validation davor).
- **Nicht zugewiesener Trainer → 404** — Trainer ohne Lead-/Vertretungs-Zuweisung und
  ohne `studio_user`-Freischaltung bekommt auf `/api/courses/{id}/updates` keine 403,
  sondern 404 (Kurs existiert für ihn nicht).
- **Storno nach Fristablauf → 422** — `DELETE /api/bookings/{booking}` innerhalb 2h
  vor `starts_at` schlägt fehl.
- **DB:** SQLite `:memory:` via `RefreshDatabase` (`phpunit.xml` setzt `DB_CONNECTION=sqlite`,
  `DB_DATABASE=:memory:`).
- **Auth-Helper auf der Basis-`TestCase`:** Der Helper führt den echten Sanctum-Cookie-
  Flow aus (`GET /sanctum/csrf-cookie` → `POST /api/login` mit Test-User), damit die
  Tests denselben Pfad nehmen wie das Frontend (Session/CSRF inklusive).

## API-Routen

| Route | Methoden | Middleware / Gate | Policy |
|---|---|---|---|
| `GET /sanctum/csrf-cookie` | GET | — (Sanctum) | — |
| `POST /api/auth/login` | POST | `web` (Session) | — |
| `POST /api/auth/logout` | POST | `web` + `auth:sanctum` | — |
| `GET /api/me` | GET | `web` + `auth:sanctum` | — |
| `GET /api/studios` | GET | `auth:sanctum` + `can:courses.view` | — |
| `GET /api/studios/{id}/courses` | GET | `auth:sanctum` + `can:courses.view` | — |
| `GET /api/admin/studios` | GET | `auth:sanctum` + `can:studios.manage` | — |
| `POST /api/admin/studios` | POST | `auth:sanctum` + `can:studios.manage` | — |
| `PUT /api/admin/studios/{studio}` | PUT | `auth:sanctum` + `can:studios.manage` | — |
| `DELETE /api/admin/studios/{studio}` | DELETE | `auth:sanctum` + `can:studios.manage` | — |
| `POST /api/admin/users` | POST | `auth:sanctum` + `can:users.assign-roles` | — |
| `GET /api/admin/users` | GET | `auth:sanctum` + `can:users.assign-roles` | — (optionaler `role`-Filter) |
| `PUT /api/admin/users/{user}` | PUT | `auth:sanctum` + `can:users.assign-roles` | — (E-Mail unique-ignore-self, Enum-Rolle, Passwort nur bei Befüllung, Selbst-Demotion → 422) |
| `DELETE /api/admin/users/{user}` | DELETE | `auth:sanctum` + `can:users.assign-roles` | — (Selbstlöschung → 422) |
| `PUT /api/studios/{studio}/details` | PUT | `auth:sanctum` + `can:studios.update` | `StudioPolicy` (studio_id) |
| `POST /api/studios/{studio}/rooms` | POST | `auth:sanctum` + `can:rooms.manage` | `StudioPolicy` (studio_id) |
| `PUT /api/studios/{studio}/rooms/{room}` | PUT | `auth:sanctum` + `can:rooms.manage` | `StudioPolicy` (studio_id) |
| `DELETE /api/studios/{studio}/rooms/{room}` | DELETE | `auth:sanctum` + `can:rooms.manage` | `StudioPolicy` (studio_id) |
| `GET /api/studios/{studio}/rooms` | GET | `auth:sanctum` + `can:courses.view` | `StudioPolicy` (studio_id, via Scope) |
| `GET /api/studios/{studio}/trainers` | GET | `auth:sanctum` + `can:courses.view` | `StudioPolicy` (studio_id, via Scope) |
| `POST /api/studios/{studio}/trainers` | POST | `auth:sanctum` + `can:courses.manage` | `StudioPolicy` (manageCourses) |
| `POST /api/studios/{studio}/trainers/attach` | POST | `auth:sanctum` + `can:courses.manage` | `StudioPolicy` (manageCourses) |
| `DELETE /api/studios/{studio}/trainers/{trainer}` | DELETE | `auth:sanctum` + `can:courses.manage` | `StudioPolicy` (manageCourses) |
| `POST /api/studios/{studio}/courses` | POST | `auth:sanctum` + `can:courses.manage` | `StudioPolicy` (studio_id) |
| `PUT /api/studios/{studio}/courses/{course}` | PUT | `auth:sanctum` + `can:courses.manage` | `StudioPolicy` (studio_id) |
| `DELETE /api/studios/{studio}/courses/{course}` | DELETE | `auth:sanctum` + `can:courses.manage` | `StudioPolicy` (studio_id) |
| `GET /api/studios/{studio}/courses/{course}/sessions` | GET | `auth:sanctum` + `can:courses.view` | `StudioPolicy` (studio_id) |
| `POST /api/studios/{studio}/courses/{course}/sessions` | POST | `auth:sanctum` + `can:courses.manage` | `CoursePolicy` (update) |
| `DELETE /api/studios/{studio}/courses/{course}/sessions/{session}` | DELETE | `auth:sanctum` + `can:courses.manage` | `CoursePolicy` (update) |
| `PUT /api/studios/{studio}/courses/{course}/sessions/{session}` | PUT | `auth:sanctum` + `can:courses.manage` | `CoursePolicy` (update) |
| `POST /api/studios/{studio}/courses/{course}/sessions/generate` | POST | `auth:sanctum` + `can:courses.manage` | `CoursePolicy` (update) — erzeugt idempotent die Termine der nächsten N Wochen (Default 4, max 12) |
| `GET /api/bookings` | GET | `auth:sanctum` + `can:bookings.manage` | `BookingPolicy` (view, via Scope) |
| `GET /api/course_sessions` | GET | `auth:sanctum` + `can:courses.view` | — (Katalog: nur `scheduled`/`substituted`, Filter `from`/`to`/`studio_id`, mit `free_spots` + `my_booking_id` + `trainer_id`) |
| `PUT /api/course_sessions/{session}` | PUT | `auth:sanctum` + `can:course-updates.create` | — (Rollen-Scopes → 404; Trainer nur `status`/`session_info`, Rest Admins) |
| `POST /api/courses/{course}/updates` | POST | `auth:sanctum` + `can:course-updates.create` | `CoursePolicy` (Pivot/Studio) |
| `PUT /api/courses/{course}/updates/{update}` | PUT | `auth:sanctum` + `can:course-updates.create` | `CoursePolicy` (Pivot/Studio) |
| `POST /api/bookings` | POST | `auth:sanctum` + `can:bookings.manage` | — (Kapazität + Unique) |
| `DELETE /api/bookings/{booking}` | DELETE | `auth:sanctum` + `can:bookings.manage` | `BookingPolicy` (Ownership) |

Studio-Scoping: Controller lösen `{studio}`, `{course}`, `{room}` stets im
Tenant-Kontext auf (`where('studio_id', $user->studio_id)`); fremde IDs → 404.
Customer-Routen (`/api/bookings`, Kurs-Listen) sind nicht studioseitig gescoped.

## Offene Punkte

- **Abweichung revidiert — `trainer_id` inzwischen wieder eingeführt (2026-10-15):**
  Mit der Aufspaltung in `courses` (Regeltermin) + `course_sessions` (Tagesinstanz) wurde
  `trainer_id` auf **beiden** Tabellen (nullable FK → `users`, nullOnDelete) wieder
  eingeführt: `courses.trainer_id` = regulärer Lead-Trainer, `course_sessions.trainer_id`
  = Termin-Trainer (ermöglicht Vertretung; NULL → Fallback auf Lead-Trainer). Die Pivot
  `course_user` bleibt deferred/optional. **Neu (2026-10-15): Trainer arbeiten n:m
  für mehrere Studios** via Pivot-Tabelle `studio_user` (`user_id` + `studio_id`,
  unique) mit Relationen `User::studios()` / `Studio::trainers()`. `users.studio_id`
  wird nur für Studio-Admins ausgewertet (Trainer: `NULL`). `CoursePolicy` erlaubt
  Trainer-Leitung bei Lead-, Vertretungs- oder Studio-Pivot-Zuweisung; Scopes
  (`forTenant`, `Course::assignedTo`) lösen über die Pivot auf und liefern weiter
  404 statt 403.
- **Kapazitäts-Kaskade / Lead-Room offene Frage:** `courses` trägt bewusst **kein**
  `room_id` (nach Nutzerabschluss). `courses.capacity` NULL soll auf „Raum-Capacity"
  zurückfallen — ein Kurs hat aber keine zugewiesene Raum-Lesebasis. Offen ist, ob (a)
  ein optionales `default_room_id` ergänzt oder (b) die Inheritance erst auf Model-
  Ebene über die Standard-Session entfällt (`first non-null win: session → course → null`,
  Room-Kapazität nur an Session verbindlich). Die Datenbank hat die NULL-Semantik; die
  Regel wird im Model implementiert.
- **Rollen-Modell-Abweichung:** TODO-Akzeptanzkriterium 1 nennt „Tabelle `roles` ODER
  Role-Feld"; gewählt wurde das String-Enum-Feld (`App\Enums\UserRole`). Keine
  `roles`-Tabelle.
- **`courses.view` für Gäste:** Aktuell nur für eingeloggte User geplant; ob die
  Kursliste öffentlich (ohne Login) einsehbar sein soll, ist nicht entschieden.
- **`bookings.manage` für Admin:** Aktuell ✅ via Super-Admin-Vollzugriff. Falls der
  Admin nie selbst buchen können soll, Matrix + Gate anpassen.
- **Benachrichtigungen:** Mail/Push bei `cancelled`/`substitute`-Updates ist nicht
  spezifiziert und Teil einer späteren Iteration.
- **`sanctum.stateful`-Produktionsorigin:** Sobald das Frontend deployt wird, Origin
  ergänzen (Dev: `http://localhost:5173`).
- **Raum-Doppelbelegung / Zeitkonflikte** zwischen Kursen sind nicht Teil dieser
  Iteration.
- **Passwort-Reset / E-Mail-Verification:** Laravel-Default-Flows, hier nicht vertieft.
- **Frontend-Anteile** (React Auth-Context, `<ProtectedRoute allowedRoles={[...]} />`,
  visuelles Ausblenden) sind im TODO beschrieben, aber nicht Gegenstand dieser
  Backend-Architektur — Umsetzung folgt separat.
