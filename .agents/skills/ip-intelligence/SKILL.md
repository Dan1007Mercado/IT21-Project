---
name: ip-intelligence
description: Implement or review INTSEC client-IP resolution, proxy trust, IPv4/IPv6 normalization/classification, public-IP enrichment, GeoIP caching, and IP map behavior.
---

# INTSEC IP Intelligence

## Resolution and normalization

Use one shared resolver/classifier for request telemetry, authentication logs, external normalization, detection, enrichment, maps, and blocking. Configure Laravel trusted proxies deliberately; never parse or trust forwarding headers independently in feature code.

Normalize valid IPv4/IPv6 addresses and CIDR networks consistently. Preserve a canonical value used for matching/grouping while retaining raw input only when explicitly useful and safe.

Classify at least public, private/reserved, loopback, and invalid. Treat IPv4-mapped IPv6 and proxy chains consistently. Tests must include both IP families and spoofed/untrusted forwarding headers.

## Enrichment

Only public addresses are eligible for GeoIP/WHOIS enrichment. Do not call external providers for private, reserved, loopback, or invalid input. Run remote or expensive lookups outside page rendering, cache results with a deliberate TTL/provider version, rate-limit provider calls, and handle unavailable providers without fabricating data.

GeoIP is approximate. Store provider, lookup time, and precision/fields actually returned. Do not infer or invent coordinates from country/region labels.

## Maps and UI

Leaflet/OpenStreetMap is an acceptable visualization when dependencies and tile use are configured. Plot only records with valid coordinates, label location as approximate, cluster or aggregate dense data, escape popup content, and show explicit unavailable/unknown states. A missing location must not appear at `(0, 0)`.

Maps are read-only visualization of persisted enrichment; opening a map must not trigger uncontrolled lookup or detection work.

## Enforcement interaction

Blocking uses canonical IP/network matching, not GeoIP location. Never block based solely on approximate geography unless a separately approved, explicit policy requires it. Keep ALLOW/BLOCK precedence independent of map presentation.

