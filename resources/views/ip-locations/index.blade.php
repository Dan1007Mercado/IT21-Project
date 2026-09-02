<x-layouts.app title="IP Locations - INTSEC">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.2em] text-cyan-300">IP Intelligence</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">IP locations</h1>
        </div>
        <div class="flex flex-wrap gap-2 text-xs text-zinc-300">
            <span class="rounded-full border border-zinc-700 bg-zinc-950/60 px-2.5 py-1.5">Unique IPs: {{ $ipLocations->total() }}</span>
            <span class="rounded-full border border-zinc-700 bg-zinc-950/60 px-2.5 py-1.5">Countries: {{ count(array_unique(array_filter(array_map(fn ($entry) => $entry['country_code'] ?? null, $ipLocations->items())))) }}</span>
            <span class="rounded-full border border-zinc-700 bg-zinc-950/60 px-2.5 py-1.5">Cities: {{ count(array_unique(array_filter(array_map(fn ($entry) => $entry['city'] ?? null, $ipLocations->items())))) }}</span>
        </div>
    </div>

    <p class="mt-4 max-w-3xl text-sm leading-6 text-zinc-400">
        Approximate geographic locations of recorded security events. These markers reflect public IP geolocation and are not exact physical locations.
    </p>

    <section class="mt-8 rounded-lg border border-zinc-800 bg-zinc-900 p-5">
        <div id="ip-location-map" class="w-full overflow-hidden rounded-xl border border-zinc-800" style="height: 450px; min-height: 280px;"></div>
        <div id="ip-location-empty" class="hidden rounded-xl border border-dashed border-zinc-700 bg-zinc-950/60 px-4 py-5 text-sm text-zinc-400">
            No geographic IP locations available.
        </div>
    </section>

    <section class="mt-8 overflow-hidden rounded-lg border border-zinc-800 bg-zinc-900">
        <div class="border-b border-zinc-800 px-5 py-4">
            <h2 class="text-lg font-semibold text-white">Observed IP locations</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="border-b border-zinc-800 text-xs uppercase tracking-[0.14em] text-zinc-500">
                    <tr>
                        <th class="px-4 py-3">IP Address</th>
                        <th class="px-4 py-3">Location</th>
                        <th class="px-4 py-3">Country</th>
                        <th class="px-4 py-3">ISP</th>
                        <th class="px-4 py-3">Events</th>
                        <th class="px-4 py-3">Last Seen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse ($ipLocations as $location)
                        <tr>
                            <td class="px-4 py-3 text-zinc-300">{{ $location['ip'] }}</td>
                            <td class="px-4 py-3 text-zinc-300">{{ $location['city'] ?? 'Unknown city' }}, {{ $location['region'] ?? 'Unknown region' }}</td>
                            <td class="px-4 py-3 text-zinc-400">{{ $location['country'] ?? 'Unknown country' }}</td>
                            <td class="px-4 py-3 text-zinc-400">{{ $location['isp'] ?? 'Unknown ISP' }}</td>
                            <td class="px-4 py-3 text-zinc-300">{{ $location['event_count'] ?? 0 }}</td>
                            <td class="px-4 py-3 text-zinc-500">{{ $location['last_seen'] ? \Carbon\Carbon::parse($location['last_seen'])->format('M j, Y H:i') : 'Unknown' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-400">No public IP locations are currently available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-6">
        {{ $ipLocations->links() }}
    </div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        const ipLocations = @json($ipLocations->items());
        const mapElement = document.getElementById('ip-location-map');
        const emptyState = document.getElementById('ip-location-empty');

        if (mapElement && window.L) {
            const validLocations = ipLocations.filter((entry) => {
                const latitude = Number(entry.latitude);
                const longitude = Number(entry.longitude);
                return Number.isFinite(latitude) && Number.isFinite(longitude)
                    && latitude >= -90 && latitude <= 90
                    && longitude >= -180 && longitude <= 180;
            });

            if (validLocations.length > 0) {
                const map = L.map('ip-location-map', {
                    scrollWheelZoom: true,
                    zoomControl: true,
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);

                const grouped = new Map();

                validLocations.forEach((entry) => {
                    const latitude = Number(entry.latitude);
                    const longitude = Number(entry.longitude);
                    const key = `${latitude.toFixed(4)}:${longitude.toFixed(4)}`;
                    const list = grouped.get(key) ?? [];
                    list.push(entry);
                    grouped.set(key, list);
                });

                const markers = [];

                grouped.forEach((entries) => {
                    const first = entries[0];
                    const latitude = Number(first.latitude);
                    const longitude = Number(first.longitude);
                    const totalEvents = entries.reduce((sum, entry) => sum + (Number(entry.event_count) || 0), 0);
                    const popupContent = `
                        <div style="min-width: 220px; color: #0f172a; line-height: 1.5;">
                            <div style="font-weight: 700; margin-bottom: 6px;">Approximate IP Location</div>
                            <div><strong>IP:</strong> ${entries.map((entry) => entry.ip).join('<br>')}</div>
                            <div><strong>City:</strong> ${first.city ?? 'Unknown city'}</div>
                            <div><strong>Region:</strong> ${first.region ?? 'Unknown region'}</div>
                            <div><strong>Country:</strong> ${first.country ?? 'Unknown country'}</div>
                            <div><strong>ISP:</strong> ${first.isp ?? 'Unknown ISP'}</div>
                            <div><strong>Organization:</strong> ${first.organization ?? 'Unknown organization'}</div>
                            <div><strong>ASN:</strong> ${first.asn ?? 'Unknown'}</div>
                            <div><strong>Timezone:</strong> ${first.timezone ?? 'Unknown time zone'}</div>
                            <div><strong>Security Events:</strong> ${totalEvents}</div>
                            <div><strong>Last Seen:</strong> ${first.last_seen ? new Date(first.last_seen).toLocaleString() : 'Unknown'}</div>
                        </div>
                    `;

                    L.marker([latitude, longitude])
                        .addTo(map)
                        .bindPopup(popupContent);

                    markers.push([latitude, longitude]);
                });

                if (markers.length === 1) {
                    map.setView(markers[0], 4);
                } else {
                    map.fitBounds(L.latLngBounds(markers), { padding: [30, 30] });
                }

                setTimeout(() => map.invalidateSize(), 150);
            } else if (emptyState) {
                emptyState.classList.remove('hidden');
            }
        } else if (emptyState) {
            emptyState.classList.remove('hidden');
        }
    </script>
</x-layouts.app>
