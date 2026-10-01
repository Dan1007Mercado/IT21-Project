---
name: ui-system
description: Design or refactor INTSEC's hybrid Blade, Tailwind, Livewire, and optional Filament UI shell, dashboards, tables, maps, charts, timelines, and operational components.
---

# INTSEC UI System

## Composition

Maintain a reusable global shell for branding, primary navigation, account controls, base dark theme, accessibility landmarks, and mobile navigation. Let each page choose the width, padding, grids, maps, charts, dense tables, and timeline/investigation layout its workflow needs.

Use Blade/Tailwind/Livewire for specialized monitoring and investigation experiences. Use Filament only when installed and native CRUD/admin UX is the better fit. Shared services, policies, and data models must make interface choice irrelevant to security behavior.

## Operational components

Create consistent, reusable treatments for severity, confidence/risk when used, alert status, incident lifecycle, IP ALLOW/BLOCK state, timestamps/freshness, source identity, and destructive actions. Preserve semantic labels in addition to color.

Dashboards should establish a clear security hierarchy: actionable alerts and incidents first, then trends/context. Maps, charts, and timelines supplement searchable/paginated records; they do not replace accessible tabular/detail views.

## Responsive and accessible behavior

Support desktop, tablet, and mobile without forcing every page into identical cards or max widths. Provide keyboard access, visible focus, labels, useful headings/landmarks, sufficient contrast, reduced-motion respect, and touch-friendly controls.

Constrain or wrap attacker-controlled paths, identities, user agents, and metadata. Encode them safely in HTML, attributes, scripts, chart payloads, and map popups.

## Data states

Use real database/API data. Provide explicit loading, empty, filtered-empty, stale, partial, permission-denied, and error states. Paginate large tables, preserve practical filter state, show time zones, and disclose approximate GeoIP.

Rendering is read/query work. Never trigger enrichment, detection, or hidden security-state mutations merely by opening a page.

