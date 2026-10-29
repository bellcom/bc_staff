# BC Staff Display POC

This module provides an access-controlled preview at `/staff/skaerm` and a minimal read-only JSON feed at `/staff/api/v1/status`.

It is **not** a complete OS2display connector. Integrating with a real OS2display instance requires an authenticated client design and a compatible display component.

Enable using `drush en bc_staff_display -y && drush cr`. The permission `view bc staff display` must be explicitly granted to selected authenticated roles. It is not automatically assigned. Anonymous requests should return HTTP 403.

The feed lists active non-demo staff accounts with configured status. It contains name, department, status and location, but never account IDs, login names, email addresses or credentials. Review personal data disclosure before placing a screen in a public area. Both routes require the display permission; JSON is marked private/no-store.

### Acceptance

- Anonymous users cannot view either endpoint.
- A user with the explicit permission can view the screen and the JSON feed.
- Updating a staff status updates the feed on the next request.
- Existing manager department-access restrictions are unaffected.
