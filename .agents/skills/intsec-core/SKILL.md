---
name: intsec-core
description: Implement or review INTSEC domain workflows spanning telemetry, security events, alerts, incidents, IP response, dashboards, or monitored-application integration.
---

# INTSEC Core

## Purpose and boundaries

INTSEC detects, monitors, alerts on, investigates, and responds to application-level suspicious activity. It observes application-visible HTTP/HTTPS metadata after TLS termination; it does not sniff packets, analyze PCAPs, enforce a network firewall, or act as a full SIEM, autonomous AI/ML IDS, malware sandbox, standalone honeypot, or offensive platform.

Inspect current code before assuming a capability exists. Use the installed Laravel version and packages.

## Domain terminology

Keep these layers distinct:

- Telemetry is an observation: `AuthenticationLog`, `RequestActivity`, or an authenticated external submission.
- `SecurityEvent` is persisted, interpreted security-relevant activity.
- `SecurityAlert` is an actionable rule result requiring attention.
- `Incident` is a human-managed investigation/response case.
- `BlockedIp` is application-level enforcement policy.
- `AuditLog` records administrative accountability.

Supporting concepts include users/authorization, remarks, status history, settings, rule configuration, IP enrichment, and monitored source identity. Authentication telemetry never substitutes for general request telemetry.

## Core pipeline

Use clear responsibilities:

`request / authentication / external event → capture → normalize → persist telemetry → enrich → detect → classify → alert → correlate → incident → response → audit`

Capture/detection must not depend on dashboard access. Keep controllers and UI thin; place processing in middleware, services/actions, jobs, and events/listeners as appropriate. Persisted data is authoritative.

## Authentication monitoring

Record attempts, outcomes, and logout without secrets. Preserve correct session regeneration/invalidation, rate limiting, and distinction between login success and ordinary authenticated requests. Use authentication-specific rules for brute force, password spraying, distributed account attempts, and failed-then-success patterns.

## Request monitoring

Use `RequestActivity` or an equivalent dedicated model for safe method, route/path, response status, duration, resolved IP, actor, user-agent classification, and timestamps. Redact sensitive data, define exclusions, and design for volume with indexing, retention, aggregation, and asynchronous processing where useful.

## External ingestion

Authenticate sources, validate/version schemas, deduplicate IDs per source, rate-limit appropriately, mitigate replay where practical, and normalize before creating authoritative events/alerts. Treat submitted severity as evidence rather than unquestioned classification.

## Detection and alerts

Rules must be deterministic and explainable through evidence, grouping key, window, threshold, and reason. Detect relevant authentication and request patterns without converting every anomaly into a Critical alert. Deduplicate/aggregate floods and preserve traceability. An alert does not automatically require an incident; correlation and administrator workflow decide that.

## Incident response

Incidents preserve status history, remarks, assignments, response actions, resolution data, and linked evidence. Authorize and audit transitions. Do not overwrite investigation history.

## IP intelligence and enforcement

Centralize trusted-proxy-aware IP resolution, normalization, and classification. GeoIP only public addresses, cache/enrich outside rendering, and label location as approximate. Enforce deterministic application-level ALLOW/BLOCK rules in middleware, including expiration and practical self-lockout protections. Distribution APIs do not equal upstream firewall enforcement.

## Real-time delivery

Persist before broadcasting. Laravel events/listeners/queues and Reverb/Echo may deliver alerts when installed, but clients must recover state from the database after disconnects. Real-time failure must not lose authoritative records.

## UI responsibilities

Use Blade/Tailwind/Livewire for specialized monitoring and Filament, when installed, for fitting CRUD/admin work. Neither may contain detection or core domain logic. Use real queries, pagination, safe encoding, consistent severity/status semantics, and clear empty/error/freshness states.

## Testing expectations

Test authentication/session semantics, authorization, telemetry redaction/capture, deterministic rules, deduplication, correlation, incident history, IP classification/enforcement, external contracts, audit behavior, failure modes, and dashboard non-mutation. A generated class or route is not completion; verify wired behavior.
