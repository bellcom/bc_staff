# BC Staff development rules

These requirements apply to all human and AI contributions in this repository.

## Required standards

- Follow the current official Drupal Coding Standards for PHP, JavaScript, CSS, Twig, YAML and documentation: https://project.pages.drupalcode.org/coding_standards/
- Target Drupal 11 and supported PHP versions. Use Drupal APIs, dependency injection where applicable, and supported extension points.
- All source-code identifiers, comments, docblocks, configuration labels, original interface strings, documentation and commit messages must be written in **English (US)**.
- Wrap user-visible strings in Drupal translation APIs (`$this->t()`, `t()` where appropriate, or Twig `|t`). Danish belongs in locale translations, not in English source strings.
- Separate Drupal business logic (modules), presentation (themes and templates), and demo data (demo module).
- Never substitute menu visibility for access control. Enforce access on routes and entity operations; explicitly test privilege escalation and cross-department access.
- Never expose real personnel records, health/absence reasons, credentials or tokens in Git. Preserve existing configuration and data.
- Preserve BC Staff module naming with `bc_` prefixes. Do not rename paths/routes or alter deployed state without a migration and rollback plan.

## Git workflow

1. Start a feature or fix branch from `main`.
2. Inspect surrounding code and existing patterns before editing.
3. Make minimal, reviewable changes with appropriate tests.
4. Run the repository quality checks. Never claim tests passed without executing them.
5. Open a pull request; do **not** commit to `main` directly.
6. Merge only after required checks pass and another authorized reviewer approves.
7. Deploy with versioned Composer dependencies and tested Drupal updates; back up the database before structural changes.

## Quality gate

Use `composer validate --no-check-publish`, PHP syntax checks, Drupal/DrupalPractice PHPCS, and applicable test suites. The CI workflow also checks source-language rules. Fix existing violations in separate PRs rather than weakening gates or ignoring entire files.

## Project-specific UX

- BC Staff is the user-facing interface for staff, managers and staff administrators. Ordinary users must not need Drupal administrative navigation.
- Use English original strings and provide translations separately. Design and security policies are independent of locale.
- All three roles must have correctly enforced least-privilege access.

## Deployment and secrets

- Never commit `settings.php`, `.env`, database backups, uploads or private keys.
- Test database update hooks, access control and form validation on a non-production installation.
- GitHub Actions success verifies only the tests it runs, not a complete functional Drupal deployment.
