# BC Staff Absence – POC

This companion module provides staff-entered vacation and absence periods.

- `/staff/fravaer`: view only your own absence history.
- `/staff/fravaer/ny`: register your own absence; vacation begins as pending.
- `/staff/fravaer/godkend`: managers review pending vacation for employees in their department. Global absence administrators can review all requests.

Recorded types: vacation, course, child sick day, sickness, day off and leave of absence. No free-text medical details are stored. The module does **not** expose detailed reasons on the shared staff overview or display API.

## Installation

```bash
drush en bc_staff_absence -y
drush cr
```

The `manage all bc staff absences` permission is sensitive and must be granted only to trusted administrator accounts. Managers use the existing `manage own bc staff department` permission.

## Scope limits

- Approved vacation does not yet automatically update the current staff presence status.
- Absence periods are registered and approved, but not yet shown in the day/week/month calendar.
- Display screens must not reveal private absence reasons.
- Before production use, complete privacy review, retention rules, and functional tests for role-scoped decisions.
