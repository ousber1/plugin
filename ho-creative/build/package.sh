#!/usr/bin/env bash
# Builds dist/ho-creative-cpanel.zip: production vendor/, compiled assets, no secrets, empty storage.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/dist}"
STAGE="$(mktemp -d)/hocreative"
mkdir -p "$STAGE" "$OUT"
rsync -a --exclude '.git' --exclude '.env' --exclude 'node_modules' --exclude 'dist' --exclude 'vendor' \
  --exclude 'database/*.sqlite' --exclude 'public/storage' --exclude '.phpunit.cache' \
  --exclude 'storage/logs/*' --exclude 'storage/framework/cache/data/*' --exclude 'storage/framework/sessions/*' \
  --exclude 'storage/framework/views/*' --exclude 'storage/app/private/*' --exclude 'storage/app/public/*' \
  --exclude 'storage/app/backups' --exclude 'storage/app/mpdf-tmp' --exclude 'storage/app/*.pdf' \
  --exclude 'storage/app/installed.lock' --exclude 'storage/app/install-token.txt' --exclude 'bootstrap/cache/*.php' \
  "$ROOT/" "$STAGE/"
cd "$STAGE"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet
# strip VCS metadata and package test suites from vendor/
find vendor -type d \( -name .git -o -name tests -o -name Tests \) -prune -exec rm -rf {} +
rm -f .phpunit.result.cache
# keep only the mPDF fonts actually used (Latin + Arabic) to shrink the archive
find vendor/mpdf/mpdf/ttfonts -type f ! -name 'DejaVu*' ! -name 'XB Riyaz*' ! -name 'FreeSans*' -delete
mkdir -p storage/app/private storage/app/public storage/app/backups storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
for d in storage/app/private storage/app/public storage/app/backups storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs; do printf '*\n!.gitignore\n' > "$d/.gitignore"; done
chmod -R ug+rwX storage bootstrap/cache
# Safety net: if someone extracts the app inside the web root, deny all HTTP access to it.
printf '<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n' > .htaccess
cd ..
# cPanel layout: web files go to ~/public_html, the application stays in ~/hocreative (outside the web root)
mv hocreative/public public_html
cp hocreative/docs/DEPLOIEMENT-CPANEL.md LISEZ-MOI-INSTALLATION.md
rm -f "$OUT/ho-creative-cpanel.zip"
zip -qr -X "$OUT/ho-creative-cpanel.zip" hocreative public_html LISEZ-MOI-INSTALLATION.md
echo "Built $OUT/ho-creative-cpanel.zip ($(du -h "$OUT/ho-creative-cpanel.zip" | cut -f1))"
