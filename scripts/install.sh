#!/usr/bin/env bash
# BC Staff first install (Debian 13). Run: sudo bash scripts/install.sh
set -Eeuo pipefail
trap 'echo "FEJL: Installation stoppet ved linje $LINENO" >&2' ERR
DOMAIN=dev-staff01.bellcom.dk
APP=/var/www/bc-staff
[ "$EUID" -eq 0 ] || { echo "FEJL: Brug sudo bash" >&2; exit 1; }
. /etc/os-release
[ "$ID" = debian ] && [ "$VERSION_ID" = 13 ] || { echo "FEJL: Kræver Debian 13" >&2; exit 1; }
apt-get update
apt-get install -y nginx mariadb-server git curl unzip openssl php8.4-cli php8.4-fpm php8.4-mysql php8.4-xml php8.4-mbstring php8.4-gd php8.4-curl php8.4-zip php8.4-intl php8.4-bcmath composer certbot python3-certbot-nginx
systemctl enable --now nginx mariadb php8.4-fpm
id bcstaff >/dev/null 2>&1 || useradd --system --home-dir /var/lib/bcstaff --create-home --shell /usr/sbin/nologin bcstaff
[ ! -e "$APP" ] || { echo "FEJL: $APP findes allerede. Stopper for ikke at overskrive data." >&2; exit 1; }
git clone https://github.com/bellcom/bc_staff.git "$APP"
chown -R bcstaff:www-data "$APP"
cd "$APP"
if [ -f composer.lock ]; then
  runuser -u bcstaff -- composer install --no-interaction --prefer-dist
else
  echo "BEMÆRK: composer.lock mangler, løser afhængigheder én gang på udviklingsserveren."
  runuser -u bcstaff -- composer update --with-all-dependencies --no-interaction --prefer-dist
fi
runuser -u bcstaff -- composer check-platform-reqs
[ -x vendor/bin/drush ] || { echo "FEJL: Drush mangler" >&2; exit 1; }
echo "OK: Drupal-kode og afhængigheder installeret."
echo "Fortsæt med database, Drush site:install og HTTPS efter docs/FIRST_TIME_INSTALL.md."
