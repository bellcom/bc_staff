# BC Staff Demo

Udviklingsmodul til **fiktive** medarbejdere og afdelinger. Må ikke bruges til at indlæse virkelige persondata.

## Installation

```bash
cd /var/www/bc-staff
git pull --ff-only origin main
drush en bc_staff_demo -y
drush cr
```

Åbn `/admin/config/bc-staff/demo` og bekræft, at de fiktive data skal oprettes. Handlingen kan gentages, og eksisterende demo-profiler opdateres ud fra deres reserverede brugernavne.

## Indhold

- 4 afdelinger fordelt på Næstved og Roskilde
- 12 fiktive medarbejdere med status, afdeling og lokation
- Blokerede brugerkonti med tilfældige adgangskoder og `@example.invalid`-adresser
- Ét standardavatar-SVG for demo-profilerne

## Vedligehold

- Medarbejdere: `/admin/people` (filtrer på `bc_demo_`)
- Afdelinger: `/admin/structure/taxonomy/manage/bc_staff_department/overview`
- Genopret testdata: `/admin/config/bc-staff/demo`
- Oversigt: `/staff`

Bemærk: Administration i Drupal kan redigere demo-konti via brugerformularen, men programmatisk oprettede felter fremgår muligvis først på formularen efter justering af form display under brugerfelter. Modulet opretter testdata og demonstrerer oversigten; det er ikke endnu den endelige administrations-UI.

**Undlad at afinstallere modulet på en database med værdifulde data.** Drupal kan slette feltdata og tilhørende konfiguration ved afinstallation.
