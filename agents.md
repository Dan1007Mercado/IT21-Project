# INTSEC — Master Development Instructions

## Purpose

INTSEC is an Integrated Intrusion Monitoring and Incident Response System. It detects, monitors, alerts on, investigates, and responds to suspicious activity in monitored web applications.

INTSEC is application-level. It may observe HTTP/HTTPS metadata available after TLS termination; it does not inspect raw packets and is not a network IDS or firewall.

## Instruction hierarchy

Use this precedence:

1. Current user task and approved requirements
2. This file
3. `.agents/rules/*`
4. Applicable `.agents/skills/*`
5. Existing implementation conventions

Inspect installed dependencies and working code before choosing APIs. Do not silently follow stale proposals. Read this file, relevant rules, and only the skills applicable to the task.

## Stack

Use versions actually installed in `composer.lock` and `package-lock.json`. The current baseline is PHP 8.2+, Laravel 12, MySQL, Blade, Tailwind CSS 4, and Vite.

Filament, Livewire, Reverb/Echo, Leaflet, and GeoIP providers are valid when needed, but verify installation first. Do not code against a package merely because a proposal mentions it. Do not downgrade Laravel or introduce competing frameworks without demonstrated need.

## Current direction

Approved application-level capabilities include:

- authentication, sessions, accounts, RBAC, and settings
- distinct authentication and HTTP request telemetry
- authenticated external security-event ingestion
- deterministic rule-based detection, including spikes, repeated IPs, brute force, password spraying, probing, and decoy endpoints
- security events, actionable alerts, correlation, incidents, remarks, and status history
- centralized IP resolution/classification, public-IP intelligence, approximate GeoIP, and mapping
- application-level IP allow/block distribution and enforcement
- audit logging and administrator accountability
- real-time-capable delivery, including Reverb/Echo where practical
- hybrid Blade/Tailwind/Livewire and optional Filament interfaces
- realistic factories/seeders and automated security tests

This is direction, not evidence that each capability already exists. Inspect and implement requested behavior end to end.

## Domain language

- `AuthenticationLog`: authentication-specific telemetry—attempts, successes, failures, and logout.
- `RequestActivity`: general HTTP/HTTPS request telemetry—route/path, method, response status, duration, and resolved client IP.
- External security event: telemetry submitted by an authenticated monitored application or sensor.
- `SecurityEvent`: persisted, interpreted security-relevant activity.
- `SecurityAlert`: actionable detection requiring administrator attention, with traceable reason and workflow state.
- `Incident`: investigation/response case correlating relevant evidence.
- `BlockedIp`: application-level ALLOW/BLOCK policy with lifecycle and provenance.
- `AuditLog`: accountability record for significant administrative/security operations.

Other relevant concepts include `User`, authorization, `IncidentRemark`, `IncidentStatusHistory`, `SystemSetting`, detection rules, IP enrichment, and monitored source identity.

Telemetry is observation. An event is interpreted activity. An alert is an actionable detection. An incident is a managed case. Do not collapse these layers or use `AuthenticationLog` as a proxy for request volume. Never generate fake records solely to populate dashboards.

## Architecture invariants

- Laravel owns domain, application, and security logic; controllers and presentation remain thin.
- Capture, enrichment, detection, alerting, and correlation never depend on opening a dashboard.
- Use middleware for request-lifecycle capture/enforcement where appropriate.
- Use services/actions/domain classes/jobs for non-trivial processing and events/listeners for decoupled delivery.
- Enforce authorization server-side with policies, gates, and middleware.
- Persisted database records are the source of truth; dashboards are primarily read/query operations.
- Centralize client-IP resolution, normalization, and classification; configure trusted proxies deliberately.
- Use queues where they materially improve reliability, with explicit retries and failure behavior.
- Keep detection deterministic, explainable, and traceable.
- Monitoring failure should not unnecessarily take monitored business applications offline.

Preferred flow:

`Application request / authentication / external event → capture → normalize → persist telemetry → enrich → detect → classify → alert → correlate → incident → response → audit`

The stages need not each be a class, but responsibilities must remain clear.

## Security invariants

- Never persist passwords, session secrets, CSRF tokens, access tokens, `Authorization` headers, or raw sensitive credentials.
- Do not indiscriminately persist headers, cookies, query strings, or bodies. Allowlist useful metadata and redact sensitive fields.
- Regenerate sessions after login and invalidate them on logout. An authenticated request is not a login success.
- Authenticate and validate external connectors, deduplicate external IDs, rate-limit appropriately, and mitigate replay where practical.
- Client-provided severity is evidence, not automatically authoritative classification.
- Distinguish public, private, loopback/reserved, and invalid IPs. Do not GeoIP non-public addresses or blindly trust forwarding headers.
- Enforce IP policy server-side with deterministic precedence, expiration, and practical self-lockout protection.
- Escape attacker-controlled UI values and audit security-sensitive changes.
- Handle telemetry/logging failures deliberately without needless business-workflow outages.

Read `.agents/rules/security.md` and applicable specialized skills before security-sensitive work.

## UI philosophy

Use Blade/Tailwind/Livewire for specialized dashboards, maps, timelines, dense monitoring, and investigations when they offer better UX. Use Filament, when installed, for conventional administration and CRUD where its native components fit.

Neither UI may own detection or business logic. Preserve working specialized screens. The shell owns shared navigation, branding, account controls, theme, and mobile navigation; pages own their useful widths, grids, maps, charts, and investigation layouts.

Operational UI must be responsive, accessible, database-driven, paginated where needed, and explicit about loading, empty, stale, and error states. Use consistent severity/status semantics and confirm destructive or security-sensitive actions.

## Data and migrations

- Use migrations for schema changes.
- Use foreign keys, constraints, and indexes suited to monitoring queries.
- Keep queryable core fields in typed columns; reserve JSON for supplementary metadata.
- Avoid duplicate and dashboard-specific storage.
- Update models, relationships, factories/seeders, queries, and tests with schema changes.
- Ensure migrations work from a clean database.

## Testing and completion

Test changed behavior, especially sessions/authentication, RBAC, telemetry privacy/capture, deterministic detection, alert deduplication, incident history, IP classification/enforcement, external ingestion, auditing, and dashboard non-mutation.

Run targeted tests, then the broader relevant suite. A feature is not complete because a class, route, view, or migration exists. It must be wired into the real workflow, authorized, persisted where required, and behaviorally tested.

## Workflow

1. Read this file, applicable rules, and relevant skills.
2. Inspect dependencies and existing implementation.
3. Identify invariants that must not regress.
4. Make incremental Laravel-conventional changes.
5. Add migrations/model updates and automated tests.
6. Run targeted and broader relevant tests.
7. Report verified behavior and remaining limitations accurately.

## Explicit exclusions

Unless newly approved, INTSEC is not:

- a packet sniffer, PCAP analyzer, or full network IDS
- a kernel, host, or network firewall
- a full enterprise SIEM or commercial SOC
- an autonomous AI/ML IDS
- a malware-analysis sandbox
- a full standalone honeypot
- an offensive-security platform
