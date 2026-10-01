# INTSEC Scope Rules

INTSEC is an application-level intrusion monitoring and incident response system. It may observe HTTP/HTTPS request metadata available after TLS termination and receive authenticated telemetry from monitored web applications.

## Approved application-level scope

INTSEC may implement:

- authentication/session monitoring and account/RBAC administration
- safe HTTP/HTTPS request telemetry and request-rate/spike monitoring
- explainable rule-based intrusion detection
- brute-force, password-spray, distributed-account-attempt, and failed-then-success detection
- repeated 401/403/404 detection and suspicious path probing
- controlled decoy-login or decoy-endpoint monitoring
- user-agent/device signals and repeated-IP analysis
- centralized IP resolution and public/private/loopback/reserved/invalid classification
- public-IP intelligence, approximate GeoIP, and Leaflet/OpenStreetMap visualization
- security events, alerts, deduplication, correlation, incidents, remarks, and status history
- application-level IP ALLOW/BLOCK policy, distribution, and enforcement
- authenticated monitored-application event ingestion and blocklist APIs
- real-time-capable delivery, including Laravel Reverb/Echo where practical
- database-driven dashboards, audit trails, settings, and automated security tests

These are permitted capabilities, not requirements for every task. Implement only what the request needs and verify optional dependencies first.

## Boundaries

INTSEC is not:

- a packet-sniffing IDS, raw-packet collector, or PCAP analyzer
- a kernel, host, or network firewall
- a full enterprise SIEM or commercial SOC
- an autonomous AI/ML IDS
- a malware-analysis sandbox
- a full standalone honeypot infrastructure
- an active offensive-security platform

Application-level decoy routes are allowed; a separate general-purpose honeypot platform is not. Application-level block policy is allowed; claiming upstream network enforcement is not.

When uncertain, prefer a reasonable application-level interpretation consistent with the current task. Do not block approved functionality because older guidance called it deferred. Ask only when requested behavior materially crosses these boundaries.
