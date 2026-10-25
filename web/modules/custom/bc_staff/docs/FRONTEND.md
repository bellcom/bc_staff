# BC Staff frontend theme

The theme negotiator selects the dedicated `bc_staff_frontend` theme on `bc_staff.*` routes only, provided that it has been enabled. Other site routes continue to use their usual configured theme. The front-end theme uses Olivero as its base for consistent Drupal form support.

## Activation on an existing development site

From the project root:

```bash
drush theme:enable bc_staff_frontend -y
drush cr
```

The theme includes a default `system_main_block` placement for the content region. Verify after enabling:

```bash
drush php:eval '$block = \Drupal::entityTypeManager()->getStorage("block")->load("bc_staff_frontend_content"); echo $block ? "Main block OK\n" : "Main block missing\n";'
```

If the block is missing, add it through the Drupal block layout UI for BC Staff Frontend, or execute:

```bash
drush php:eval '$storage = \Drupal::entityTypeManager()->getStorage("block"); if (!$storage->load("bc_staff_frontend_content")) { $storage->create(["id" => "bc_staff_frontend_content", "theme" => "bc_staff_frontend", "region" => "content", "plugin" => "system_main_block", "weight" => 0, "settings" => ["id" => "system_main_block", "label" => "Main page content", "label_display" => "0"]])->save(); }'
drush cr
```

## Manual verification

- `/staff`, `/staff/mobil`, `/staff/login`, `/staff/medarbejdere` render using the BC Staff interface.
- `/admin` retains the site's admin theme.
- All navigation links are generated via Drupal route access checks.
- Verify route restrictions as administrator, manager, and employee.
- Ensure the status form and employee edit forms can be submitted with CSRF protection intact.
- The theme does not alter roles, permissions, or employee data.

## Uninstall the theme

```bash
drush theme:uninstall bc_staff_frontend -y
drush cr
```

Existing staff routes fall back to the site's normal theme.
