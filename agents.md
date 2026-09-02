# INTSEC — Master Development Instructions

## Project

INTSEC: An Integrated Intrusion Monitoring and Incident Response System.

INTSEC is a web-based, application-level intrusion monitoring and incident response system.

The system monitors authentication and application access activity and provides administrators with visibility, investigation, incident tracking, and application-level response capabilities.

INTSEC is NOT a network-level IDS.

## Technology Stack

Use the existing project stack:

* PHP
* Laravel 12
* Filament
* MySQL
* Blade
* Livewire
* Tailwind CSS

Use Laravel conventions and the APIs provided by the versions actually installed in the project.

Do not downgrade the framework or introduce unnecessary alternative frameworks.

## Current Development Phase

Build the working core system first.

Current scope:

1. Authentication
2. Login and logout
3. User accounts
4. Roles / RBAC
5. User dashboard
6. Filament administrator panel
7. Security dashboard
8. Authentication logging
9. Security event management
10. Incident management
11. Investigation remarks
12. Incident status management
13. Application-level IP blocking management
14. System/account settings
15. Database migrations
16. Models and relationships
17. Factories/seeders for realistic development data
18. Automated tests for core functionality

## Explicitly Deferred

Do NOT implement these features in the current phase:

* Google reCAPTCHA
* Email OTP
* TOTP / Authenticator 2FA
* OAuth
* MaxMind GeoLite2
* Laravel Reverb
* WebSockets
* Laravel Echo real-time monitoring
* Decoy/bait login portal
* Automated intrusion-detection rules
* Advanced threat intelligence
* AI/ML detection

Design the architecture so these features can be added later, but do not implement them now.

## Architectural Principle

Laravel is the core application and security-logic layer.

Filament is the administrator/security-operations interface.

MySQL is the persistent data layer.

Blade/Livewire may be used for user-facing application screens.

Filament must not become the location of core business or security logic.

Non-trivial business logic should live in Laravel services/actions/domain-oriented classes.

## Core Domain

The core domain currently includes:

* User
* Role
* AuthenticationLog
* SecurityEvent
* Incident
* IncidentRemark
* BlockedIp

Use proper Eloquent relationships and database constraints.

## Authentication

Implement normal application authentication.

The system must support:

* login
* logout
* authenticated sessions
* user account management
* password management appropriate to the current Laravel authentication implementation

Authentication attempts must be designed so that successful and failed authentication activity can be persisted as security-relevant application data.

Do not log passwords, authentication secrets, or sensitive credentials.

## RBAC

At minimum support:

* Administrator
* Standard User

Administrators have access to the administrative/security monitoring functionality.

Standard users must not be able to access administrator functionality.

Authorization must be enforced server-side.

Do not rely on hiding navigation items as the security mechanism.

## Security Dashboard

The administrator dashboard should provide database-driven visibility into:

* total security events
* failed authentication attempts
* suspicious/security events
* high-severity events
* critical events
* open incidents
* blocked IPs
* recent security events
* authentication activity trends
* event severity distribution

Do not hardcode statistics.

Use actual database queries.

## Authentication Logs

Authentication activity should capture useful investigation context such as:

* user
* attempted username/email where appropriate
* IP address
* user agent
* authentication status
* action
* timestamp
* failure reason where applicable
* request-related context where appropriate

Do not store unnecessary sensitive information.

## Security Events

Security events represent security-relevant activity identified by the application's monitoring/detection layer.

The system should support severity levels:

* Normal
* Warning
* Suspicious
* High
* Critical

A SecurityEvent should contain sufficient context for an administrator to understand and investigate the event.

Security events must be persisted in the database.

Do not use fake dashboard-only security event data.

## Incidents

An incident represents an investigation/response record associated with one or more security events.

The system should support:

* incident title
* description
* severity
* status
* related security event
* assigned administrator where appropriate
* investigation remarks
* resolution information
* opened/resolved timestamps

Incident status should support a sensible lifecycle such as:

* Open
* Investigating
* Contained
* Resolved
* Closed

Do not add unnecessary incident states without a project requirement.

## Investigation Remarks

Investigation remarks must be persisted as records.

Include:

* incident
* author
* remark
* timestamp

The system should maintain an investigation history instead of replacing previous remarks.

## IP Blocking

INTSEC includes application-level IP blocking.

Administrators must be able to manage blocked IP addresses.

Blocked IP records should include:

* IP address
* reason
* administrator responsible
* blocked timestamp
* optional expiration
* status

The implementation must ultimately be enforceable by Laravel application logic/middleware.

A Filament button alone is not considered IP blocking.

## Filament

Use Filament as the administrative interface.

Build appropriate Filament:

* Resources
* Pages
* Widgets
* Tables
* Forms
* Filters
* Actions
* Infolists
* Notifications

Primary navigation should be organized around:

Dashboard

Monitoring

* Security Events
* Authentication Logs
* IP Activity / relevant monitoring view

Incident Response

* Incidents
* Blocked IPs

Administration

* Users
* Roles

Settings

Do not recreate standard Filament CRUD functionality using Blade unless there is a clear UX reason.

## UI

The administrator interface should visually communicate a security-monitoring system.

Use:

* clear status/severity badges
* readable tables
* filters
* sorting
* search
* meaningful empty states
* confirmation dialogs for destructive actions
* notifications for important administrator actions
* responsive layouts

Use Filament native components wherever practical.

## Database

Use migrations for schema changes.

Use Eloquent models and relationships.

Use factories and seeders for realistic development/test data.

Do not create fake/demo-only databases or tables.

Seeded data must use the same production models and tables that the real application will use.

## Security

All security-sensitive functionality must be authorized server-side.

Validate user input.

Protect against mass assignment.

Use Laravel validation and authorization facilities.

Do not trust client-side role information.

Do not expose secrets.

Do not log passwords, authentication tokens, OTP values, or other credentials.

Do not implement security controls solely in JavaScript or the frontend.

## Testing

Important functionality must have automated tests.

At minimum test:

* authentication
* authorization
* RBAC
* administrator access restrictions
* user access restrictions
* security-event creation/display
* incident creation/update
* incident remarks
* IP-blocking behavior
* relevant Filament authorization behavior

## Development Behavior

Before implementing a feature:

1. Inspect the existing application.
2. Read applicable AGENTS.md and .agents instructions.
3. Reuse existing architecture where appropriate.
4. Do not unnecessarily rewrite working code.
5. Make changes incrementally.
6. Run migrations/tests after significant changes.
7. Fix errors rather than leaving broken code.
8. Verify that the application actually runs.

Do not claim a feature is complete merely because files were generated.

A feature is complete when the Laravel application can execute it successfully.

## Important Design Principle

INTSEC should have this general flow:

Authentication / Application Activity
↓
Authentication Logs / Security Data
↓
Security Events
↓
Severity Classification
↓
Administrator Monitoring
↓
Incident Investigation
↓
Response
↓
Resolution

The current phase focuses on building the application foundation and administrative workflow.

The future detection engine must be able to use the same production database structures and services without redesigning the entire application.
