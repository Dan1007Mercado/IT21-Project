# INTSEC Architecture Rules

## Ownership

- Laravel owns application, domain, and security logic.
- Blade, Livewire, Tailwind, Filament, charts, and maps are presentation layers, not detection engines.
- Keep controllers thin. Put non-trivial processing in focused services, actions, domain classes, or jobs.
- Use middleware for request-lifecycle capture and enforcement where appropriate.
- Enforce authorization through policies, gates, middleware, and other server-side logic.
- Use migrations and Eloquent relationships; persisted database records are the source of truth.

## Processing pipeline

Prefer this responsibility flow:

`Application request / authentication / external event → capture → normalize → persist telemetry → enrich → detect → classify → alert → correlate → incident → response → audit`

Do not create a class for every stage without need, but keep these boundaries:

- `AuthenticationLog` is authentication telemetry.
- `RequestActivity` is general HTTP request telemetry.
- External telemetry crosses an authenticated, validated ingestion boundary.
- `SecurityEvent` is interpreted security-relevant activity.
- `SecurityAlert` is an actionable detection.
- `Incident` is an investigation/response case.

Never substitute authentication logs for HTTP request volume.

## Operational invariants

- Capture and detection run independently of dashboard views.
- Dashboards and reports are primarily read/query operations and must not create detections as a rendering side effect.
- Centralize client-IP resolution, normalization, and classification.
- Perform GeoIP and remote/expensive enrichment outside page rendering; cache or queue it when useful.
- Validate and authenticate external ingestion before domain processing.
- Keep detection deterministic, testable, and explainable.
- Use transactions for multi-record security operations that must succeed atomically.
- Use queues/jobs where they improve reliability or latency; make retries, idempotency, deduplication, and failures explicit.
- Monitoring failure must not unnecessarily take monitored business applications offline.
- Preserve working behavior unless a requirement intentionally replaces it.
- Evolve schemas through migrations, not destructive manual edits.

## Query and delivery architecture

Use query scopes or query services for reusable operational metrics. Avoid N+1 queries, paginate high-volume records, and index filters/grouping fields. Cache only when invalidation preserves correctness.

Real-time delivery may broadcast persisted alerts through Laravel events, listeners, queues, and Reverb/Echo when installed. Broadcasting is a delivery channel, not the source of truth; reconnecting clients recover state from the database.
