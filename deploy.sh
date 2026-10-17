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

# --- 4. PUBLIC_HTML STRUKTUR ZUSAMMENFÜHREN ---
echo "🔀 Verschmelze React-Build und Laravel-Public in public_html..."

# A) Laravel-Public-Basis kopieren (index.php, robots.txt, etc.)
cp -r "$BACKEND_DIR/public/." "$OUTPUT_DIR/public_html/"

# B) React-Artefakte (index.html und assets/) hineinkopieren
cp -r "$FRONTEND_DIR/dist/." "$OUTPUT_DIR/public_html/"

# --- 5. AUTOMATISCHE ANPASSUNGEN (ROUTING & PFADE) ---
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
echo "3. Erstelle auf dem Server im Ordner 'laravel-back/' eine produktive '.env' Datei:"
echo "   - APP_URL=https://booking.rosaktiv.eu/ (muss mit PROD_URL übereinstimmen)"
echo "   - APP_KEY per 'php artisan key:generate' erzeugen (niemals lokalen Dev-Key übernehmen)"
echo "   - DotRoll-MySQL-Daten eintragen: DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD"
echo "4. Auf dem Server in 'laravel-back/' ausführen:"
echo "   composer install --no-dev --optimize-autoloader   (installiert vendor frisch auf dem Server – nicht hochladen!)"
echo "   php artisan migrate --force"
echo "   php artisan storage:link"
echo "   php artisan config:cache"
echo "5. Sanctum/HTTPS-Hinweis: SESSION_SECURE_COOKIE=true setzen; SESSION_DOMAIN und"
echo "   SANCTUM_STATEFUL_DOMAINS auf die echte Domain setzen (ohne Protokoll, z. B. booking.rosaktiv.eu)."
echo "------------------------------------------------------------------------"
