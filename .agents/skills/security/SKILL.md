---
name: security
description: Implement or review security-sensitive INTSEC behavior such as authentication, telemetry privacy, external APIs, detection, authorization, auditing, incident history, or IP enforcement.
---

# INTSEC Security Development

## Trust boundaries

Authorize server-side, validate untrusted input, protect mass assignment, encode output, and never trust roles, ownership, severity, source IP, or workflow state from the browser or connector. UI visibility is not authorization.

## Authentication

Use Laravel authentication and hashing. Regenerate sessions after login; invalidate sessions and regenerate the CSRF token on logout. Distinguish attempt, success, failure, authenticated request, and logout. Rate-limit attempts and reduce account enumeration where practical.

Never log passwords, password fields, session IDs/secrets, `Authorization` headers, cookies, CSRF values, tokens, OTPs, or recovery secrets.

## Safe telemetry

Allowlist useful request/authentication metadata. Do not indiscriminately store bodies, headers, cookies, or query strings. Redact sensitive keys and exclude noisy/recursive endpoints. Limit telemetry access and define retention.

Resolve client IPs centrally using deliberate trusted-proxy configuration. Normalize IPv4/IPv6 and classify public, private/reserved, loopback, and invalid values. Never blindly trust forwarding headers or GeoIP non-public addresses.

## External connectors

Authenticate source identity, validate a versioned schema, scope unique external IDs by source, rate-limit, and mitigate replay when practical. Keep secrets out of responses/logs. Normalize evidence before classification; connector-supplied severity is not automatically authoritative.

## Detection and response

Rules are deterministic, explainable, tested, and traceable to evidence, thresholds, windows, and grouping keys. Avoid escalating weak single signals and deduplicate alert floods. Preserve immutable investigation remarks/status history and authorize every response transition.

Enforce application-level IP ALLOW/BLOCK policy in server-side request handling with deterministic precedence, expiration, and practical self-lockout protection. Audit all material rule and incident changes.

## Failure posture

Choose fail-open/fail-closed behavior per boundary. Optional telemetry, enrichment, or remote blocklist refresh should not unnecessarily disable a monitored business application. Required atomic security changes must roll back and report failure rather than leave misleading partial success. Make retrying operations idempotent.

## Verification

Test unauthorized and authorized paths, session semantics, redaction, spoofed proxy inputs, external replay/duplicate handling, rule boundaries, alert deduplication, history preservation, block precedence/expiration, audit creation, and deliberate failure behavior.
