# INTSEC Architecture Rules

Laravel owns application logic.

Filament owns the administrative interface.

MySQL owns persistent application data.

Keep security/business logic independent from Filament.

Do not put substantial business logic directly into Filament Resources.

Use Laravel services/actions for substantial operations.

Use middleware for request-level enforcement.

Use policies/authorization for access control.

Use Eloquent models and relationships for domain data.

SecurityEvents are persisted domain records, not dashboard-only objects.

Incidents are persisted investigation/response records.

Blocked IP records must be enforceable by Laravel application logic.

Dashboard values must be calculated from database data.

Seed data must populate the same tables used by real application functionality.

Do not create temporary or fake data structures solely to make the UI look complete.

Preserve architectural compatibility with future:

* detection rules
* geolocation
* real-time monitoring
* decoy login

without implementing those features in the current phase.
