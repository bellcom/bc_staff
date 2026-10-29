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

## Expanded absence test scenarios

If `bc_staff_absence` is enabled, refreshing the demo data also creates eight **active, fictional** test accounts:

- `bc_demo_manager_naestved` – manager in Næstved Administration
- `bc_demo_manager_roskilde` – manager in Roskilde Administration
- `bc_demo_employee_naestved_1`, `_2`, `_3` – employees in Næstved
- `bc_demo_employee_roskilde_1`, `_2`, `_3` – employees in Roskilde

The active users have strong randomly generated passwords; the generator **does not publish credentials**. On the development server, an administrator may set a password on a selected demo account for interactive tests. Avoid reusing production passwords.

Scenarios include: pending, approved, rejected vacation; sickness; child's sick day; course; day off; and leave of absence. Dates are generated within the current month. Refreshing during the same month will not duplicate these records and **will not reset an approval decision**. Existing non-demo accounts are never modified. The original twelve blocked demo accounts remain blocked.

Test a vacation approval as a Næstved manager, verify that a Roskilde request cannot be reviewed by that manager, and test self-service history as an employee. Status data and absence reasons are intentionally separated.

**Development-only.** Keep `bc_staff_demo` disabled on production systems.
