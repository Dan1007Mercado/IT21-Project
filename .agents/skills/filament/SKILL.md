# Filament Skill

## Purpose

Use Filament as the administrator and security-operations interface, not as the home of core business logic.

## Required patterns

- Prefer native Filament resources, tables, forms, pages, widgets, actions, filters, and infolists.
- Keep complex logic in Laravel services/actions instead of in resource classes.
- Use Filament notifications, confirmation dialogs, and badges to improve operational workflows.
- Organize navigation around Dashboard, Monitoring, Incident Response, Administration, and Settings.

## Access rules

- Enforce authorization in Laravel policies and gates.
- Do not rely on hiding menu items as a security control.
- Restrict admin functionality to authorized roles and users server-side.

## UI expectations

- Emphasize security-monitoring workflows.
- Use readable tables, search, sorting, empty states, badges, and filters.
- Make actions clear without introducing custom UI patterns where native Filament components are adequate.

## Scope guard

Filament should support operations and visibility, but it must not become the sole implementation location for business or security logic.
# Filament Development Skill

Filament is the INTSEC administrator/security operations interface.

## Use Resources for

* Users
* Roles
* Authentication Logs
* Security Events
* Incidents
* Blocked IPs

Use custom Pages when a workflow is not naturally CRUD.

## Use Widgets for

* security statistics
* event statistics
* authentication trends
* severity distributions
* recent activity

## Tables

Tables should provide useful:

* search
* filtering
* sorting
* pagination
* status/severity presentation

Security Events should support investigation-friendly filtering.

Useful filters may include:

* severity
* status
* event type
* IP address
* user
* date/time

## Forms

Forms must validate input.

Do not trust client-side validation alone.

Sensitive actions require server-side authorization.

## Actions

Actions may be used for:

* investigate
* update incident status
* add investigation remark
* resolve incident
* block IP
* unblock IP

Filament actions should call appropriate Laravel business logic rather than containing complex security logic themselves.

## Authorization

Never rely solely on hiding navigation items.

Authorization must be enforced server-side.

Administrators may access security-management functions.

Standard users must not access administrator functionality.

## Dashboard

Dashboard statistics must come from real database queries.

Do not hardcode counts.

The Filament interface should present INTSEC as a security-monitoring application, not a generic admin CRUD system.
