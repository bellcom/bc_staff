# BC Staff – first time install

Mål: Debian 13 på dev-staff01.bellcom.dk (2.31.35.108).

## Før installation

- Sæt DNS A-record korrekt og åbn TCP 80/443.
- Kontrollér Debian-version med `cat /etc/os-release`.
- GitHub: https://github.com/bellcom/bc_staff
- Alle scriptfiler skal gennemgås før udførelse på en server med sudo.

## Førstegangs-bootstrap

```bash
curl -fL --proto '=https' --tlsv1.2 -o /tmp/bc-staff-install.sh https://raw.githubusercontent.com/bellcom/bc_staff/main/scripts/install.sh
bash -n /tmp/bc-staff-install.sh && sudo bash /tmp/bc-staff-install.sh
```

Dette GitHub-script installerer OS-pakker og Composer-projektet. Det er endnu ikke en fuld unattended site-installation. Drupal DB, site-install og HTTPS er særskilte trin. Kør ikke update-scriptet før site-installationen er fuldført.

## Når Drupal er installeret

```bash
sudo nginx -t
sudo certbot certificates
cd /var/www/bc-staff
sudo -u bcstaff vendor/bin/drush --root=/var/www/bc-staff/web status
```

## Opdateringer

```bash
sudo bash /var/www/bc-staff/scripts/update.sh
```

Opdateringsscriptet tager databasebackup, henter main, opdaterer Composer og kører Drush updatedb/cache:rebuild. Det kræver eksisterende Drupal site-installation og ændrer ikke Nginx eller SSL.

## Vigtigt

Projektet mangler aktuelt en committed `composer.lock`. Første installation bruger Composer update til at løse afhængigheder; det er ikke reproducerbart indtil lock-filen er genereret, testet og committed. Brug ikke virkelige medarbejderdata på dette tidspunkt.
