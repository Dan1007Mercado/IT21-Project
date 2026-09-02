# INTSEC Security Rules

## Core rules

- Security-sensitive functionality must be authorized server-side.
- Validate input and reject unsafe operations.
- Protect against mass assignment.
- Do not rely on client-side role information.
- Do not expose secrets.
- Do not log passwords, authentication secrets, tokens, or OTP values.
- Do not implement security controls solely in JavaScript or frontend code.

## Authentication

Authentication logging must capture useful investigation context without storing unnecessary sensitive data.

Required context includes:

- user
- attempted username/email where appropriate
- IP address
- user agent
- status
- action
- timestamp
- failure reason when applicable
- request-related context where useful

## Security events and incidents

- Security events must be stored as records in the database.
- Support severity levels: Normal, Warning, Suspicious, High, Critical.
- Incidents must persist investigation history and lifecycle states.
- Incident remarks must be stored as historical records rather than replacing prior notes.

## IP blocking

Blocked IP data must include:

- IP address
- reason
- administrator responsible
- blocked timestamp
- optional expiration
- status

The implementation must be enforceable via Laravel application logic or middleware. A Filament-only button is not enough.

## Principle

Apply security design to the real application workflow, not only to the UI.
