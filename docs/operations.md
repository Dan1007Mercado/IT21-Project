# INTSEC operations

## Database and workers

Run migrations before deploying the upgraded monitoring architecture:

```shell
php artisan migrate --force
```

Public-IP enrichment is disabled by default. When enabled in System Settings or with `INTSEC_IP_ENRICHMENT_ENABLED=true`, run a queue worker that includes the `security-enrichment` queue:

```shell
php artisan queue:work --queue=security-enrichment,default --tries=3
```

Only public addresses are submitted to the configured enrichment provider. Private, loopback, reserved, and invalid addresses remain local records without coordinates.

## Monitored application connector

Configure the same `INTSEC_API_TOKEN` in INTSEC and each approved monitored application, and list permitted source identifiers in `INTSEC_EVENT_SOURCES`. The existing event and blocklist endpoints remain compatible. The Hotel connector caches fresh policy briefly and retains its last known blocklist if INTSEC is temporarily unavailable.

## Real-time delivery

The current dependency set does not include Laravel Reverb or a WebSocket client, so dashboards use persisted database state and ordinary refreshes. Detection and incident creation do not depend on a dashboard being open. Reverb/Echo remains intentionally deferred until the server package, broadcast authentication, TLS endpoint, and frontend client can be deployed together.
