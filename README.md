# BC Staff

BC Staff er en Drupal 11-baseret proof of concept (POC) til medarbejderoverblik, tilstedeværelse, lokationer og teams, udviklet af Bellcom.

## Status

Projektet er under udvikling og er ikke klar til produktionsbrug eller håndtering af virkelige personaleoplysninger.

## Teknologi

- Drupal 11, PHP 8.3+, Composer, Drush 13
- Drupal Core (Users, Views, Taxonomy, Media, Field API)
- Admin Toolbar, Group og ECA
- Devel som udviklingsafhængighed

## Installation (udvikling)

Kræver PHP, Composer, en Drupal-kompatibel database og en webserver med document root `web/`.

```bash
composer validate --no-check-publish
composer update --with-all-dependencies
composer check-platform-reqs
```

`composer.lock` skal genereres og committes efter en vellykket dependency resolution; brug derefter `composer install` til reproducerbare installationer. Drupal-siteinstallationen, databaseoplysninger og webserverkonfiguration kommer efterfølgende.

## Planlagt funktionalitet

- Medarbejderstatus og lokationsvalg
- Oversigt grupperet efter afdeling og team
- Rollebaseret adgang
- Touchbetjening med sikker identifikation
- Senere: kalender, fraværsgodkendelse og mulig OS2display-visning

Hovedmodulet navngives `bc_staff`, og det tilhørende tema `bc_staff_theme`. Undgå at lægge secrets og reelle personoplysninger i Git. Foto alene er ikke autentifikation; fraværsårsager skal holdes adskilt fra fællesvisningen.

## Git

Udvikl på feature branches og brug pull requests til `main`. `vendor/`, Drupal Core og contrib-moduler hentes via Composer og committes ikke.

## Licens

Se LICENSE (GPL-3.0). Licenskompatibilitet og eventuelle tredjepartsrettigheder afklares før distribution.
