# INTSEC Security Rules

## Core enforcement

- Authorize security-sensitive behavior server-side with middleware, policies, gates, or equivalent Laravel mechanisms.
- Validate untrusted input and protect against mass assignment.
- Never trust client-provided roles, ownership, severity, IP identity, or workflow state without server checks.
- Do not expose secrets or implement a security boundary only in JavaScript/navigation visibility.
- Safely encode attacker-controlled metadata. Never inject raw request data into Blade or scripts.

## Authentication and sessions

- Use Laravel authentication and password hashing.
- Regenerate the session after login; invalidate it and regenerate the CSRF token on logout.
- Distinguish login attempt, success, failure, authenticated request, and logout. An existing session must not create another login-success record.
- Rate-limit authentication and avoid account-enumeration differences where reasonable.
- Never persist raw passwords, password fields, session identifiers/secrets, auth tokens, OTP values, or recovery secrets.
- Log useful context without treating authentication telemetry as general request telemetry.

## Request telemetry and privacy

- Capture metadata with a defined monitoring use: resolved IP, method, normalized route/path, response status, duration, user/user-agent classification, timestamps, and limited justified context.
- Never store `Authorization` headers, cookies, CSRF tokens, session cookies, passwords, or complete request bodies.
- Do not indiscriminately persist headers or query strings. Allowlist fields and redact password, token, secret, authorization, cookie, API-key, and credential variants.
- Exclude health checks, static assets, telemetry endpoints, or other noise where capture adds no value or causes recursion.
- Treat retention and telemetry access as security/privacy concerns.

## IP handling

- Resolve, normalize, and classify client IPs through one shared component.
- Configure trusted proxies deliberately; never blindly trust `X-Forwarded-For`.
- Support IPv4/IPv6 and classify loopback, private/reserved, public, and invalid input.
- Do not GeoIP non-public or invalid addresses.
- Treat GeoIP as approximate enrichment, never proof of a person's location.

## External security APIs

- Authenticate connector requests without exposing ingestion secrets.
- Validate source/sensor identity and a versioned event schema.
- Deduplicate external IDs within the proper source scope.
- Rate-limit and reduce replay risk with timestamps, signatures/nonces, or equivalent controls where supported.
- Treat client severity/descriptions as untrusted evidence; server policy determines authoritative classification.
- Return stable, minimal errors that do not leak secrets or internals.

## Detection, alerts, and incidents

- Detection is deterministic and explainable through rule ID, evidence, time window, threshold, grouping key, and result.
- Use context and thresholds; a single weak signal must not become a Critical alert or incident.
- Deduplicate or aggregate repeated detections without erasing useful evidence.
- Preserve traceability from telemetry/evidence to event, alert, and incident.
- Persist remarks and status histories; never overwrite investigation history.
- Authorize status, assignment, severity, response, and resolution changes.

## IP enforcement

- Enforce application-level rules in Laravel middleware or equivalent server-side handling.
- Define ALLOW/BLOCK precedence, address/network specificity, enabled state, expiration, and invalid-rule behavior.
- Audit rule creation, edits, toggles, expiration changes, and deletion.
- Avoid accidental administrator self-lockout where practical without undocumented bypasses.
- Authenticate block distribution and define stale/unavailable behavior.

## Logging and failures

- Audit significant operations with actor, action, target, timestamp, and safe context.
- Never place secrets in application, security, audit, exception, or debug logs.
- Handle telemetry/audit failures deliberately; do not claim success when a required atomic operation failed.
- Monitoring failures should not unnecessarily crash business workflows. Choose fail-open or fail-closed per boundary and test that choice.
