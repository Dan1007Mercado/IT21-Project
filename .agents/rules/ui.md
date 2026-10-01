# INTSEC UI Rules

## Hybrid presentation

Choose technology for the workflow:

- Use Blade/Tailwind/Livewire for specialized dashboards, maps, charts, timelines, dense request views, triage, and investigation workspaces when they provide better UX.
- Use Filament, when installed, for conventional administration, configuration, reference data, and CRUD where its native components fit.
- Do not rebuild working specialized Blade screens in Filament merely for consistency or force every page into one rigid shell.
- Keep business, detection, authorization, and persistence logic outside both Blade and Filament.

## Layout ownership

The global shell owns shared navigation, branding, account controls, base theme, accessibility landmarks, and mobile navigation.

Individual pages own useful width, padding, grids, maps, charts, dense tables, timelines, and investigation layouts. Do not force every view into the same max-width/card arrangement.

## Operational UX

- Support desktop, tablet, and mobile with keyboard navigation, visible focus, labels, and sufficient contrast.
- Use a consistent dark INTSEC identity and consistent severity, alert-state, incident-status, and IP-policy components.
- Make timestamps/time zones, approximate GeoIP, freshness, and destructive effects clear.
- Provide filters, search, sorting, date ranges, and pagination for operational datasets.
- Provide meaningful loading, empty, stale, and error states; never substitute invented data.
- Confirm destructive/security-sensitive actions and show useful success/failure feedback.
- Escape attacker-controlled strings and constrain long paths, user agents, identities, and metadata.

Metrics, charts, tables, and maps use real persisted data or clearly identified map tiles. Never hardcode counts or fake coordinates. Page rendering must not trigger detection, enrichment, or hidden security-state mutation.
