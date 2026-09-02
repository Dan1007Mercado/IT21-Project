# Security Skill

## Core safety rules

- Keep all security-sensitive enforcement server-side.
- Do not trust client-side role or access information.
- Validate all input with Laravel validation.
- Protect against mass assignment and unsafe object hydration.
- Do not expose secrets or log credentials.

## Auth and monitoring

- Persist authentication attempts and outcomes as security-relevant domain data.
- Capture useful detection context such as user, attempted identity, IP address, user agent, status, action, and timestamps.
- Avoid storing sensitive secrets, passwords, tokens, or OTP values.

## Event and incident handling

- Security events must be saved as application records.
- Severity levels should follow the defined values: Normal, Warning, Suspicious, High, Critical.
- Incidents should maintain investigation history with remarks and status transitions.

## Blocking enforcement

- Blocked IP management must be implemented as data records with admin ownership and lifecycle fields.
- Ensure the system can enforce application-level blocking through Laravel middleware or application logic.
- A Filament button alone is not sufficient as the blocking mechanism.

## Security posture

The system must support operational investigation without introducing insecure shortcuts or frontend-only controls.
Security Development Skill

Treat security-sensitive functionality as server-side functionality.

Authentication

Use Laravel authentication mechanisms.

Record useful authentication activity.

Never log:

passwords
authentication tokens
OTP secrets
session secrets
Authorization

Use RBAC and Laravel authorization.

Every administrator operation must be authorized.

Never trust role information sent by the client.

Logging

Security logs should contain useful investigation context without storing unnecessary secrets.

Record relevant:

user
IP
timestamp
action
status
user agent
request context where appropriate
IP Handling

IP addresses are security-relevant data.

Validate and normalize IP values appropriately.

Application-level IP blocking must eventually be enforced using Laravel server-side logic/middleware.

Do not treat a UI status change as actual IP enforcement.

Input

Validate all user-controlled input.

Use appropriate Laravel validation.

Protect against mass assignment.

Incident Response

Investigation remarks must maintain historical records.

Do not overwrite investigation history unnecessarily.

Incident status transitions must be authorized.

Testing

Security-sensitive functionality should have tests covering:

unauthorized access
authorized administrator access
standard-user restrictions
IP blocking
incident updates
security-event visibility