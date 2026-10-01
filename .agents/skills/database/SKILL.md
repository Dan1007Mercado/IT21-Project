---
name: database
description: Design or change INTSEC MySQL/Laravel persistence, migrations, Eloquent relationships, indexes, retention-aware telemetry tables, factories, or seeders.
---

# INTSEC Database and Eloquent

Use MySQL through Laravel's database layer. Schema changes use forward migrations and must work from a clean database. Use foreign keys and constraints where appropriate, consistent timestamps, and Eloquent relationships/casts.

## Core records

The domain may include:

- users and authorization data
- `authentication_logs`
- `request_activities`
- `security_events`
- `security_alerts`
- incidents, remarks, and status histories
- blocked IP/network policies
- audit logs and system settings
- detection-rule configuration, IP enrichment, and monitored sources where introduced

Keep authentication telemetry, request telemetry, interpreted events, actionable alerts, and incidents separate. Avoid dashboard-specific copies or excessive duplicate storage.

## Queryable fields and indexes

Base indexes on actual filters, joins, grouping, retention, and ordering. Common candidates include:

- `request_activities`: `occurred_at`, `ip_address`, `user_id`, `status_code`, route name/path, and classification
- `authentication_logs`: `occurred_at`, `ip_address`, `user_id`, attempted identity, and status/action
- `security_events`: `occurred_at`, `source_ip`, event type, severity, source, and source-scoped external event ID
- `security_alerts`: `occurred_at`, `source_ip`, severity, status, and alert type/rule
- `incidents`: status, severity, source IP, assignment, and `last_detected_at`
- `blocked_ips`: normalized address/network, action, enabled/status, and expiration

Prefer composite indexes that match important queries and scoped uniqueness such as source plus external event ID. Do not add every possible single-column index; consider write cost and selectivity.

## Modeling rules

Use typed columns for queryable core fields and JSON only for supplementary metadata/evidence. Normalize IPv4/IPv6 and CIDR values consistently through domain code. Preserve event time separately from ingestion/record timestamps when sources can submit historical events.

Model many-to-many evidence explicitly when an incident or alert can link multiple records; do not force a single foreign key if requirements need correlation. Use transactions for multi-record workflows that require atomicity.

## Volume and lifecycle

Request telemetry can be high volume. Plan pagination, bounded queries, aggregation, retention/archival policy, and batch deletion without weakening investigation requirements. Detection queries must use event occurrence time consistently and avoid unbounded scans.

## Factories and seeders

Populate production tables through production models with realistic, non-sensitive records. Never create fake dashboard-only data or embed real credentials/secrets. Seed relationships and timestamps coherently enough to exercise real queries.
