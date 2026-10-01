---
name: detection
description: Design, implement, or test deterministic INTSEC detection rules, alert creation, deduplication, correlation, severity, confidence, and rule evaluation jobs.
---

# INTSEC Detection

## Rule contract

Every rule must define its evidence source, grouping key, time basis/window, threshold or sequence, exclusions, stable rule identifier/version, output reason, severity mapping, and deduplication/cooldown behavior. Results must be explainable from persisted evidence and deterministic for the same inputs.

Evaluate from `AuthenticationLog`, `RequestActivity`, or normalized external events according to the rule. Never use authentication logs as request-volume telemetry. Prefer `occurred_at` for event windows and handle late external events deliberately.

## Supported patterns

Rules may cover:

- repeated failures by IP/account and brute-force sequences
- password spraying across accounts and distributed attempts against one account
- failed-then-successful login patterns and other suspicious successes
- repeated 401/403/404 responses
- suspicious path or method probing
- per-IP, per-route, or global request spikes
- controlled decoy endpoint access
- user-agent/device or source changes as supporting signals

Use multiple dimensions only when they add clear value. Avoid an expensive general rule framework before real requirements justify it.

## Classification

Severity expresses potential impact/urgency; confidence or risk, if used, expresses evidential strength. Do not conflate them. A weak single signal must not become Critical automatically. Store enough structured evidence to explain counts, sampled records, windows, grouping, and classification without copying sensitive data.

## Alerts and correlation

Persist the interpreted `SecurityEvent` where the domain requires it and create/update actionable `SecurityAlert` records idempotently. Use stable deduplication keys or explicit aggregation windows to prevent floods while retaining first/last detection times and counts.

Do not create an incident for every alert. Correlate related alerts/events by defensible dimensions such as source IP, account, source application, rule family, and time proximity. Preserve links to underlying evidence and audit automated or administrator-driven incident changes.

## Execution and tests

Run detection during ingestion or through services/jobs/scheduled commands—not dashboard rendering. Bound queries and index their grouping/time fields. Make queued evaluation retry-safe.

Test below/at/above thresholds, window boundaries, grouping separation, exclusions, late events, repeated evaluation, deduplication expiry, severity reasons, and concurrency/idempotency where relevant.

