#!/usr/bin/env bash
# BC Staff phase 2: complete existing Drupal Composer installation.
# Use: sudo bash /var/www/bc-staff/scripts/setup-site.sh
set -Eeuo pipefail
trap 'echo "FEJL: Site-opsætning stoppede ved linje $LINENO" >&2' ERR

APP=/var/www/bc-staff
DOMAIN=dev-staff01.bellcom.dk
SECRET=/root/bc-staff-secrets
[ "$EUID" -eq 0 ] || { echo "FEJL: Kræver sudo." >&2; exit 1; }
[ -x "$APP/vendor/bin/drush" ] || { echo "FEJL: Composer/Drush mangler." >&2; exit 1; }
[ ! -f "$APP/web/sites/default/settings.php" ] || { echo "FEJL: Drupal er allerede installeret. Stopper." >&2; exit 1; }
[ ! -e "$SECRET" ] || { echo "FEJL: Secrets findes fra en tidligere kørsel. Stopper." >&2; exit 1; }
[ "$(curl -4fsS https://api.ipify.org)" = "$(getent ahostsv4 "$DOMAIN" | awk 'NR==1 {print $1}')" ] || { echo "FEJL: DNS stemmer ikke med server-IP." >&2; exit 1; }
systemctl is-active --quiet mariadb || { echo "FEJL: MariaDB kører ikke." >&2; exit 1; }
systemctl is-active --quiet php8.4-fpm || { echo "FEJL: PHP-FPM kører ikke." >&2; exit 1; }

umask 077
DB_PASSWORD="$(openssl rand -hex 24)"
ADMIN_PASSWORD="$(openssl rand -hex 24)"
printf 'DB_PASSWORD=%s\nADMIN_PASSWORD=%s\n' "$DB_PASSWORD" "$ADMIN_PASSWORD" > "$SECRET"
chmod 600 "$SECRET"
mariadb -e 'CREATE DATABASE IF NOT EXISTS bc_staff CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
mariadb -e "CREATE USER IF NOT EXISTS 'bc_staff'@'localhost' IDENTIFIED BY '$DB_PASSWORD'"
mariadb -e "ALTER USER 'bc_staff'@'localhost' IDENTIFIED BY '$DB_PASSWORD'"
mariadb -e "GRANT ALL PRIVILEGES ON bc_staff.* TO 'bc_staff'@'localhost'"
install -d -o bcstaff -g www-data -m 2770 "$APP/web/sites/default/files"
runuser -u bcstaff -- "$APP/vendor/bin/drush" --root="$APP/web" --uri="http://$DOMAIN" site:install standard --db-url="mysql://bc_staff:$DB_PASSWORD@localhost/bc_staff" --site-name="BC Staff" --account-name=bcadmin --account-pass="$ADMIN_PASSWORD" --account-mail=js@bellcom.dk -y
printf '\n// BC Staff trusted host\n%s\n' "\$settings['trusted_host_patterns'] = ['^dev-staff01\\.bellcom\\.dk\$'];" >> "$APP/web/sites/default/settings.php"
chown bcstaff:www-data "$APP/web/sites/default/settings.php"
chmod 640 "$APP/web/sites/default/settings.php"
echo "OK: Drupal installeret. Credentials ligger i $SECRET."
bash "$APP/scripts/setup-drush.sh"
echo "Næste trin: scripts/setup-web.sh"
