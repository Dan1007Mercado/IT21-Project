<x-layouts.app title="System settings - INTSEC">
    <div class="ops-page">
        <x-ui.page-header kicker="Administration" title="System settings" description="Tune deterministic protection, detection, correlation, and enrichment behavior."><x-slot:context><span class="ops-context-pill">Changes are audited</span></x-slot:context><x-slot:actions><a href="{{ route('admin.audit-logs') }}" class="ops-button ops-button--quiet">View audit logs</a></x-slot:actions></x-ui.page-header>
        @if (session('status') === 'settings-updated')<div class="ops-banner ops-banner--success" role="status">Security threshold settings updated successfully.</div>@endif
        @if ($errors->any())<div class="ops-banner ops-banner--danger" role="alert"><div><strong>Settings were not saved.</strong><ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
        @php
            $groups = [
                'Authentication protection' => [
                    'max_login_attempts' => ['Maximum failed attempts', 'Failed attempts allowed before temporary blocking activates.'],
                    'login_attempt_window_minutes' => ['Attempt window (minutes)', 'Rolling window used to evaluate excess failed logins.'],
                    'login_block_duration_minutes' => ['Temporary block duration (minutes)', 'Duration of a temporary authentication block.'],
                ],
                'Detection thresholds' => [
                    'failed_login_warning_threshold' => ['Failed-login warning threshold', 'Marks the source activity as a warning.'],
                    'repeated_authentication_threshold' => ['Repeated authentication threshold', 'Triggers on repeated suspicious authentication.'],
                    'repeated_ip_activity_threshold' => ['Repeated-IP activity threshold', 'Repeated source-IP activity used by monitoring.'],
                ],
                'Authentication detection' => [
                    'brute_force_threshold' => ['Failures against one account', null],
                    'password_spray_threshold' => ['Distinct identities from one IP', null],
                    'distributed_attack_threshold' => ['Distinct IPs attacking one account', null],
                ],
            ];
            $requestFields = [
                'repeated_request_threshold' => ['Repeated requests per IP', null],
                'request_window_seconds' => ['Request window (seconds)', null],
                'request_spike_threshold' => ['Application request spike', 'Application-level request volume, not network traffic.'],
                'repeated_404_threshold' => ['Repeated 404 responses', null],
                'repeated_403_threshold' => ['Repeated 403 responses', null],
                'repeated_401_threshold' => ['Repeated 401 responses', null],
                'sensitive_path_probe_threshold' => ['Sensitive-path probes', null],
            ];
        @endphp
        <form method="POST" action="{{ route('admin.settings.store') }}" class="ops-stack ops-settings-form">@csrf
            <div class="ops-settings-summary">
                @foreach ($groups as $heading => $fields)
                    <section class="ops-panel ops-panel--padded">
                        <div class="ops-panel-header"><div><h2 class="ops-panel-title">{{ $heading }}</h2><p class="ops-panel-description">Values must be positive integers.</p></div></div>
                        <div class="ops-form-grid">
                            @foreach ($fields as $key => [$label, $help])
                                <label class="ops-field ops-field--wide"><span class="ops-label">{{ $label }}</span><input type="number" min="1" name="{{ $key }}" value="{{ old($key, $settings[$key]) }}" required>@if ($help)<span class="ops-help">{{ $help }}</span>@endif</label>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="ops-settings-detail">
                <section class="ops-panel ops-panel--padded">
                    <div class="ops-panel-header"><div><h2 class="ops-panel-title">Request monitoring</h2><p class="ops-panel-description">Application-level request thresholds.</p></div></div>
                    <div class="ops-form-grid">
                        @foreach ($requestFields as $key => [$label, $help])
                            <label class="ops-field ops-field--wide"><span class="ops-label">{{ $label }}</span><input type="number" min="1" name="{{ $key }}" value="{{ old($key, $settings[$key]) }}" required>@if ($help)<span class="ops-help">{{ $help }}</span>@endif</label>
                        @endforeach
                    </div>
                </section>

                <div class="ops-settings-side">
                    <section class="ops-panel ops-panel--padded">
                        <div class="ops-panel-header"><div><h2 class="ops-panel-title">Correlation and deduplication</h2><p class="ops-panel-description">Values must be positive integers.</p></div></div>
                        <div class="ops-form-grid">
                            @foreach ([
                                'correlation_window_minutes' => 'Correlation window (minutes)',
                                'alert_cooldown_minutes' => 'Alert cooldown (minutes)',
                            ] as $key => $label)
                                <label class="ops-field ops-field--wide"><span class="ops-label">{{ $label }}</span><input type="number" min="1" name="{{ $key }}" value="{{ old($key, $settings[$key]) }}" required></label>
                            @endforeach
                        </div>
                    </section>

                    <section class="ops-panel ops-panel--padded">
                        <div class="ops-panel-header"><div><h2 class="ops-panel-title">IP controls and intelligence</h2><p class="ops-panel-description">Blocking defaults and public-IP enrichment.</p></div></div>
                        <div class="ops-form-grid">
                            <label class="ops-field ops-field--wide"><span class="ops-label">Default block duration (minutes)</span><input type="number" min="1" name="default_ip_block_duration_minutes" value="{{ old('default_ip_block_duration_minutes', $settings['default_ip_block_duration_minutes']) }}" required><span class="ops-help">Fallback duration for administrative IP blocks.</span></label>
                            <label class="ops-field ops-field--wide"><span class="ops-label">Cache duration (hours)</span><input type="number" min="1" name="ip_enrichment_cache_hours" value="{{ old('ip_enrichment_cache_hours', $settings['ip_enrichment_cache_hours']) }}" required></label>
                            <label class="ops-checkbox ops-field--full"><input type="hidden" name="ip_enrichment_enabled" value="0"><input type="checkbox" name="ip_enrichment_enabled" value="1" @checked(old('ip_enrichment_enabled', $settings['ip_enrichment_enabled']))>Queue intelligence enrichment for public IP addresses</label>
                        </div>
                    </section>
                </div>
            </div>
            <div class="ops-filter-actions"><button type="submit" class="ops-button ops-button--primary">Save system settings</button></div>
        </form>
    </div>
</x-layouts.app>
