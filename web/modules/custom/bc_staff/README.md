# BC Staff – 0.3.0 (POC)

## Funktioner

- `/staff`: medarbejderkort, statusfarver, afdelingsfilter og opdatering hvert 30. sekund.
- `/staff/mobil`: store touchvenlige statusknapper og egen lokation efter Drupal-login.
- `/staff/min-status`: samme formular som mobilvisningen.
- `/admin/structure/taxonomy/manage/bc_staff_location/overview`: administratorens liste over lokationer.
- Manifest og SVG-ikon til mobilens hjemmeskærm (PWA-grundlag). Ingen offline-cache eller push.

## Opgradering fra 0.2.0

```bash
cd /var/www/bc-staff
git pull --ff-only origin main
drush updb -y
drush cr
```

Kør ikke `drush en bc_staff` igen på en eksisterende installation. `bc_staff_update_11001` opretter den nye lokationsordliste. Åbn derefter `/admin/config/bc-staff/demo`, og bekræft for at oprette eksempel-lokationer.

## Sikkerhed

Statusformularen ændrer kun den aktuelle Drupal-bruger. Delte touchskærme må ikke tillade statusændring alene via foto; sikker identifikation kræves. Demo-konti kan ikke logge ind. Brug fiktive data under udvikling.

Det er endnu ikke en fuld, offline-understøttet PWA eller en app udgivet i appbutikker.
