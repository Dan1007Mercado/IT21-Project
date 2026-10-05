<x-layouts.app title="Login Activity - INTSEC" wide realtime-entities="authentication_log" :realtime-source="$monitoringSource->value">
    @php
        $hasFilters = collect(request()->only(['search', 'status', 'action', 'identity', 'ip', 'user_id', 'from', 'to']))->filter(fn ($value) => filled($value))->isNotEmpty();
        $clearUrl = auth()->user()->isAdministrator()
            ? route('monitoring.login-activity', $monitoringSource->value)
            : route('login-activity');
    @endphp
    <div class="ops-page">
        <x-ui.page-header
            :kicker="$monitoringSource->label().' authentication telemetry'"
            title="Login activity"
            description="Authentication attempts, successful sign-ins, and logout activity. General HTTP requests are tracked separately."
        >
            <x-slot:context>
                <span class="ops-context-pill ops-context-pill--live">Live authentication updates</span>
                <span class="ops-context-pill">Source <span class="ops-technical">{{ $monitoringSource->value }}</span></span>
            </x-slot:context>
            @if (auth()->user()->isAdministrator())
                <x-slot:actions>
                    <a class="ops-button ops-button--secondary" href="{{ route('monitoring.overview', $monitoringSource->value) }}">Back to overview</a>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        <form method="GET" class="ops-filter" aria-label="Login activity filters">
            <div class="ops-filter-heading">
                <h2 class="ops-filter-title">Filter authentication telemetry</h2>
                @if ($hasFilters)<span class="ops-filter-state">Filters active</span>@endif
            </div>
            <div class="ops-filter-grid">
                <label class="ops-field ops-field--search" for="login-search">
                    <span class="ops-label">Search</span>
                    <input id="login-search" name="search" value="{{ request('search') }}" placeholder="User, identity, IP, action, or outcome">
                </label>
                <label class="ops-field" for="login-status">
                    <span class="ops-label">Outcome</span>
                    <select id="login-status" name="status">
                        <option value="">All outcomes</option>
                        <option value="successful" @selected(request('status') === 'successful')>Successful</option>
                        <option value="failed" @selected(request('status') === 'failed')>Failed</option>
                    </select>
                </label>
                <label class="ops-field" for="login-action">
                    <span class="ops-label">Action</span>
                    <select id="login-action" name="action">
                        <option value="">All actions</option>
                        <option value="login" @selected(request('action') === 'login')>Login</option>
                        <option value="logout" @selected(request('action') === 'logout')>Logout</option>
                    </select>
                </label>
                @if ($users->isNotEmpty())
                    <label class="ops-field" for="login-user">
                        <span class="ops-label">Known user</span>
                        <select id="login-user" name="user_id">
                            <option value="">All users</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }} — {{ $user->email }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label class="ops-field" for="login-identity">
                    <span class="ops-label">Attempted identity</span>
                    <input id="login-identity" name="identity" value="{{ request('identity') }}" placeholder="Email or username">
                </label>
                <label class="ops-field" for="login-ip">
                    <span class="ops-label">IP address</span>
                    <input id="login-ip" name="ip" value="{{ request('ip') }}" placeholder="IPv4 or IPv6">
                </label>
                <label class="ops-field" for="login-from">
                    <span class="ops-label">From date</span>
                    <input id="login-from" type="date" name="from" value="{{ request('from') }}">
                </label>
                <label class="ops-field" for="login-to">
                    <span class="ops-label">To date</span>
                    <input id="login-to" type="date" name="to" value="{{ request('to') }}">
                </label>
                <div class="ops-filter-actions">
                    <button class="ops-button ops-button--primary" type="submit">Apply filters</button>
                    <a class="ops-button ops-button--secondary" href="{{ $clearUrl }}">Clear</a>
                </div>
            </div>
        </form>

        <section class="ops-table-shell" aria-labelledby="login-results-title">
            <div class="ops-panel-header">
                <div>
                    <h2 id="login-results-title" class="ops-panel-title">Authentication records</h2>
                    <p class="ops-panel-description">Approximate locations are enrichment context, not proof of a person's location.</p>
                </div>
                <span class="ops-context-pill">{{ $logs->count() }} on this page</span>
            </div>
            <div class="ops-table-scroll" tabindex="0" aria-label="Scrollable login activity table">
                <table class="ops-table ops-table--xwide">
                    <caption class="sr-only">Authentication telemetry for {{ $monitoringSource->label() }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Occurred</th>
                            <th scope="col">Identity / user</th>
                            <th scope="col">Action</th>
                            <th scope="col">Outcome</th>
                            <th scope="col">Source IP</th>
                            <th scope="col">Device / browser</th>
                            <th scope="col">Approximate location</th>
                            <th scope="col">Context</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="ops-numeric">{{ $log->occurred_at?->format('M j, Y H:i:s') ?? '—' }}</td>
                                <td>
                                    <span class="ops-cell-primary ops-wrap-anywhere">{{ $log->user?->name ?? 'Unknown user' }}</span>
                                    <span class="ops-cell-meta ops-wrap-anywhere" title="{{ $log->attempted_identity }}">{{ $log->attempted_identity ?? 'No identity recorded' }}</span>
                                </td>
                                <td><span class="ops-badge ops-badge--cyan">{{ ucfirst($log->action) }}</span></td>
                                <td><x-security.status-badge :status="$log->status" /></td>
                                <td><span class="ops-technical ops-wrap-anywhere" title="{{ $log->ip_address }}">{{ $log->ip_address ?? 'Unknown' }}</span></td>
                                <td>
                                    <span class="ops-cell-primary">{{ $log->device_type ?? 'Unknown device' }}</span>
                                    <span class="ops-cell-meta ops-wrap-anywhere">{{ collect([$log->browser_name, $log->browser_version, $log->os_name])->filter()->join(' · ') ?: 'Browser details unavailable' }}</span>
                                </td>
                                <td>
                                    <span class="ops-wrap-anywhere">{{ collect([$log->city, $log->region, $log->country])->filter()->join(', ') ?: 'Not enriched' }}</span>
                                    @if ($log->country || $log->city)<span class="ops-cell-meta">Approximate public-IP result</span>@endif
                                </td>
                                <td>
                                    <span class="ops-wrap-anywhere">{{ $log->failure_reason ? str($log->failure_reason)->replace('_', ' ')->title() : '—' }}</span>
                                    <span class="ops-cell-meta ops-technical ops-wrap-anywhere">{{ $log->method }} {{ $log->route }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <x-ui.empty-state
                                        :title="$hasFilters ? 'No authentication activity matches these filters' : 'No authentication activity recorded'"
                                        :description="$hasFilters ? 'Clear or adjust the active filters to broaden the results.' : 'Login attempts and logout activity will appear here when recorded.'"
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.simple-pagination :paginator="$logs" label="authentication records" />
        </section>
    </div>
</x-layouts.app>
