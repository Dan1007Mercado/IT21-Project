---
name: laravel
description: Build or review INTSEC Laravel application code, including controllers, middleware, services, jobs, events, queries, validation, authorization, and Eloquent workflows.
---

# Laravel Development

Use the Laravel version and APIs actually installed in `composer.json` and `composer.lock`; proposals do not override runtime dependencies.

## Application structure

- Keep controllers thin and use Form Requests for substantial input validation/authorization.
- Put non-trivial workflows in focused services/actions/domain classes.
- Use DTOs or value objects only when they clarify boundaries or invariants.
- Use middleware for request telemetry, trusted request context, and application-level IP enforcement.
- Use policies, gates, and route middleware for server-side authorization.
- Use jobs for slow, retryable, or asynchronous work such as enrichment or batch detection.
- Use events/listeners to decouple persisted alert creation from optional notifications/broadcasting.
- Do not put business logic in Blade, Filament Resources, client code, or dashboard rendering.
- Avoid unnecessary repository/pattern layers when Eloquent and a focused service are sufficient.

## Data and consistency

Use Eloquent relationships, casts, query scopes, and query services for reusable operational metrics. Use transactions and row locks where multi-record security state must remain consistent. Make jobs and externally retried operations idempotent.

Paginate high-volume datasets, select only needed columns, eager-load relationships, avoid N+1 queries, and add indexes justified by query patterns. Cache only when invalidation preserves correctness and security state is not made stale.

## Security and failure behavior

Use Laravel validation, escaping, hashing, rate limiting, configuration, and secret handling. Protect mass assignment. Never trust frontend state for authorization. Define monitoring failures deliberately so optional telemetry/enrichment does not unnecessarily break business traffic, while required atomic operations never report false success.

## Schema workflow

For schema changes:

1. Add a forward migration.
2. Update models, casts, and relationships.
3. Update factories/seeders where relevant.
4. Update query/index assumptions.
5. Add or update behavior-focused tests.
6. Verify migrations from a clean database and run relevant tests.

Preserve working conventions and change incrementally.
