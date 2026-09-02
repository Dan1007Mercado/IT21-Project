# INTSEC Scope Rule

All development must remain within the approved INTSEC project proposal.

INTSEC is an application-level intrusion monitoring and incident response system.

Do not turn the project into:

* a network IDS
* a network firewall
* a SIEM
* a full honeypot infrastructure
* an AI/ML detection system
* a commercial-grade SOC platform

Current implementation must prioritize:

* authentication
* RBAC
* user management
* administrator dashboard
* security event visibility
* authentication logs
* incident management
* investigation remarks
* incident status
* application-level IP blocking
* settings

Do not implement deferred security technologies until explicitly requested:

* reCAPTCHA
* Email OTP
* TOTP / 2FA
* OAuth
* MaxMind GeoLite2
* Reverb
* WebSockets
* Echo
* decoy login
* automated detection rules

When a requested feature is outside the proposal, stop and identify that it is outside the current project scope before implementing it.
