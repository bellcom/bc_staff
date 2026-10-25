# BC Staff roller og test

Tre adskilte roller i Drupal:

- `bc_staff_admin` – BC Staff Administrator: alle medarbejdere via BC Staff-administrationen; **ikke** Drupal-superadministrator.
- `bc_staff_manager` – BC Staff Leder: medarbejdere i den afdeling der står på lederens konto. Kan oprette i egen afdeling og redigere egne medarbejdere. Kan ikke ændre andre ledere, administratorer eller sig selv gennem medarbejderformularen.
- `bc_staff_employee` – BC Staff Medarbejder: oversigt og ændring af egen status/lokation.

Drupal-brugerens felt `field_bc_staff_department` (indtil videre leveret af bc_staff_demo) er lederens afgrænsning. **Lederen skal have en afdeling før rollen kan bruges.** Afdelingsændringer i nye medarbejdere fastlåses server-side til lederens afdeling.

## Førstegangsopdatering på eksisterende installation

```bash
cd /var/www/bc-staff
git pull --ff-only origin main && drush updb -y && drush cr
```

Rollerne oprettes med update-hook `bc_staff_update_11003`. Ingen eksisterende brugere får rollerne automatisk.

Giv roller til testbrugere med `drush user:role:add bc_staff_admin BRUGERNAVN`, `drush user:role:add bc_staff_manager BRUGERNAVN` eller `drush user:role:add bc_staff_employee BRUGERNAVN`. Brug separate testbrugere til de tre roller.

URL'er (uden tracking):

- `/staff` medarbejderoverblik
- `/staff/medarbejdere` administration for administrator/leder
- `/staff/medarbejdere/ny` oprettelse
- `/staff/mobil` medarbejderstatus

Undlad at give leder eller medarbejder standard Drupal-administratorrollen eller tilladelser som `administer users`, `administer permissions`, `access administration pages` og `administer taxonomy`. Disse er bredere end BC Staff.

**Begrænsninger (POC):** Kun medarbejderlisten/formularen er scoped pr. afdeling. Adgangskontrol for den fælles medarbejderoversigt, afdelings-/lokationsadministration, foto-upload i samme formular og et fuldt tilpasset BC Staff-login/layout skal udvikles og gennemgås, før persondata må anvendes. De eksisterende Drupal-konti bevares.
