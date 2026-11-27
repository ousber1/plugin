#!/usr/bin/env bash
# Builds the cPanel release ZIP from the committed code (uncommitted changes are NOT included).
#
#   bin/build-release.sh              → dist/berradi-wall-art-<version>-cpanel.zip
#   OUT_DIR=/tmp/out bin/build-release.sh
#
# Needs: git, PHP 8.2+, Composer, zip. Steps:
#   1. git archive HEAD (so .env, storage contents, uploads, backups never enter the package)
#   2. remove development-only files (tests, CI, editor configs)
#   3. composer install --no-dev with an optimized, authoritative-free classmap
#   4. trim vendor/ (tests, docs, examples, VCS folders) — Dompdf fonts are kept
#   5. lint every PHP file of the package, add INSTALLATION-CPANEL.txt, zip
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
OUT_DIR="${OUT_DIR:-$ROOT/dist}"
VERSION="$(php -r '$c = require "config/app.php"; echo $c["version"];' 2>/dev/null || true)"
if [ -z "$VERSION" ]; then
  VERSION="$(grep -oE "'version' => '[^']+'" config/app.php | head -1 | sed -E "s/.*'([^']+)'$/\1/")"
fi
NAME="berradi-wall-art"
ZIP="$OUT_DIR/$NAME-$VERSION-cpanel.zip"

command -v composer >/dev/null || { echo "composer is required" >&2; exit 1; }
command -v zip >/dev/null || { echo "zip is required" >&2; exit 1; }
if ! git diff --quiet HEAD -- . 2>/dev/null; then
  echo "warning: uncommitted changes are not part of the package (git archive HEAD)" >&2
fi

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
PKG="$WORK/$NAME"
mkdir -p "$WORK/src" "$OUT_DIR"

# 1. committed files of this project only (works whether the project is the repo root or a sub-folder)
PREFIX="$(git rev-parse --show-prefix)"
git -C "$(git rev-parse --show-toplevel)" archive --format=tar HEAD ${PREFIX:+"$PREFIX"} | tar -x -C "$WORK/src"
mv "$WORK/src/${PREFIX%/}" "$PKG"

# 2. development-only files
rm -rf "$PKG/tests" "$PKG/.github" "$PKG/.devcontainer" "$PKG/.vscode" "$PKG/.idea" "$PKG/dist" \
       "$PKG/phpunit.xml" "$PKG/phpunit.xml.dist" "$PKG/.editorconfig" "$PKG/.gitattributes"
rm -f "$PKG/.env" "$PKG/storage/installed.lock"

# 3. production dependencies
(cd "$PKG" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --quiet)

# 4. trim vendor (keeps every runtime file, including dompdf/lib/fonts)
find "$PKG/vendor" -depth -type d \( -name .git -o -name .github -o -iname tests -o -iname test -o -iname docs -o -iname doc \
     -o -iname examples -o -iname example \) -prune -exec rm -rf {} +
find "$PKG/vendor" -type f \( -name 'phpunit.xml*' -o -name '.travis.yml' -o -name '.gitignore' -o -name '.gitattributes' \
     -o -name 'phpstan*.neon*' -o -name '.php-cs-fixer*' -o -name 'psalm.xml*' \) -delete
test -d "$PKG/vendor/dompdf/dompdf/lib/fonts" || { echo "dompdf fonts missing after trim" >&2; exit 1; }

# 5. lint, installation notes, zip
errors=0
while IFS= read -r -d '' f; do
  php -l "$f" >/dev/null 2>&1 || { echo "syntax error: ${f#$PKG/}" >&2; errors=$((errors + 1)); }
done < <(find "$PKG" -path "$PKG/vendor" -prune -o -name '*.php' -print0)
[ "$errors" -eq 0 ] || { echo "$errors file(s) with syntax errors" >&2; exit 1; }

cat > "$PKG/INSTALLATION-CPANEL.txt" <<TXT
BERRADI WALL ART $VERSION — installation on cPanel shared hosting
=====================================================================
Requirements: PHP 8.2, 8.3 or 8.4 (pdo_mysql, mbstring, openssl, fileinfo, gd, dom, curl, intl, zip),
MySQL 5.7+ or MariaDB 10.4+, Apache with .htaccess (mod_rewrite). The vendor/ folder is included:
Composer is NOT needed on the server.

NEW INSTALLATION
 1. cPanel > MySQL Databases: create a database and a user, add the user with ALL PRIVILEGES.
 2. File Manager: upload this ZIP to your home folder (/home/USER/), NOT inside public_html, and extract it.
    You get /home/USER/$NAME
 3. cPanel > Domains > Manage > Document Root = $NAME/public
    (if you cannot change it: copy the CONTENT of $NAME/public into public_html and create
     public_html/app_path.php containing:  <?php return '/home/USER/$NAME';  )
 4. cPanel > Select PHP Version: 8.2+ with the extensions above; upload_max_filesize 32M, post_max_size 40M,
    memory_limit 256M.
 5. Open https://YOUR-DOMAIN/install and follow the 10 steps.
 6. cPanel > Cron Jobs, every 15 minutes (use your host's PHP path):
    */15 * * * * /usr/local/bin/php /home/USER/$NAME/bin/console cron:run >/dev/null 2>&1
 7. SSL: run AutoSSL, then uncomment the "force HTTPS" lines in public/.htaccess.
 8. Back office > Maintenance: every point of the security check should be green.

UPDATING AN EXISTING INSTALLATION
 1. Back office > Maintenance > Backups > Back up now, and download the file.
 2. Extract the new ZIP next to the old folder, then copy from the OLD installation into the new one:
    .env   storage/ (whole folder)   public/uploads/ (option public_html: keep public_html/uploads)
 3. Swap the folders, then Back office > Maintenance > Update.
 4. Coming from a version before 1.0.0: delete public/robots.txt (it is now generated).

Full guide: docs/DEPLOYMENT_CPANEL.md   ·   Team guide (French): docs/USER_GUIDE.md
TXT

rm -f "$ZIP"
(cd "$WORK" && zip -rq9 "$ZIP" "$NAME")
echo "$ZIP ($(du -h "$ZIP" | cut -f1))"
