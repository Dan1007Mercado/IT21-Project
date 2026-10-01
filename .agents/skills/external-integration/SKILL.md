---
name: external-integration
description: Implement or review INTSEC monitored-application connectors for authenticated event ingestion and application-level blocklist distribution/enforcement.
---

# INTSEC External Integration

## Contract boundaries

The monitored application sends security telemetry to INTSEC's ingestion API. INTSEC exposes authenticated application-level block policy to the monitored application, which performs enforcement in its own request stack.

Version both contracts. Identify each monitored source/sensor explicitly and document timestamps, IDs, field limits, authentication/signature scheme, error semantics, pagination/cursors, and compatibility expectations.

## Event ingestion

- Authenticate the source and keep secrets in environment/configuration, never payloads, URLs, logs, or responses.
- Validate schema, size, timestamps, identifiers, and allowed metadata before normalization.
- Deduplicate external event IDs within source scope and make retries idempotent.
- Rate-limit and mitigate replay with signed timestamps/nonces or equivalent controls when practical.
- Treat submitted severity and actor/IP fields as untrusted evidence; normalize/classify server-side.
- Persist receipt/evidence before asynchronous enrichment/detection where reliability requires it.

Return stable machine-readable success, duplicate, validation, authentication, throttling, and transient-failure responses without leaking internals.

## Block distribution and enforcement

Expose only active, enabled, unexpired rules applicable to the requesting source. Include a policy version/freshness marker and deterministic address/network/action semantics. Authenticate access and avoid exposing internal audit metadata unnecessarily.

The monitored application should cache the last known valid policy and refresh with bounded timeouts. Prefer availability-preserving fail-open behavior when INTSEC is temporarily unavailable unless the approved threat model requires fail-closed. Never interpret an error response as an empty blocklist that silently clears protection.

## Reliability and observability

Use bounded retries with backoff/jitter and a durable queue/outbox when event loss would otherwise be permanent. Do not retry permanent validation/authentication failures indefinitely. Record safe delivery/receipt status and surface prolonged failures operationally without logging secrets.

## Tests

Test source authentication, version/schema validation, field limits, duplicate/replay handling, clock skew, retry/idempotency, source isolation, severity normalization, active/expired policy output, stale-cache behavior, timeouts, and fail-open enforcement behavior.
