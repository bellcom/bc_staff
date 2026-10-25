# BC Staff kernemodul – 0.2.0 (udvikling)

## Funktioner
- `/staff`: responsivt kortbaseret medarbejderoverblik med foto-placeholder, statusfarver og afdelingsfilter.
- Automatisk sideopdatering hvert 30. sekund, kun når fanen er synlig.
- `/staff/min-status`: ændring af egen status og lokation efter Drupal-login.
- `/staff/mobil`: mobilvenlig indgang til samme sikre formular.
- Ingen anonym adgang eller mulighed for at redigere andres status gennem formularen.

## Installation/opdatering
```bash
cd /var/www/bc-staff
git pull --ff-only origin main
drush updb -y
drush cr
```

Tildel rettigheder under `/admin/people/permissions`. Demo-data forudsætter modulet `bc_staff_demo`.

## Status
Dette er en POC. Den mobile side er endnu ikke en installérbar PWA med manifest, service worker eller push-notifikationer. Delte touchskærme kræver sikker identifikation; tryk på foto giver ikke statusændringsadgang. Standardavatar er et lokalt SVG-asset. Overblikket skal ikke bruges til reelle personaledata før databeskyttelse og områdespecifik adgangskontrol er implementeret.
