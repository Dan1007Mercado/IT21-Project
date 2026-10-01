---
name: testing
description: Plan, write, or review INTSEC automated tests for authentication, authorization, telemetry, detection, alerts, incidents, IP behavior, external connectors, dashboards, and regressions.
---

# INTSEC Testing

Use the installed Laravel/PHPUnit stack and existing test conventions. Prefer observable behavior over assertions that a class, route, migration, or string merely exists.

## Required coverage by affected area

- Authentication: attempt/success/failure/logout distinction, session regeneration/invalidation, rate limiting, and no duplicate login success from an existing session.
- Authorization: administrator and standard-user paths, policies/middleware, direct endpoint access, and sensitive mutations.
- Telemetry: response status/duration, request exclusions, redaction, safe failure posture, and separation of `AuthenticationLog` from `RequestActivity`.
- Detection: threshold/window boundaries, grouping, exclusions, reasons/severity, idempotent re-evaluation, deduplication, and correlation.
- Incidents: creation/update, assignment, remarks, status history, resolution, linked evidence, and audit records.
- IP: IPv4/IPv6 normalization, public/private/reserved/loopback/invalid classification, trusted/untrusted proxy behavior, ALLOW/BLOCK precedence, CIDR matching, expiration, and self-lockout protections.
- External APIs: authentication, schema validation, source scoping, duplicate/replay behavior, rate limits, severity normalization, and blocklist contract.
- Dashboards/UI queries: authorization, database-backed metrics, pagination/filter correctness, escaped hostile strings, and proof that reads do not create events/alerts/enrichment.
- Real-time delivery: persistence before broadcast, authorization of channels, retry behavior, and recovery from the database.

## Test design

Use factories for real domain tables and freeze/control time for windows and expiration. Test one below, at, and above each important boundary. Include hostile and malformed inputs without embedding real secrets.

Use fakes for queues, events, notifications, HTTP providers, and broadcasters when asserting orchestration; retain integration tests for critical database and middleware behavior. Test failure paths and idempotency, not only happy paths.

Run targeted tests during implementation, then the broader relevant suite. Run clean migrations when schema changes. Report unrelated pre-existing failures separately and never claim completion with known task-caused failures.

