#!/bin/bash

# --- FEHLER-ABBRUCH AKTIVIEREN ---
set -e

# --- KONFIGURATION ---
# ANPASSEN: Echte Produktions-Domain eintragen (wird als API-Basis in den Frontend-Build eingebacken).
PROD_URL="https://booking.rosaktiv.eu/"
OUTPUT_DIR="deploy_output"
BACKEND_DIR="backend"        # Name deines Laravel-Ordners
FRONTEND_DIR="frontend"      # Name deines React-Ordners

echo "🚀 Starte Vorbereitung für das DotRoll-Webhosting Deployment..."

# --- 1. AUFRÄUMEN ---
echo "🧹 Bereinige alte Build-Artefakte..."
rm -rf "$OUTPUT_DIR"
rm -rf "$FRONTEND_DIR/dist"
mkdir -p "$OUTPUT_DIR/laravel-back"
mkdir -p "$OUTPUT_DIR/public_html"

# --- 2. FRONTEND BUILD (REACT SPA) ---
echo "📦 Baue das React Frontend via Vite..."
cd "$FRONTEND_DIR"
pnpm install --frozen-lockfile
pnpm lint:fix && VITE_API_BASE_URL="$PROD_URL" pnpm build
cd ..

# --- 3. BACKEND VORBEREITEN (LARAVEL) ---
echo "⚙️ Bereite Laravel-Dateien vor..."
# Produktions-Vendor ohne Dev-Pakete bauen, damit kein dev-vendor paketiert wird
if ! command -v composer >/dev/null 2>&1; then
  echo "❌ FEHLER: 'composer' wurde nicht gefunden. Bitte Composer installieren und erneut ausführen." >&2
  exit 1
fi
echo "📦 Installiere Composer-Abhängigkeiten für Produktion (ohne Dev-Pakete)..."
(cd "$BACKEND_DIR" && composer install --no-dev --prefer-dist --optimize-autoloader)

echo "📂 Kopiere Laravel-Dateien (Git Bash-kompatibel)..."
# Kopiere das gesamte Backend-Verzeichnis in das Zielverzeichnis
cp -r "$BACKEND_DIR/." "$OUTPUT_DIR/laravel-back/"

# Bereinige die Verzeichnisse und Dateien, die nicht auf den Server gehören
rm -rf "$OUTPUT_DIR/laravel-back/public"
rm -rf "$OUTPUT_DIR/laravel-back/node_modules"
rm -rf "$OUTPUT_DIR/laravel-back/vendor"
rm -rf "$OUTPUT_DIR/laravel-back/.env"
rm -rf "$OUTPUT_DIR/laravel-back/.git"
rm -rf "$OUTPUT_DIR/laravel-back/tests"
rm -f "$OUTPUT_DIR/laravel-back/storage/framework/cache/data/"* 2>/dev/null || true
rm -f "$OUTPUT_DIR/laravel-back/storage/framework/sessions/"* 2>/dev/null || true
rm -f "$OUTPUT_DIR/laravel-back/storage/framework/views/"* 2>/dev/null || true
rm -f "$OUTPUT_DIR/laravel-back/storage/logs/"* 2>/dev/null || true

# --- 4. PRODUKTIVE .ENV ERSTELLEN ---
echo "🔐 Erstelle produktive .env für DotRoll..."
cat << 'EOF' > "$OUTPUT_DIR/laravel-back/.env"
APP_NAME=Laravel
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://booking.rosaktiv.eu/

APP_LOCALE=de
APP_FALLBACK_LOCALE=de
APP_FAKER_LOCALE=de_DE

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=ciavpjzc_lara848
DB_USERNAME=ciavpjzc_lara848
DB_PASSWORD=249]SP6Ap]

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=booking.rosaktiv.eu
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"

FRONTEND_URL=https://booking.rosaktiv.eu/

SANCTUM_STATEFUL_DOMAINS=booking.rosaktiv.eu

# Admin user seeding (DatabaseSeeder)
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=Fitness!Admin-2026-Booking
STUDIO_ADMIN_PASSWORD=Fitness!Studio-Admin-2026-Booking
TRAINER_PASSWORD=Fitness!Trainer-2026-Booking
CUSTOMER_PASSWORD=Fitness!Customer-2026-Booking
EOF

# --- 6. PUBLIC_HTML STRUKTUR ZUSAMMENFÜHREN ---
echo "🔀 Verschmelze React-Build und Laravel-Public in public_html..."

# A) Laravel-Public-Basis kopieren (index.php, robots.txt, etc.)
cp -r "$BACKEND_DIR/public/." "$OUTPUT_DIR/public_html/"

# B) React-Artefakte (index.html und assets/) hineinkopieren
cp -r "$FRONTEND_DIR/dist/." "$OUTPUT_DIR/public_html/"

# --- 7. AUTOMATISCHE ANPASSUNGEN (ROUTING & PFADE) ---
echo "📝 Passe Pfade in public_html/index.php an..."
# Ändert die relativen Pfade der verschobenen index.php, damit sie laravel-back findet
sed -i "s|__DIR__.'/../|__DIR__.'/../laravel-back/|g" "$OUTPUT_DIR/public_html/index.php"

# C) Kombinierte .htaccess für Kombi-Routing schreiben
echo "🌐 Erstelle optimierte .htaccess für kombiniertes SPA & API-Routing..."
cat << 'EOF' > "$OUTPUT_DIR/public_html/.htaccess"
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /

    # 1. API-Anfragen direkt an Laravels index.php leiten
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_URI} ^/api/
    RewriteRule ^(.*)$ index.php [L]

    # 2. React SPA-Routing: Wenn Datei nicht existiert, lade die React index.html
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.html [L]
</IfModule>
EOF

# --- FERTIFFESTELLUNG ---
echo "✅ FERTIG! Die deployfähige Struktur wurde im Ordner /$OUTPUT_DIR/ generiert."
echo "------------------------------------------------------------------------"
echo "👉 NÄCHSTE SCHRITTE FÜR DICH:"
echo "1. Lade den Inhalt von '$OUTPUT_DIR/laravel-back' in das Root-Verzeichnis deines Webspace hoch."
echo "2. Lade den Inhalt von '$OUTPUT_DIR/public_html' komplett in dein 'public_html' Verzeichnis auf dem Server."
echo "3. Auf dem Server in 'laravel-back/' ausführen:"
echo "   composer install --no-dev --optimize-autoloader   (installiert vendor frisch auf dem Server – nicht hochladen!)"
echo "   php artisan key:generate                          (APP_KEY erzeugt)"
echo "   php artisan migrate --force"
echo "   php artisan storage:link"
echo "   php artisan config:cache"
echo "4. Die .env Datei liegt bereits bei (mit DotRoll-MySQL, APP_URL, Sanctum-Domain)."
echo "   Prüfe sie ggf. und passe bei Bedarf MAIL- oder weitere Werte an."
echo "------------------------------------------------------------------------"

# --- 8. DEPLOY-MARKER FÜR INKREMENTELLES DEPLOYMENT ---
# Merkt sich, welcher Commit vollständig deployt wurde, damit deploy_diff.sh
# beim nächsten Mal nur die Änderungen seit diesem Stand packt.
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  REPO_ROOT="$(git rev-parse --show-toplevel)"
  git rev-parse HEAD > "$REPO_ROOT/.deploy_last"
  GIT_COMMON_DIR="$(git rev-parse --git-common-dir)"
  case "$GIT_COMMON_DIR" in
    /* | [A-Za-z]:/*) ;;
    *) GIT_COMMON_DIR="$REPO_ROOT/$GIT_COMMON_DIR" ;;
  esac
  EXCLUDE_FILE="$GIT_COMMON_DIR/info/exclude"
  mkdir -p "$(dirname "$EXCLUDE_FILE")"
  for entry in ".deploy_last" "deploy_diff/"; do
    grep -qxF "$entry" "$EXCLUDE_FILE" 2>/dev/null || printf '%s\n' "$entry" >> "$EXCLUDE_FILE"
  done
fi
