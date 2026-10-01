---
name: filament
description: Build or review optional Filament-based INTSEC administration and CRUD after verifying Filament is installed; use custom monitoring UI when specialized workflows need it.
---

# Filament in INTSEC

Filament is an optional administration and CRUD tool, not the required shell for every security workflow. Before using its APIs, verify the installed Filament version in Composer dependencies. If it is absent, do not generate package-dependent code unless the task includes installing/configuring it.

## Good fits

Use Filament Resources, Tables, Forms, Actions, Infolists, Pages, Widgets, and Notifications when their native UX efficiently supports:

- user and role administration
- system settings and detection-rule configuration
- monitored-source/reference data
- straightforward CRUD and administrative tables/forms

Specialized Blade/Livewire/Tailwind may be a better fit for security overviews, request monitoring, IP maps, incident timelines, complex triage, and investigation workspaces. Do not rebuild working specialized UI just for consistency.

## Architecture and access

- Keep detection, correlation, enforcement, persistence workflows, and metrics queries in Laravel services/actions/query objects—not Resources, Pages, or Widgets.
- Filament actions call the same authorized, audited application services as any other interface.
- Enforce access with policies, gates, middleware, and panel access checks. Hidden navigation is not authorization.
- Preserve tenant/source scoping if introduced.

## Operational UX

Use searchable, sortable, filtered, paginated tables; consistent severity/status badges; meaningful empty/error states; and confirmations for destructive or security-sensitive actions. Escape attacker-controlled values and avoid rendering unbounded metadata.

All metrics come from real persisted records. Widget/page rendering must not trigger capture, detection, enrichment, or alert creation.
