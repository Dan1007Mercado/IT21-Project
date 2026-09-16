# Hotel to INTSEC integration

INTSEC is the central security authority. The Hotel application remains independently deployable and sends selected security events over authenticated HTTP; the applications do not share a database.

## Local configuration

Run INTSEC at `http://127.0.0.1:8000` and Hotel at `http://127.0.0.1:8001`.

Generate one long random token and set it locally (never commit it):

```dotenv
# INTSEC .env
INTSEC_API_TOKEN=replace-with-a-long-random-secret
INTSEC_EVENT_SOURCES=hotel-booking

# Hotel .env
INTSEC_API_URL=http://127.0.0.1:8000
INTSEC_API_TOKEN=replace-with-the-same-secret
INTSEC_SOURCE=hotel-booking
INTSEC_BLOCKLIST_CACHE_SECONDS=60
```

## API

Both endpoints require `Authorization: Bearer <INTSEC_API_TOKEN>`.

`POST /api/security/events` accepts a selected event from a configured source. The client-provided severity is retained only as metadata; INTSEC performs the final classification.

```json
{
  "event_id": "e2c9e4d2-35a4-4e7b-8e75-8c0495cfb8ad",
  "source": "hotel-booking",
  "event_type": "login_failed",
  "severity": "warning",
  "ip": "203.0.113.20",
  "route": "/login",
  "method": "POST",
  "user_agent": "Mozilla/5.0",
  "message": "Failed authentication attempt",
  "metadata": {}
}
```

Example response (`201`):

```json
{"data":{"id":42,"severity":"Warning","duplicate":false,"alert_id":null,"incident_id":null}}
```

The optional UUID `event_id` makes retries idempotent. Invalid tokens receive `401`; unconfigured INTSEC intake receives `503` and does not persist an event. Input validation failures receive `422`.

`GET /api/security/blocked-ips` returns active, enabled central BLOCK rules. The Hotel caches this response briefly and enforces matching exact IPs/CIDR ranges in web middleware with `403` responses. INTSEC remains the only blocklist store.

## Detection and workflow

Hotel reports `login_failed`, `login_success`, `logout`, unauthorized administrator access, and the isolated `/security/monitored-login` endpoint. It never sends a password, bearer token, or session secret.

INTSEC stores received data as `SecurityEvent` records with `source` and `external_event_id`, applies existing settings for failed-login and repeated-IP thresholds, creates/updates existing `SecurityAlert` records for repeated failures, and reuses the IP activity incident service for threshold-triggered incidents. Receipt is recorded in `AuditLog`.

## Failure behavior and testing

The Hotel `IntsecClient` uses short HTTP timeouts. A failed connection or invalid response is logged to its normal Laravel log without tokens or request credentials; authentication and Hotel business flows continue.

Start both servers, configure the matching token, then test a successful Hotel login, repeated failed logins, an administrator denial, and `GET /security/monitored-login`. Verify the corresponding INTSEC events/alerts/incidents. Stop INTSEC and repeat a Hotel login to verify fail-open behavior. Finally submit an event with an invalid bearer token and verify `401` with no persisted event.
