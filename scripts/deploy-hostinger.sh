#!/usr/bin/env bash
set -euo pipefail

# Script canónico de despliegue para app.pulsosur.com en Hostinger.
BASE=${DEPLOY_BASE:-/home/u670461366/domains/app.pulsosur.com}
REPO=${DEPLOY_REPOSITORY:-https://github.com/sts19813/wordpress_ia.git}
BRANCH=${1:-main}
PHP=${PHP_BINARY:-/opt/alt/php85/usr/bin/php}
COMPOSER=${COMPOSER_BINARY:-$(command -v composer)}
NODE=${NODE_BINARY:-/opt/alt/alt-nodejs22/root/usr/bin/node}
NPM=${NPM_BINARY:-/opt/alt/alt-nodejs22/root/usr/bin/npm}
TS=$(date +%Y%m%d%H%M%S)
RELEASE="$BASE/releases/release_$TS"
PREVIOUS=$(readlink -f "$BASE/current" 2>/dev/null || true)

# npm usa `env node` internamente; el binario de Node de Hostinger no está en
# el PATH predeterminado de las sesiones SSH ni de cron.
export PATH="$(dirname "$NODE"):$PATH"
# Rolldown (Vite) usa Rayon al compilar. En el plan compartido el límite de
# procesos/hilos puede impedir el pool predeterminado; un hilo basta para el
# build y evita abortar el despliegue antes del cambio de release.
export RAYON_NUM_THREADS="${RAYON_NUM_THREADS:-1}"

mkdir -p "$BASE/releases" "$BASE/shared" "$BASE/backups"

echo "==> Clonando $REPO ($BRANCH)"
git clone --depth=1 --branch "$BRANCH" "$REPO" "$RELEASE"

cd "$RELEASE"
ln -sfn "$BASE/shared/.env" .env
rm -rf storage
ln -sfn "$BASE/shared/storage" storage

echo "==> Instalando dependencias PHP"
"$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction

echo "==> Compilando assets"
"$NPM" install --ignore-scripts
"$NPM" run build
rm -rf node_modules

echo "==> Modo mantenimiento"
if [ -n "$PREVIOUS" ] && [ -d "$PREVIOUS" ]; then
    cd "$PREVIOUS"
    "$PHP" artisan down --retry=60 || true
fi

cd "$RELEASE"
"$PHP" artisan storage:link || true
"$PHP" artisan migrate --force
"$PHP" artisan optimize:clear
"$PHP" artisan optimize

# El archivo se versiona en el repositorio; debe ser legible por Apache para
# que Laravel resuelva las rutas públicas usadas por la API de Instagram.
chmod 644 public/.htaccess

ln -sfn "$RELEASE" "$BASE/current"
if [ -L "$BASE/public_html" ]; then
    ln -sfn "$BASE/current/public" "$BASE/public_html"
else
    mv "$BASE/public_html" "$BASE/public_html.backup.$TS"
    ln -s "$BASE/current/public" "$BASE/public_html"
fi

"$PHP" artisan up || true
find "$BASE/shared/storage" "$RELEASE/bootstrap/cache" -type d -exec chmod 755 {} \;
find "$BASE/shared/storage" "$RELEASE/bootstrap/cache" -type f -exec chmod 644 {} \;

echo "==> Deploy listo: $RELEASE"
