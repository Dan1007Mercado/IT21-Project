# INTSEC Core Skill

## Purpose

Implement the INTSEC application as an application-level intrusion monitoring and incident response system.

## Core scope

Build the current phase first:

- authentication and session management
- login/logout
- user accounts and RBAC
- admin dashboard
- security dashboard metrics from database data
- authentication logging
- security event persistence
- incident management with remarks and statuses
- application-level IP blocking
- settings and admin interfaces

## Deferred features

Do not implement these in the current phase:

- Google reCAPTCHA
- email OTP
- TOTP / Authenticator 2FA
- OAuth
- MaxMind GeoLite2
- Laravel Reverb
- WebSockets
- Echo real-time monitoring
- decoy/bait login portal
- automated intrusion-detection rules
- advanced threat intelligence
- AI/ML detection

## Domain model

Keep the primary domain centered on:

- User
- Role
- AuthenticationLog
- SecurityEvent
- Incident
- IncidentRemark
- BlockedIp

Use Laravel models, migrations, factories, and Eloquent relationships.

## Architecture

- Laravel owns business logic and security logic.
- Filament owns the admin/security operations interface.
- MySQL stores persisted application data.
- Keep non-trivial logic in services/actions/domain classes, not in Filament resources.
- Use policies, middleware, and validation for enforcement.

## Delivery expectations

- Prefer incremental changes over broad rewrites.
- Reuse existing Laravel conventions.
- Keep the project aligned with the approved scope and architecture.
- Validate behavior with tests when core functionality changes.
