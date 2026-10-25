#!/usr/bin/env bash
# BC Staff - update code, Composer dependencies and Drupal database.
# Run as a script with sudo. Does not modify Nginx, certificates or OS packages.
set -Eeuo pipefail
trap 'echo "FEJL: Opdatering stoppede ved linje $LINENO." >&2' ERR
APP=/var/www/bc-staff
DOMAIN=dev-staff01.bellcom.dk
[ "$EUID" -eq 0 ] || { echo "FEJL: Brug sudo bash." >&2; exit 1; }
[ -d "$APP/.git" ] && [ -x "$APP/vendor/bin/drush" ] || { echo "FEJL: BC Staff er ikke installeret." >&2; exit 1; }
exec 9>/run/lock/bc-staff-update.lock
flock -n 9 || { echo "FEJL: Opdatering kører allerede." >&2; exit 1; }
cd "$APP"
[ "$(git branch --show-current)" = main ] || { echo "FEJL: Git er ikke på main." >&2; exit 1; }
[ -z "$(git status --porcelain --untracked-files=no)" ] || { echo "FEJL: Lokale ændringer i versionsstyrede filer." >&2; exit 1; }
git fetch origin main
OLD=$(git rev-parse HEAD)
NEW=$(git rev-parse origin/main)
git merge-base --is-ancestor "$OLD" "$NEW" || { echo "FEJL: Kan ikke fast-forwarde til origin/main." >&2; exit 1; }

BACKUP="/var/backups/bc-staff/$(date -u +%Y%m%dT%H%M%SZ)"
install -d -m 0700 "$BACKUP"
mariadb-dump --single-transaction --quick --databases bc_staff | gzip -c > "$BACKUP/database.sql.gz"
[ -s "$BACKUP/database.sql.gz" ] || { echo "FEJL: Databasebackup er tom." >&2; exit 1; }
echo "$OLD" > "$BACKUP/git-commit.txt"
echo "Backup: $BACKUP"

DRUSH="$APP/vendor/bin/drush"
MAINTENANCE=0
cleanup() {
  if [ "$MAINTENANCE" = 1 ]; then
    runuser -u bcstaff -- "$DRUSH" --root="$APP/web" --uri="https://$DOMAIN" state:set system.maintenance_mode 0 -y || true
    runuser -u bcstaff -- "$DRUSH" --root="$APP/web" --uri="https://$DOMAIN" cache:rebuild || true
  fi
}
trap cleanup EXIT
runuser -u bcstaff -- "$DRUSH" --root="$APP/web" --uri="https://$DOMAIN" state:set system.maintenance_mode 1 -y
MAINTENANCE=1
git merge --ff-only origin/main

if [ -f composer.lock ]; then
  runuser -u bcstaff -- composer install --no-interaction --prefer-dist --no-progress
else
  echo "BEMÆRK: composer.lock mangler. Composer-opløsning er ikke reproducerbar."
  runuser -u bcstaff -- composer update --with-all-dependencies --no-interaction --prefer-dist --no-progress
fi
runuser -u bcstaff -- composer check-platform-reqs
runuser -u bcstaff -- "$DRUSH" --root="$APP/web" --uri="https://$DOMAIN" updatedb -y
runuser -u bcstaff -- "$DRUSH" --root="$APP/web" --uri="https://$DOMAIN" cache:rebuild
cleanup
MAINTENANCE=0
trap - EXIT
echo "OK: Opdateret fra $OLD til $(git rev-parse HEAD)."
echo "BEMÆRK: Drupal config-import køres ikke automatisk. Nginx/SSL er uændret."
