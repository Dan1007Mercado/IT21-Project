<x-layouts.app title="IP Intelligence - INTSEC" wide realtime-entities="request_activity,blocked_ip" :realtime-source="$monitoringSource->value">
    @php
        $hasFilters = collect(request()->only(['search', 'ip', 'ip_type', 'country']))->filter(fn ($value) => filled($value))->isNotEmpty();
        $clearUrl = auth()->user()->isAdministrator()
            ? route('monitoring.ip-locations', $monitoringSource->value)
            : route('ip-locations');
    @endphp
    <x-slot:head>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    </x-slot:head>

    <div class="ops-page">
        <x-ui.page-header
            :kicker="$monitoringSource->label().' IP intelligence'"
            title="IP monitoring"
            :description="'Approximate public-IP enrichment for '.$monitoringSource->label().' activity. Private, reserved, loopback, and invalid addresses remain visible but are never plotted.'"
        >
            <x-slot:context>
                <span class="ops-context-pill ops-context-pill--live">Live request and policy updates</span>
                <span class="ops-context-pill">Approximate GeoIP</span>
            </x-slot:context>
            @if (auth()->user()->isAdministrator())
                <x-slot:actions>
                    <a class="ops-button ops-button--secondary" href="{{ route('ip-management.index') }}">Manage IP policy</a>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        <section class="ops-section" aria-label="IP intelligence metrics">
            <div class="ops-grid-3">
                <x-security.metric-card label="IPs on this page" :value="number_format($ipLocations->count())" context="Observed addresses in the current result page" />
                <x-security.metric-card label="Countries observed" :value="number_format($countryCount)" tone="emerald" context="Distinct enriched country codes for this source" />
                <x-security.metric-card label="Cities observed" :value="number_format($cityCount)" tone="zinc" context="Distinct enriched city values for this source" />
            </div>
        </section>

        <form method="GET" class="ops-filter" aria-label="IP monitoring filters">
            <div class="ops-filter-heading">
                <h2 class="ops-filter-title">Filter observed addresses</h2>
                @if ($hasFilters)<span class="ops-filter-state">Filters active</span>@endif
            </div>
            <div class="ops-filter-grid">
                <label class="ops-field ops-field--search" for="ip-search">
                    <span class="ops-label">Search</span>
                    <input id="ip-search" name="search" value="{{ request('search') }}" placeholder="IP, city, region, country, or organization">
                </label>
                <label class="ops-field" for="ip-address-filter">
                    <span class="ops-label">IP contains</span>
                    <input id="ip-address-filter" name="ip" value="{{ request('ip') }}" placeholder="IPv4 or IPv6">
                </label>
                <label class="ops-field" for="ip-type-filter">
                    <span class="ops-label">IP type</span>
                    <select id="ip-type-filter" name="ip_type">
                        <option value="">All IP types</option>
                        @foreach (['public', 'private', 'loopback', 'reserved', 'invalid'] as $type)
                            <option value="{{ $type }}" @selected(request('ip_type') === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ops-field" for="ip-country-filter">
                    <span class="ops-label">Country</span>
                    <select id="ip-country-filter" name="country">
                        <option value="">All countries</option>
                        @foreach ($countries as $code => $country)
                            <option value="{{ $code }}" @selected(request('country') === $code)>{{ $country }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="ops-filter-actions">
                    <button class="ops-button ops-button--primary" type="submit">Apply filters</button>
                    <a class="ops-button ops-button--secondary" href="{{ $clearUrl }}">Clear</a>
                </div>
            </div>
        </form>

        <section class="ops-section ops-panel" aria-labelledby="ip-map-title">
            <div class="ops-panel-header">
                <div>
                    <h2 id="ip-map-title" class="ops-panel-title">Public-IP map</h2>
                    <p class="ops-panel-description">Only public addresses with valid approximate coordinates on this result page are plotted.</p>
                </div>
                <span class="ops-context-pill">OpenStreetMap</span>
            </div>
            <div class="ops-map-wrap">
                <div id="ip-location-map" class="ops-map" aria-label="Map of approximate public IP locations"></div>
                <div id="ip-location-empty" class="hidden">
                    <x-ui.empty-state title="No public IPs available for mapping" description="This page has no public addresses with valid approximate coordinates." />
                </div>
            </div>
        </section>

        <section class="ops-table-shell" aria-labelledby="ip-results-title">
            <div class="ops-panel-header">
                <div>
                    <h2 id="ip-results-title" class="ops-panel-title">Observed IP addresses</h2>
                    <p class="ops-panel-description">Policy state reflects current application-level ALLOW/BLOCK evaluation.</p>
                </div>
                <span class="ops-context-pill">{{ $ipLocations->count() }} on this page</span>
            </div>
            <div class="ops-table-scroll" tabindex="0" aria-label="Scrollable IP intelligence table">
                <table class="ops-table ops-table--wide">
                    <caption class="sr-only">Observed IP intelligence for {{ $monitoringSource->label() }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">IP address</th>
                            <th scope="col">Type</th>
                            <th scope="col">Approximate location</th>
                            <th scope="col">ASN / organization</th>
                            <th scope="col">Last seen</th>
                            <th scope="col">Requests</th>
                            <th scope="col">Policy</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ipLocations as $row)
                            @php
                                $location = match ($row['ip_type']) {
                                    'loopback' => 'Localhost',
                                    'private' => 'Private network',
                                    'public' => collect([$row['city'], $row['region'], $row['country']])->filter()->join(', ') ?: 'Awaiting enrichment',
                                    default => 'No public geolocation',
                                };
                            @endphp
                            <tr>
                                <td><span class="ops-technical ops-wrap-anywhere" title="{{ $row['ip'] }}">{{ $row['ip'] }}</span></td>
                                <td><span class="ops-badge ops-badge--{{ $row['ip_type'] === 'public' ? 'cyan' : 'muted' }}">{{ ucfirst($row['ip_type']) }}</span></td>
                                <td>
                                    <span class="ops-wrap-anywhere">{{ $location }}</span>
                                    @if ($row['ip_type'] === 'public' && $row['last_enriched_at'])
                                        <span class="ops-cell-meta">Enriched {{ \Carbon\Carbon::parse($row['last_enriched_at'])->diffForHumans() }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="ops-wrap-anywhere">{{ $row['asn'] ? 'AS'.$row['asn'] : 'ASN unavailable' }}</span>
                                    <span class="ops-cell-meta ops-wrap-anywhere" title="{{ $row['organization'] ?? $row['isp'] }}">{{ $row['organization'] ?? $row['isp'] ?? 'Organization unknown' }}</span>
                                </td>
                                <td class="ops-numeric" title="{{ \Carbon\Carbon::parse($row['last_seen'])->format('M j, Y H:i:s T') }}">{{ \Carbon\Carbon::parse($row['last_seen'])->diffForHumans() }}</td>
                                <td class="ops-numeric">{{ number_format($row['event_count']) }}</td>
                                <td><span class="ops-badge ops-badge--{{ $row['is_blocked'] ? 'red' : 'muted' }}">{{ $row['is_blocked'] ? 'Blocked' : 'No block' }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-ui.empty-state
                                        :title="$hasFilters ? 'No observed IPs match these filters' : 'No IP activity observed'"
                                        :description="$hasFilters ? 'Clear or adjust the current filters to broaden the results.' : 'Addresses will appear after request telemetry is persisted for this source.'"
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.simple-pagination :paginator="$ipLocations" label="IP addresses" />
        </section>
    </div>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            const locations = @json($mapLocations);
            const mapNode = document.getElementById('ip-location-map');
            const emptyNode = document.getElementById('ip-location-empty');

            if (window.L && locations.length) {
                const map = L.map(mapNode).setView([15, 0], 2);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);
                const bounds = [];

                locations.forEach((entry) => {
                    const latitude = Number(entry.latitude);
                    const longitude = Number(entry.longitude);
                    if (!Number.isFinite(latitude) || !Number.isFinite(longitude) || (latitude === 0 && longitude === 0)) return;

                    const popup = document.createElement('div');
                    popup.className = 'intsec-map-popup';
                    const rows = [
                        ['IP', entry.ip],
                        ['Approximate location', [entry.city, entry.country].filter(Boolean).join(', ') || 'Unknown'],
                        ['ASN / organization', [entry.asn ? `AS${entry.asn}` : null, entry.organization || entry.isp].filter(Boolean).join(' · ') || 'Unknown'],
                        ['Last seen', entry.last_seen ? new Date(entry.last_seen).toLocaleString() : 'Unknown'],
                        ['Requests', String(entry.event_count || 0)],
                        ['Policy', entry.is_blocked ? 'Blocked' : 'No block'],
                    ];

                    rows.forEach(([label, value]) => {
                        const row = document.createElement('div');
                        const strong = document.createElement('strong');
                        strong.textContent = `${label}: `;
                        row.appendChild(strong);
                        row.appendChild(document.createTextNode(value));
                        popup.appendChild(row);
                    });

                    L.marker([latitude, longitude]).addTo(map).bindPopup(popup);
                    bounds.push([latitude, longitude]);
                });

                if (bounds.length === 1) map.setView(bounds[0], 5);
                else if (bounds.length) map.fitBounds(bounds, { padding: [30, 30] });
                if (!bounds.length) {
                    mapNode.classList.add('hidden');
                    emptyNode.classList.remove('hidden');
                }
                setTimeout(() => map.invalidateSize(), 100);
            } else {
                mapNode.classList.add('hidden');
                emptyNode.classList.remove('hidden');
            }
        </script>
    </x-slot:scripts>
</x-layouts.app>
