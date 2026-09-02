# Database Skill

## Standards

Use proper database design and Laravel persistence patterns.

- Add migrations for schema changes.
- Use Eloquent models and relationships for all domain data.
- Keep database constraints and indexes sensible.
- Avoid fake demo tables or ad hoc storage for application functionality.

## Seed data

- Seed realistic development data only through the real application models and tables.
- Ensure seeders align with production data structures.

## Domain records

The database should support:

- users and roles
- authentication logs
- security events
- incidents and remarks
- blocked IP management
- settings and operational metadata

## Implementation principle

All data used by dashboard widgets and admin screens should originate from real database queries and persisted records.
# Database and Eloquent Skill

Use MySQL through Laravel's database layer.

## Core Tables

The initial domain should include:

* users
* roles
* authentication_logs
* security_events
* incidents
* incident_remarks
* blocked_ips

## Relationships

At minimum consider relationships such as:

User
→ Role

User
→ Authentication Logs

User
→ Security Events

Security Event
→ Incident

Incident
→ Incident Remarks

User
→ Incident Remarks

User
→ Blocked IP records as administrator/auditor

## Principles

Use foreign keys where appropriate.

Use indexes for frequently queried fields, especially security-monitoring fields such as:

* IP address
* timestamp
* severity
* status
* event type
* user ID

Use appropriate timestamps.

Do not duplicate data unnecessarily.

Do not create dashboard-specific copies of security data.

Factories and seeders must populate the real domain tables.

Seed data should look realistic enough to demonstrate the system but must not contain real credentials or sensitive information.
