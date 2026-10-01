---
name: request-monitoring
description: Implement or review safe INTSEC HTTP request telemetry capture, RequestActivity persistence, lifecycle middleware, exclusions, privacy controls, aggregation, and request metrics.
---

# INTSEC Request Monitoring

## Boundary

`RequestActivity` is general application request telemetry. `AuthenticationLog` is only authentication telemetry. Keep both even when one request legitimately produces records in each domain.

Capture application-visible HTTP/HTTPS metadata after TLS termination; do not describe it as packet inspection.

## Lifecycle capture

Use terminating/response-aware middleware or an equivalent request-lifecycle integration to capture:

- occurrence time and resolved/normalized client IP
- method and normalized route name/path
- response status
- elapsed duration
- authenticated user when available
- safe user-agent/device classification
- monitored source/application identity where relevant
- limited classification/context needed by rules

Measure through response completion as supported by Laravel and ensure exception responses have deliberate behavior. Avoid recursive telemetry.

## Privacy and exclusions

Never store `Authorization`, cookies, CSRF/session values, passwords, tokens, or complete bodies. Do not indiscriminately persist query strings or headers. Allowlist safe fields and recursively redact sensitive key variants.

Define explicit treatment for assets, health checks, telemetry/ingestion routes, bots, internal jobs, and development tooling. Exclusion improves signal but must not create an attacker-controlled bypass.

## Reliability and volume

Decide whether failed telemetry writes are non-blocking for business traffic and make that behavior observable. Queue downstream enrichment/detection when it reduces request latency, but persist required evidence reliably first.

Index occurrence time, IP, user, status, route/path, source, and classification according to actual queries. Paginate, aggregate, and plan retention/archival. Avoid unbounded dashboard scans and duplicate records on middleware retries.

## Verification

Test status and duration capture, route normalization, authenticated/guest requests, exception paths, exclusions, redaction, spoofed forwarding headers, duplicate prevention, telemetry-write failure posture, and proof that dashboard reads do not create telemetry-derived alerts.

