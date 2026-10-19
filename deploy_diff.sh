#!/bin/bash
#
# deploy_diff.sh — inkrementelles Deploy-Delta seit dem letzten deployten Commit.
#
# Schwester-Skript von deploy.sh: deploy.sh baut den KOMPLETTEN Upload,
# deploy_diff.sh nur die Änderungen seit der Basis (Default: .deploy_last).
#
# Basis:   Argument (Tag/Branch/SHA) > .deploy_last > HEAD~1 (mit Warnung)
# Ziel:    immer HEAD
# Ausgabe: deploy_diff/laravel-back/  -> Laravel-Root auf dem Server
#          deploy_diff/public_html/   -> public_html/ auf dem Server
#          deploy_diff/CHANGES.txt    -> was im Delta steckt
#          deploy_diff/DELETE.txt     -> was auf dem Server gelöscht werden muss

set -euo pipefail

# --- ABBRUCH-SCHUTZ ---
# Ein abgebrochener Lauf darf kein unvollständiges Delta hinterlassen
# (ohne CHANGES.txt/DELETE.txt und ggf. ohne neu gebautes Frontend).
# Entscheidung hängt an den Flags, nicht an $?: nach einem Signal misst $?
# im EXIT-Trap sonst 0 und der Abbruch bliebe unentdeckt.
DELTA_STARTED=0
DELTA_COMPLETE=0
cleanup_on_exit() {
  if [ "${DELTA_STARTED:-0}" -eq 1 ] && [ "${DELTA_COMPLETE:-0}" -eq 0 ]; then
    rm -rf "${OUTPUT_DIR:-deploy_diff}"
    echo "${ERR:-❌} Abbruch — unvollständiges Delta entfernt (${OUTPUT_DIR:-deploy_diff}/)." >&2
  fi
}
trap cleanup_on_exit EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
trap 'exit 129' HUP

# --- KONFIGURATION (muss zu deploy.sh passen) ---
PROD_URL="https://booking.rosaktiv.eu/"
OUTPUT_DIR="deploy_diff"
MARKER_FILE=".deploy_last"
BACKEND_DIR="backend"
FRONTEND_DIR="frontend"

# --- FARBEN/PRÄFIXE ---
INFO="ℹ️ "
WARN="⚠️ "
ERR="❌"

usage() {
  cat <<'EOF'
deploy_diff.sh — inkrementelles Deploy-Delta seit dem letzten deployten Commit

AUFRUF
  ./deploy_diff.sh [BASIS-REF]

  BASIS-REF    optional: Tag, Branch oder SHA der zuletzt deployten Version.
               Default: Inhalt von .deploy_last; fehlt die Datei, wird HEAD~1
               verwendet (mit deutlicher Warnung). Ziel ist immer HEAD.
  -h, --help   diese Hilfe anzeigen

ERGEBNIS
  deploy_diff/laravel-back/   -> Inhalt in den Server-Ordner 'laravel-back/' hochladen
  deploy_diff/public_html/    -> Inhalt in den Server-Ordner 'public_html/' hochladen
                                 (beide Ordner liegen im Webspace-Root)
  deploy_diff/CHANGES.txt     -> Inhalt des Deltas (inkl. nicht deploy-relevanter Dateien)
  deploy_diff/DELETE.txt      -> Pfade (relativ zum Webspace-Root), die auf dem
                                 Server gelöscht werden müssen

HINWEISE
  - Änderungen unter frontend/ lösen einen kompletten Vite-Rebuild aus; der
    komplette dist/-Inhalt landet in public_html/.
  - Gehashte Asset-Namen bedeuten: alte assets/*.js|css bleiben auf dem Server
    liegen (unschädlich, index.html zeigt auf die neuen Namen).
  - .deploy_last wird nach erfolgreichem Lauf auf HEAD gesetzt und ist über
    .git/info/exclude aus git status ausgeblendet (ebenso deploy_diff/).
EOF
}

# --- ARGUMENTE ---
BASE_ARG=""
while [ "$#" -gt 0 ]; do
  case "$1" in
    -h|--help)
      usage
      exit 0
      ;;
    --)
      shift
      if [ "$#" -gt 0 ]; then
        if [ -n "$BASE_ARG" ]; then
          echo "$ERR Zu viele Argumente: '$1' (Basis ist bereits '$BASE_ARG')." >&2
          usage >&2
          exit 1
        fi
        BASE_ARG="$1"
        shift
      fi
      if [ "$#" -gt 0 ]; then
        echo "$ERR Zu viele Argumente: '$1'." >&2
        usage >&2
        exit 1
      fi
      ;;
    -*)
      echo "$ERR Unbekannte Option: '$1'" >&2
      usage >&2
      exit 1
      ;;
    *)
      if [ -n "$BASE_ARG" ]; then
        echo "$ERR Zu viele Argumente: '$1' (Basis ist bereits '$BASE_ARG')." >&2
        usage >&2
        exit 1
      fi
      BASE_ARG="$1"
      shift
      ;;
  esac
done

# --- REPO-ROOT ---
if ! REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null)"; then
  echo "$ERR Kein Git-Repository gefunden." >&2
  exit 1
fi
cd "$REPO_ROOT"

TARGET_REF="HEAD"
TARGET_SHA="$(git rev-parse --verify "${TARGET_REF}^{commit}")"
TARGET_SHORT="$(git rev-parse --short "$TARGET_SHA")"

# --- BASIS BESTIMMEN ---
BASE_SOURCE=""
if [ -n "$BASE_ARG" ]; then
  BASE_REF="$BASE_ARG"
  BASE_SOURCE="Argument"
elif [ -f "$MARKER_FILE" ] && [ -n "$(tr -d '[:space:]' < "$MARKER_FILE")" ]; then
  BASE_REF="$(head -n 1 "$MARKER_FILE" | tr -d '[:space:]')"
  BASE_SOURCE="Marker $MARKER_FILE"
else
  BASE_REF="HEAD~1"
  BASE_SOURCE="Fallback (kein $MARKER_FILE)"
  echo ""
  echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!"
  echo "$WARN  KEIN '$MARKER_FILE' GEFUNDEN — Basis ist ersatzweise HEAD~1."
  echo "$WARN  Das Delta enthält NUR die Änderungen des letzten Commits."
  echo "$WARN  Wenn auf dem Server mehr fehlt: Basis explizit angeben, z. B."
  echo "$WARN      ./deploy_diff.sh <tag-oder-sha>"
  echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!"
  echo ""
fi

if ! BASE_SHA="$(git rev-parse --verify --quiet "${BASE_REF}^{commit}")"; then
  echo "$ERR Ungültige Basis-Referenz: '$BASE_REF' (Quelle: $BASE_SOURCE)." >&2
  echo "    Erwartet: Tag, Branch oder SHA, der als Commit existiert." >&2
  if [ "$BASE_SOURCE" = "Marker $MARKER_FILE" ]; then
    echo "    Tipp: '$MARKER_FILE' korrigieren, löschen oder Basis als Argument angeben." >&2
  elif [ "$BASE_SOURCE" = "Fallback (kein $MARKER_FILE)" ]; then
    echo "    Tipp: Basis explizit angeben, z. B. './deploy_diff.sh <tag-oder-sha>'." >&2
  fi
  exit 1
fi
BASE_SHORT="$(git rev-parse --short "$BASE_SHA")"

# --- LEERES DELTA ---
if [ "$BASE_SHA" = "$TARGET_SHA" ]; then
  echo ""
  echo "$INFO Basis ($BASE_REF, $BASE_SHORT) ist identisch mit HEAD ($TARGET_SHORT)."
  echo "$INFO Nichts zu deployen. Ein eventuell vorhandenes '$OUTPUT_DIR/' wird entfernt,"
  echo "     damit niemand versehentlich ein altes Delta hochlädt."
  rm -rf "$OUTPUT_DIR"
  echo "     '$MARKER_FILE' bleibt unverändert."
  exit 0
fi

# --- WARNUNG: DIRTY WORKING TREE ---
DIRTY="$(git -c core.quotePath=false status --porcelain)"
if [ -n "$DIRTY" ]; then
  echo ""
  echo "$WARN  WARNUNG: Der Arbeitsbaum hat uncommittete Änderungen:"
  printf '%s\n' "$DIRTY" | sed 's/^/        /'
  echo ""
  echo "     - Uncommittete Änderungen an backend/-Dateien sind NICHT Teil des"
  echo "       Deltas und fehlen auf dem Server."
  echo "       (Ist dieselbe Datei ohnehin im Delta, wird der aktuelle"
  echo "       Arbeitsbaum-Stand kopiert — dann ist die Änderung mit drin.)"
  echo "     - Uncommittete Änderungen unter frontend/ werden MITGEDEPLOYT,"
  echo "       weil der Build aus dem Arbeitsbaum läuft."
fi

UNTRACKED="$(git -c core.quotePath=false ls-files --others --exclude-standard)"
if [ -n "$UNTRACKED" ]; then
  echo ""
  echo "$WARN  Nicht versionierte (und nicht ignorierte) Dateien — NICHT im Delta:"
  printf '%s\n' "$UNTRACKED" | sed 's/^/        /'
fi
if [ -n "$DIRTY" ] || [ -n "$UNTRACKED" ]; then
  echo ""
fi

# --- SAMMLUNGEN ---
LARAVEL_FILES=()          # Pfade relativ zu laravel-back/
PUBLIC_FILES=()           # Pfade relativ zu public_html/ (aus backend/public/)
FRONTEND_FILES=()         # geänderte frontend/-Quelldateien (nur Trigger + Doku)
OTHER_FILES=()            # nicht deploy-relevant
SKIPPED_FILES=()          # backend-Pfade, die auch der Full-Deploy nicht kopiert
DELETE_LARAVEL=()         # zu löschende Serverpfade (laravel-back/...)
DELETE_PUBLIC=()          # zu löschende Serverpfade (public_html/...)
FRONTEND_DELETIONS=()     # frontend-Quelldateien gelöscht (kein Serverpfad)

FRONTEND_CHANGED=0
COMPOSER_CHANGED=0
MIGRATIONS_CHANGED=0
HTACCESS_SKIPPED=0
DEPLOY_SH_CHANGED=0
INDEX_PHP_DELETED=0
RENAMES=()

LARAVEL_COUNT=0
PUBLIC_COUNT=0
DIST_TOTAL=0

# --- HILFSFUNKTIONEN ---
is_backend_skipped() {
  local rel="$1"
  case "$rel" in
    vendor/*|node_modules/*|.git/*|.env|tests/*)
      return 0
      ;;
    storage/framework/cache/data/*|storage/framework/sessions/*|storage/framework/views/*|storage/logs/*)
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

note_flags() {
  local path="$1"
  case "$path" in
    deploy.sh)
      # deploy.sh erzeugt Server-Artefakte (.env, .htaccess), die NIE im Delta sind.
      DEPLOY_SH_CHANGED=1
      ;;
  esac
  case "$path" in
    backend/composer.json|backend/composer.lock)
      COMPOSER_CHANGED=1
      ;;
  esac
  case "$path" in
    backend/database/migrations/*)
      MIGRATIONS_CHANGED=1
      ;;
  esac
  case "$path" in
    frontend/*)
      FRONTEND_CHANGED=1
      ;;
  esac
}

copy_tracked() {
  local src="$1"
  local dest="$2"
  if [ ! -e "$src" ]; then
    echo "$WARN  Quelldatei fehlt im Arbeitsbaum: $src (uncommittete Löschung?) — übersprungen." >&2
    return 0
  fi
  mkdir -p "$(dirname "$dest")"
  cp -p "$src" "$dest"
}

add_change() {
  local path="$1"
  note_flags "$path"
  case "$path" in
    backend/public/.htaccess)
      # deploy.sh erzeugt die Server-.htaccess selbst -> nie mitkopieren.
      HTACCESS_SKIPPED=1
      ;;
    backend/public/*)
      local rel="${path#backend/public/}"
      PUBLIC_FILES+=("$rel")
      copy_tracked "$path" "$OUTPUT_DIR/public_html/$rel"
      PUBLIC_COUNT=$((PUBLIC_COUNT + 1))
      ;;
    backend/*)
      local rel="${path#backend/}"
      if is_backend_skipped "$rel"; then
        SKIPPED_FILES+=("$path")
      else
        LARAVEL_FILES+=("$rel")
        copy_tracked "$path" "$OUTPUT_DIR/laravel-back/$rel"
        LARAVEL_COUNT=$((LARAVEL_COUNT + 1))
      fi
      ;;
    frontend/*)
      FRONTEND_FILES+=("$path")
      ;;
    *)
      OTHER_FILES+=("$path")
      ;;
  esac
}

add_deletion() {
  local path="$1"
  note_flags "$path"
  case "$path" in
    backend/public/.htaccess)
      # Wird von deploy.sh erzeugt; ein rm würde das Routing zerstören.
      HTACCESS_SKIPPED=1
      ;;
    backend/public/index.php)
      # Front Controller: ohne ihn ist die Anwendung nicht erreichbar.
      INDEX_PHP_DELETED=1
      DELETE_PUBLIC+=("public_html/index.php")
      ;;
    backend/public/*)
      local rel="${path#backend/public/}"
      DELETE_PUBLIC+=("public_html/$rel")
      ;;
    backend/*)
      local rel="${path#backend/}"
      if ! is_backend_skipped "$rel"; then
        DELETE_LARAVEL+=("laravel-back/$rel")
      fi
      ;;
    frontend/*)
      FRONTEND_DELETIONS+=("$path")
      ;;
  esac
}

print_list() {
  if [ "$#" -eq 0 ]; then
    echo "  (keine)"
  else
    printf '%s\n' "$@" | sort | sed 's/^/  /'
  fi
}

# --- DIFF EINLESEN ---
echo "$INFO Ermittle Änderungen: $BASE_REF ($BASE_SHORT) -> $TARGET_REF ($TARGET_SHORT)"

DIFF_OUTPUT="$(git -c core.quotePath=false diff --name-status -M "$BASE_SHA" "$TARGET_SHA")"

# Ausgabeordner frisch aufbauen
rm -rf "$OUTPUT_DIR"
mkdir -p "$OUTPUT_DIR/laravel-back" "$OUTPUT_DIR/public_html"
DELTA_STARTED=1

while IFS=$'\t' read -r status p1 p2; do
  [ -n "${status:-}" ] || continue
  case "$status" in
    A|M|T)
      add_change "$p1"
      ;;
    C*)
      # Kopie: Quelle bleibt, Ziel ist neu
      add_change "$p2"
      ;;
    D)
      add_deletion "$p1"
      ;;
    R*)
      # Umbenennung: alten Serverpfad löschen, neuen kopieren
      add_deletion "$p1"
      add_change "$p2"
      RENAMES+=("$p1 -> $p2")
      ;;
    *)
      echo "$WARN  Unbekannter Diff-Status '$status' für '$p1' — übersprungen." >&2
      ;;
  esac
done <<< "$DIFF_OUTPUT"

# --- FRONTEND BUILD ---
DIST_FILES=()
if [ "$FRONTEND_CHANGED" -eq 1 ]; then
  if ! command -v pnpm >/dev/null 2>&1; then
    echo "$ERR 'pnpm' wurde nicht gefunden, aber das Frontend muss neu gebaut werden." >&2
    echo "    pnpm installieren (Node LTS) und erneut ausführen." >&2
    exit 1
  fi
  echo "$INFO Frontend-Änderung erkannt — baue komplettes Frontend neu (aus dem Arbeitsbaum)..."
  (
    cd "$FRONTEND_DIR"
    pnpm install --frozen-lockfile
    VITE_API_BASE_URL="$PROD_URL" pnpm build
  )
  if [ ! -f "$FRONTEND_DIR/dist/index.html" ]; then
    echo "$ERR Frontend-Build hat kein '$FRONTEND_DIR/dist/index.html' erzeugt." >&2
    exit 1
  fi
  cp -r "$FRONTEND_DIR/dist/." "$OUTPUT_DIR/public_html/"
  while IFS= read -r f; do
    DIST_FILES+=("$f")
  done < <(cd "$FRONTEND_DIR/dist" && find . -type f | sed 's|^\./||' | sort)
  DIST_TOTAL="${#DIST_FILES[@]}"
fi

# --- index.php PFAD-REWRITE (wie deploy.sh) ---
if [ -f "$OUTPUT_DIR/public_html/index.php" ]; then
  sed -i "s|__DIR__.'/../|__DIR__.'/../laravel-back/|g" "$OUTPUT_DIR/public_html/index.php"
  echo "$INFO public_html/index.php: Pfade auf ../laravel-back/ umgeschrieben."
fi

# --- CHANGES.TXT ---
DATE_STR="$(date '+%Y-%m-%d %H:%M:%S %Z' | sed 's/[[:space:]]*$//')"
{
  echo "Deploy-Delta"
  echo "============"
  echo "Basis:  $BASE_REF ($BASE_SHORT)  [Quelle: $BASE_SOURCE]"
  echo "Ziel:   $TARGET_REF ($TARGET_SHORT)"
  echo "Datum:  $DATE_STR"
  echo "Erzeugt von deploy_diff.sh (Repo: $(basename "$REPO_ROOT"))"
  echo ""
  echo "Hochladen: Inhalt von laravel-back/ -> Server-Ordner 'laravel-back/'"
  echo "           Inhalt von public_html/  -> Server-Ordner 'public_html/'"
  echo "           (beide Ordner liegen im Webspace-Root)"
  echo ""
  echo "laravel-back/ (in den Server-Ordner 'laravel-back/' hochladen)"
  echo "----------------------------------------------------------------"
  print_list "${LARAVEL_FILES[@]}"
  echo ""
  echo "public_html/ (aus backend/public/)"
  echo "----------------------------------"
  print_list "${PUBLIC_FILES[@]}"
  echo ""
  echo "public_html/ (Frontend-Build, komplett neu gebaut)"
  echo "--------------------------------------------------"
  if [ "$FRONTEND_CHANGED" -eq 1 ]; then
    echo "Rebuild ausgelöst durch:"
    REBUILD_TRIGGERS=0
    if [ "${#FRONTEND_FILES[@]}" -gt 0 ]; then
      printf '%s\n' "${FRONTEND_FILES[@]}" | sort | sed 's/^/  /'
      REBUILD_TRIGGERS=1
    fi
    if [ "${#FRONTEND_DELETIONS[@]}" -gt 0 ]; then
      printf '%s\n' "${FRONTEND_DELETIONS[@]}" | sort | sed 's/^/  (gelöscht) /'
      REBUILD_TRIGGERS=1
    fi
    if [ "$REBUILD_TRIGGERS" -eq 0 ]; then
      echo "  (keine)"
    fi
    echo ""
    echo "Erzeugte Dateien (vollständig hochladen):"
    if [ "$DIST_TOTAL" -eq 0 ]; then
      echo "  (keine)"
    else
      printf '%s\n' "${DIST_FILES[@]}" | sed 's/^/  /'
    fi
  else
    echo "  (kein Rebuild — keine Änderung unter $FRONTEND_DIR/)"
  fi
  echo ""
  if [ "${#RENAMES[@]}" -gt 0 ]; then
    echo "Umbenennungen/Verschiebungen"
    echo "----------------------------"
    printf '%s\n' "${RENAMES[@]}" | sed 's/^/  /'
    echo ""
  fi
  echo "nicht deploy-relevant (nur in dieser Liste, nicht im Upload-Delta)"
  echo "------------------------------------------------------------------------"
  print_list "${OTHER_FILES[@]}"
  echo ""
  echo "übersprungen (auch der Full-Deploy kopiert diese Pfade nicht)"
  echo "-------------------------------------------------------------"
  if [ "${#SKIPPED_FILES[@]}" -eq 0 ]; then
    echo "  (keine)"
  else
    printf '%s\n' "${SKIPPED_FILES[@]}" | sort | sed 's/^/  /'
  fi
  echo ""
  echo "Hinweise"
  echo "--------"
  if [ "$HTACCESS_SKIPPED" -eq 1 ]; then
    echo "  - backend/public/.htaccess wird NICHT gelistet und NICHT kopiert/beachtet:"
    echo "    die Server-.htaccess (SPA/API-Routing) wird von deploy.sh erzeugt."
  fi
  if [ "$DEPLOY_SH_CHANGED" -eq 1 ]; then
    echo "  - 'deploy.sh' steht im Änderungs-Nachweis dieser Liste, aber NICHT im"
    echo "    Upload-Delta — seine erzeugten Server-Artefakte ebenso wenig: die .env"
    echo "    (Vorlage in deploy.sh) und die generierte .htaccess liegen nie im Delta."
    echo "    Wurde eine dieser beiden Vorlagen in deploy.sh geändert, deploy.sh"
    echo "    komplett erneut ausführen (Full-Upload) oder die Datei auf dem Server"
    echo "    manuell anpassen."
  fi
  if [ "$INDEX_PHP_DELETED" -eq 1 ]; then
    echo "  - ACHTUNG: backend/public/index.php wurde gelöscht. Ohne Front Controller"
    echo "    ist die Anwendung auf dem Server NICHT erreichbar; siehe DELETE.txt."
  fi
  if [ "$FRONTEND_CHANGED" -eq 1 ]; then
    echo "  - Frontend neu gebaut: gehashte Asset-Namen bedeuten, dass alte"
    echo "    assets/*.js|css auf dem Server liegen bleiben. Das ist unschädlich"
    echo "    (index.html referenziert die neuen Namen), kann aber manuell"
    echo "    aufgeräumt werden."
  fi
  if [ "$HTACCESS_SKIPPED" -eq 0 ] && [ "$DEPLOY_SH_CHANGED" -eq 0 ] && \
     [ "$INDEX_PHP_DELETED" -eq 0 ] && [ "$FRONTEND_CHANGED" -eq 0 ]; then
    echo "  - (keine)"
  fi
  echo ""
  echo "Löschungen: siehe DELETE.txt"
} > "$OUTPUT_DIR/CHANGES.txt"

# --- DELETE.TXT ---
{
  echo "# DELETE.txt — Pfade, die auf dem Server gelöscht werden müssen."
  echo "# Ein Delta-Upload kann nichts löschen; diese Dateien bleiben sonst liegen."
  echo "# Alle Pfade sind relativ zum Webspace-Root (der Ordner, der"
  echo "# 'public_html/' UND 'laravel-back/' enthält) — NICHT relativ zu 'laravel-back/'."
  echo "#"
  echo "# Ausführen im Webspace-Root, z. B.:"
  echo "#   cd /pfad/zum/Webspace-Root"
  echo "#   while IFS= read -r p; do"
  echo "#     case \"\$p\" in ''|'#'*) continue ;; esac"
  echo "#     rm -f \"\$p\""
  echo "#   done < /pfad/zu/deploy_diff/DELETE.txt"
  echo "#"
  echo "# Leer gewordene Verzeichnisse bleiben ggf. zurück (manuell entfernen)."
  echo ""
  if [ "${#DELETE_LARAVEL[@]}" -gt 0 ]; then
    echo "# laravel-back/"
    printf '%s\n' "${DELETE_LARAVEL[@]}" | sort
    echo ""
  fi
  if [ "${#DELETE_PUBLIC[@]}" -gt 0 ]; then
    echo "# public_html/"
    printf '%s\n' "${DELETE_PUBLIC[@]}" | sort
    echo ""
  fi
  if [ "${#DELETE_LARAVEL[@]}" -eq 0 ] && [ "${#DELETE_PUBLIC[@]}" -eq 0 ]; then
    echo "keine"
  fi
  if [ "$INDEX_PHP_DELETED" -eq 1 ]; then
    echo ""
    echo "# !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!"
    echo "# ACHTUNG: public_html/index.php steht oben zum Löschen. Das ist der"
    echo "# Front Controller — ohne ihn ist die Anwendung auf dem Server NICHT"
    echo "# erreichbar. Nur löschen, wenn der neue Stand einen Ersatz mitbringt."
    echo "# !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!"
  fi
  if [ "${#FRONTEND_DELETIONS[@]}" -gt 0 ]; then
    echo ""
    echo "# Frontend-Quelldateien gelöscht — KEIN Serverpfad, der Rebuild ersetzt den Build."
    echo "# Dort nichts löschen; nur zur Information:"
    printf '%s\n' "${FRONTEND_DELETIONS[@]}" | sort | sed 's/^/#   /'
  fi
} > "$OUTPUT_DIR/DELETE.txt"

# Delta ist vollständig (CHANGES.txt + DELETE.txt stehen). Bewusst VOR dem
# Marker: schlägt der Marker-Write oder info/exclude fehl, bleibt das komplette
# Delta liegen und nur .deploy_last ist veraltet (der nächste Lauf baut
# dasselbe Delta erneut) — statt Delta weg + Marker auf HEAD (stiller Verlust).
DELTA_COMPLETE=1

# --- MARKER + INFO/EXCLUDE ---
printf '%s\n' "$TARGET_SHA" > "$MARKER_FILE"

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

# --- ABSCHLUSS ---
PUBLIC_TOTAL=$((PUBLIC_COUNT + DIST_TOTAL))
DELETE_TOTAL=$((${#DELETE_LARAVEL[@]} + ${#DELETE_PUBLIC[@]}))

echo ""
echo "✅ Delta erzeugt: $OUTPUT_DIR/"
echo "   laravel-back/ : $LARAVEL_COUNT Datei(en)"
echo "   public_html/  : $PUBLIC_TOTAL Datei(en) (davon $DIST_TOTAL aus dem Frontend-Build)"
echo "   Löschungen    : $DELETE_TOTAL (siehe $OUTPUT_DIR/DELETE.txt)"
echo "   Basis         : $BASE_REF ($BASE_SHORT)  ->  HEAD ($TARGET_SHORT)"
echo "   Marker        : $MARKER_FILE = $TARGET_SHORT"

if [ "$DEPLOY_SH_CHANGED" -eq 1 ]; then
  echo ""
  echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!"
  echo "⚠️   ACHTUNG: 'deploy.sh' steht im Änderungs-Nachweis, aber nicht im Upload-Delta —"
  echo "     seine erzeugten Server-Artefakte sind ebenfalls nicht im Delta:"
  echo "     die .env (Vorlage in deploy.sh, Zeilen 57-131) und die generierte"
  echo "     .htaccess. Wurde eine dieser Vorlagen geändert, entweder deploy.sh"
  echo "     komplett erneut ausführen (Full-Upload) oder die Serverdatei manuell"
  echo "     anpassen. Der Marker ist trotzdem auf HEAD gesetzt."
  echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!"
fi
if [ "$INDEX_PHP_DELETED" -eq 1 ]; then
  echo ""
  echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!"
  echo "⚠️   ACHTUNG: backend/public/index.php wurde gelöscht."
  echo "     public_html/index.php steht in DELETE.txt — ohne diesen Front"
  echo "     Controller ist die Anwendung auf dem Server NICHT erreichbar."
  echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!"
fi

echo ""
echo "👉 NÄCHSTE SCHRITTE"
echo "1. Inhalt von '$OUTPUT_DIR/laravel-back/' in den Server-Ordner 'laravel-back/' hochladen."
echo "2. Inhalt von '$OUTPUT_DIR/public_html/' in den Server-Ordner 'public_html/' hochladen."
echo "   (beide Ordner liegen im Webspace-Root; nichts doppelt verschachteln)"
echo "3. Löschungen aus '$OUTPUT_DIR/DELETE.txt' im Webspace-Root ausführen"
echo "   (der Ordner, der 'public_html/' und 'laravel-back/' enthält)."
echo "4. Auf dem Server im Laravel-Root ('laravel-back/') ausführen:"
if [ "$COMPOSER_CHANGED" -eq 1 ]; then
  echo "   composer install --no-dev --optimize-autoloader"
fi
if [ "$MIGRATIONS_CHANGED" -eq 1 ]; then
  echo "   php artisan migrate --force"
fi
if [ "$LARAVEL_COUNT" -gt 0 ] || [ "${#DELETE_LARAVEL[@]}" -gt 0 ]; then
  # Auch bei reinen Löschungen: eine entfernte Datei kann eine veraltete
  # bootstrap/cache/config.php auf dem Server ungültig machen -> neu bauen.
  echo "   php artisan config:cache"
fi
if [ "$COMPOSER_CHANGED" -eq 0 ] && [ "$MIGRATIONS_CHANGED" -eq 0 ] && \
   [ "$LARAVEL_COUNT" -eq 0 ] && [ "${#DELETE_LARAVEL[@]}" -eq 0 ]; then
  echo "   (keine — das Delta enthält keine Änderung für 'laravel-back/')"
fi
if [ "$FRONTEND_CHANGED" -eq 1 ]; then
  echo "5. Browser hart neu laden (Strg+F5 / Cmd+Shift+R) — Frontend wurde neu gebaut."
fi
echo ""
