#!/usr/bin/env bash
# Install BC Staff Drush defaults and system-wide command.
# Run as: sudo bash /var/www/bc-staff/scripts/setup-drush.sh
set -Eeuo pipefail
trap 'echo "FEJL: Drush-opsætning stoppede ved linje $LINENO" >&2' ERR
APP=/var/www/bc-staff
DOMAIN=dev-staff01.bellcom.dk
[ "$EUID" -eq 0 ] || { echo "FEJL: Kør med sudo." >&2; exit 1; }
[ -x "$APP/vendor/bin/drush" ] || { echo "FEJL: Drush mangler i $APP/vendor/bin." >&2; exit 1; }
install -d -m 0755 /usr/local/bin
cat > /usr/local/bin/drush <<'WRAPPER'
#!/bin/sh
# BC Staff: available from any working directory.
APP=/var/www/bc-staff
if [ ! -x "$APP/vendor/bin/drush" ]; then
  echo "FEJL: Drush mangler i $APP/vendor/bin." >&2
  exit 1
fi
cd "$APP" || { echo "FEJL: Kan ikke tilgå $APP." >&2; exit 1; }
exec "$APP/vendor/bin/drush" --root="$APP/web" --uri=https://dev-staff01.bellcom.dk "$@"
WRAPPER
chmod 0755 /usr/local/bin/drush
/usr/local/bin/drush status --fields=uri,drupal-version,db-status
echo "OK: /usr/local/bin/drush anvender https://$DOMAIN"
