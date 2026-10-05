<x-layouts.app title="Alert {{ $alert->alert_id }} - INTSEC" wide realtime-entities="security_alert,incident,blocked_ip" :realtime-source="$alert->source">
    <div class="ops-page">
        <x-ui.page-header
            kicker="Security alert"
            :title="$alert->title"
            :description="$alert->typeLabel().' · '.$alert->alert_id"
        >
            <x-slot:context>
                <x-security.source-badge :source="$alert->source" />
                <x-security.severity-badge :severity="$alert->severity" />
                <x-security.status-badge :status="$alert->status" />
                <span class="ops-context-pill">{{ $alert->occurred_at?->format('M j, Y H:i:s T') ?? 'Time unavailable' }}</span>
            </x-slot:context>
            <x-slot:actions>
                <a class="ops-button ops-button--secondary" href="{{ route('alerts.index') }}">← Back to alerts</a>
                @if ($alert->incident)
                    <a class="ops-button ops-button--primary" href="{{ route('incidents.show', $alert->incident) }}">Open {{ $alert->incident->incident_id }}</a>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('status'))
            <div class="ops-banner ops-banner--success" role="status">Alert workflow updated successfully.</div>
        @endif
        @if ($errors->any())
            <div class="ops-banner ops-banner--danger" role="alert">
                <strong>The requested alert action could not be completed.</strong>
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="ops-section ops-split-layout">
            <div class="ops-stack">
                <section class="ops-panel ops-panel--padded" aria-labelledby="alert-detection-title">
                    <div class="mb-4">
                        <h2 id="alert-detection-title" class="ops-panel-title">Detection</h2>
                        <p class="ops-panel-description">Explainable rule context and persisted alert identity.</p>
                    </div>
                    <dl class="ops-detail-grid">
                        <div class="ops-detail-item"><dt>Alert ID</dt><dd class="ops-technical">{{ $alert->alert_id }}</dd></div>
                        <div class="ops-detail-item"><dt>Detection type</dt><dd>{{ $alert->typeLabel() }}</dd></div>
                        <div class="ops-detail-item"><dt>Rule key</dt><dd class="ops-technical">{{ $alert->rule_key ?: 'Not recorded' }}</dd></div>
                        <div class="ops-detail-item"><dt>Occurrences</dt><dd class="ops-numeric">{{ number_format($alert->occurrence_count ?? 1) }}</dd></div>
                        <div class="ops-detail-item"><dt>First detected</dt><dd>{{ $alert->first_detected_at?->format('M j, Y H:i:s T') ?? $alert->occurred_at?->format('M j, Y H:i:s T') ?? '—' }}</dd></div>
                        <div class="ops-detail-item"><dt>Last detected</dt><dd>{{ $alert->last_detected_at?->format('M j, Y H:i:s T') ?? $alert->occurred_at?->format('M j, Y H:i:s T') ?? '—' }}</dd></div>
                    </dl>
                    <div class="mt-5">
                        <h3 class="ops-label">Detection description</h3>
                        <p class="mt-2 whitespace-pre-wrap text-sm leading-7 text-slate-300 ops-wrap-anywhere">{{ $alert->description ?: 'No additional description was recorded.' }}</p>
                    </div>
                </section>

                <section class="ops-panel ops-panel--padded" aria-labelledby="alert-source-title">
                    <div class="mb-4">
                        <h2 id="alert-source-title" class="ops-panel-title">Source and related evidence</h2>
                        <p class="ops-panel-description">Observed source context and current application-level policy decision.</p>
                    </div>
                    <dl class="ops-detail-grid">
                        <div class="ops-detail-item"><dt>Source application</dt><dd><x-security.source-badge :source="$alert->source" /></dd></div>
                        <div class="ops-detail-item"><dt>Source IP</dt><dd class="ops-technical">{{ $alert->source_ip ?? 'Not recorded' }}</dd></div>
                        <div class="ops-detail-item">
                            <dt>IP policy decision</dt>
                            <dd>
                                @if (($ipDecision ?? null) === 'blocked')
                                    <span class="ops-badge ops-badge--red">Blocked</span>
                                @elseif (($ipDecision ?? null) === 'allowed')
                                    <span class="ops-badge ops-badge--green">Allowed</span>
                                @elseif ($alert->source_ip)
                                    <span class="ops-badge ops-badge--muted">No matching rule</span>
                                @else
                                    Not applicable
                                @endif
                            </dd>
                        </div>
                        <div class="ops-detail-item"><dt>Related security event</dt><dd>{{ $alert->securityEvent ? '#'.$alert->securityEvent->id.' · '.str($alert->securityEvent->event_type)->replace('_', ' ')->title() : 'Not linked' }}</dd></div>
                    </dl>
                    @if (($ipRules ?? collect())->isNotEmpty())
                        <div class="mt-4">
                            <h3 class="ops-label">Matching IP rules</h3>
                            <ul class="ops-list mt-2 rounded-lg border border-slate-800 px-3">
                                @foreach ($ipRules as $rule)
                                    <li class="ops-list-row">
                                        <span>
                                            <span class="ops-list-title ops-technical">{{ $rule->ip_address }}</span>
                                            <span class="ops-list-meta">Rule #{{ $rule->id }} · {{ ucfirst($rule->source) }} provenance</span>
                                        </span>
                                        <span class="ops-badge ops-badge--{{ $rule->isAllowRule() ? 'green' : 'red' }}">{{ ucfirst($rule->action) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>

                <section class="ops-panel ops-panel--padded" aria-labelledby="alert-remarks-title">
                    <div>
                        <h2 id="alert-remarks-title" class="ops-panel-title">Investigation remarks</h2>
                        <p class="ops-panel-description">Append-only analyst context for this alert.</p>
                    </div>
                    <form method="POST" action="{{ route('alerts.remarks.store', $alert) }}" class="mt-4">
                        @csrf
                        <label class="ops-field ops-field--full" for="alert-remark">
                            <span class="ops-label">New remark <span class="ops-required">*</span></span>
                            <textarea id="alert-remark" name="remark" required maxlength="2000" rows="4" placeholder="Document findings, decisions, or next steps.">{{ old('remark') }}</textarea>
                            @error('remark')<span class="ops-error">{{ $message }}</span>@enderror
                        </label>
                        <div class="ops-action-group mt-3 justify-start"><button type="submit" class="ops-button ops-button--primary">Add remark</button></div>
                    </form>
                    <div class="mt-5">
                        @forelse ($alert->remarks as $remark)
                            <article class="border-t border-slate-800 py-4">
                                <p class="whitespace-pre-wrap text-sm leading-6 text-slate-300 ops-wrap-anywhere">{{ $remark->remark }}</p>
                                <p class="ops-cell-meta">{{ $remark->author?->name ?? 'System' }} · {{ $remark->created_at->format('M j, Y H:i T') }}</p>
                            </article>
                        @empty
                            <x-ui.empty-state title="No investigation remarks yet" description="Add the first analyst note to establish investigation context." />
                        @endforelse
                    </div>
                </section>

                @if ($relatedAlerts->isNotEmpty())
                    <section class="ops-panel" aria-labelledby="related-alerts-title">
                        <div class="ops-panel-header">
                            <div>
                                <h2 id="related-alerts-title" class="ops-panel-title">Related alerts</h2>
                                <p class="ops-panel-description">Same source and IP observed during the previous 24 hours.</p>
                            </div>
                        </div>
                        <ul class="ops-list">
                            @foreach ($relatedAlerts as $related)
                                <li class="ops-list-row">
                                    <a href="{{ route('alerts.show', $related) }}">
                                        <span class="ops-list-title ops-wrap-anywhere">{{ $related->title }}</span>
                                        <span class="ops-list-meta">{{ $related->alert_id }} · {{ $related->typeLabel() }} · {{ $related->occurred_at?->diffForHumans() }}</span>
                                    </a>
                                    <x-security.severity-badge :severity="$related->severity" />
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section class="ops-panel ops-panel--padded" aria-labelledby="alert-metadata-title">
                    <h2 id="alert-metadata-title" class="ops-panel-title">Supplementary metadata</h2>
                    <p class="ops-panel-description">Safely rendered supporting evidence supplied by the detection workflow.</p>
                    @if (! empty($alert->metadata))
                        <dl class="ops-detail-grid mt-4">
                            @foreach ($alert->metadata as $key => $value)
                                <div class="ops-detail-item">
                                    <dt>{{ str($key)->replace('_', ' ')->title() }}</dt>
                                    <dd class="ops-technical">{{ is_scalar($value) || $value === null ? ($value ?? '—') : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @else
                        <x-ui.empty-state title="No supplementary metadata" description="This alert has no additional metadata fields." />
                    @endif
                </section>
            </div>

            <aside class="ops-stack" aria-label="Alert actions">
                <section class="ops-panel ops-panel--padded" aria-labelledby="alert-workflow-title">
                    <h2 id="alert-workflow-title" class="ops-panel-title">Analyst workflow</h2>
                    <p class="ops-panel-description">Ownership and state changes are audited.</p>

                    @if ($alert->status === 'new')
                        <form method="POST" action="{{ route('alerts.acknowledge', $alert) }}" class="mt-4">
                            @csrf
                            <button type="submit" class="ops-button ops-button--primary w-full">Acknowledge alert</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('alerts.assign', $alert) }}" class="mt-4">
                        @csrf
                        <label class="ops-field ops-field--full" for="alert-assigned-to">
                            <span class="ops-label">Assigned analyst</span>
                            <select id="alert-assigned-to" name="assigned_to" required>
                                @foreach ($admins as $administrator)
                                    <option value="{{ $administrator->id }}" @selected($alert->assigned_to === $administrator->id)>{{ $administrator->id === auth()->id() ? 'Me — '.$administrator->name : $administrator->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="ops-button ops-button--secondary mt-2 w-full">Update assignment</button>
                    </form>

                    <form method="POST" action="{{ route('alerts.status.update', $alert) }}" class="mt-4 border-t border-slate-800 pt-4">
                        @csrf @method('PATCH')
                        <label class="ops-field ops-field--full" for="alert-workflow-status">
                            <span class="ops-label">Workflow status</span>
                            <select id="alert-workflow-status" name="status">
                                @foreach (['new', 'acknowledged', 'investigating', 'resolved', 'dismissed'] as $status)
                                    <option value="{{ $status }}" @selected($alert->status === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="ops-field ops-field--full mt-3" for="alert-status-reason">
                            <span class="ops-label">Reason or context</span>
                            <input id="alert-status-reason" name="reason" maxlength="255" placeholder="Optional reason for this transition">
                        </label>
                        <button type="submit" class="ops-button ops-button--secondary mt-2 w-full">Update status</button>
                    </form>

                    <form method="POST" action="{{ route('alerts.severity.update', $alert) }}" class="mt-4 border-t border-slate-800 pt-4">
                        @csrf @method('PATCH')
                        <label class="ops-field ops-field--full" for="alert-severity-update">
                            <span class="ops-label">Severity classification</span>
                            <select id="alert-severity-update" name="severity">
                                @foreach (['Normal', 'Warning', 'Suspicious', 'High', 'Critical'] as $severity)
                                    <option value="{{ $severity }}" @selected($alert->severity === $severity)>{{ $severity }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="ops-button ops-button--secondary mt-2 w-full">Update severity</button>
                    </form>
                </section>

                <section class="ops-panel ops-panel--padded" aria-labelledby="alert-incident-title">
                    <h2 id="alert-incident-title" class="ops-panel-title">Incident relationship</h2>
                    @if ($alert->incident)
                        <p class="ops-description">This alert is attached to {{ $alert->incident->incident_id }}.</p>
                        <a href="{{ route('incidents.show', $alert->incident) }}" class="ops-button ops-button--primary mt-4 w-full">Open incident</a>
                    @else
                        <p class="ops-panel-description">Create a new case or attach this alert to an active incident from the same source.</p>
                        <form method="POST" action="{{ route('alerts.create-incident', $alert) }}" class="mt-4">
                            @csrf
                            <button type="submit" class="ops-button ops-button--primary w-full">Create incident</button>
                        </form>
                        @if ($openIncidents->isNotEmpty())
                            <form method="POST" action="{{ route('alerts.attach-incident', $alert) }}" class="mt-4 border-t border-slate-800 pt-4">
                                @csrf
                                <label class="ops-field ops-field--full" for="alert-incident-select">
                                    <span class="ops-label">Active incident</span>
                                    <select id="alert-incident-select" name="incident_id" required>
                                        <option value="">Choose an incident</option>
                                        @foreach ($openIncidents as $incident)
                                            <option value="{{ $incident->id }}">{{ $incident->incident_id }} — {{ $incident->title }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <button type="submit" class="ops-button ops-button--secondary mt-2 w-full">Attach to incident</button>
                            </form>
                        @endif
                    @endif
                </section>

                <section class="ops-panel ops-panel--padded ops-danger-zone" aria-labelledby="alert-sensitive-actions-title">
                    <h2 id="alert-sensitive-actions-title" class="ops-panel-title">Sensitive actions</h2>
                    <p class="ops-panel-description">These actions change enforcement or close the analyst workflow.</p>

                    @if ($alert->source_ip)
                        <form method="POST" action="{{ route('alerts.block-ip', $alert) }}" class="mt-4" onsubmit="return confirm('Create an application-level BLOCK rule for {{ $alert->source_ip }}?');">
                            @csrf
                            <label class="ops-field ops-field--full" for="alert-block-expiration">
                                <span class="ops-label">Block duration</span>
                                <select id="alert-block-expiration" name="expiration">
                                    <option value="24h">24 hours</option>
                                    <option value="7d">7 days</option>
                                    <option value="30d">30 days</option>
                                    <option value="permanent">Permanent</option>
                                </select>
                            </label>
                            <label class="ops-field ops-field--full mt-3" for="alert-block-reason">
                                <span class="ops-label">Reason</span>
                                <input id="alert-block-reason" name="reason" maxlength="500" value="Block requested from alert {{ $alert->alert_id }}">
                            </label>
                            <label class="ops-checkbox mt-3">
                                <input type="checkbox" name="confirm_self_block" value="1">
                                <span>Confirm even if this address matches my current administrator IP.</span>
                            </label>
                            <button type="submit" class="ops-button ops-button--danger mt-3 w-full">Block {{ $alert->source_ip }}</button>
                        </form>
                    @else
                        <p class="ops-banner ops-banner--warning">No source IP is available for enforcement.</p>
                    @endif

                    <form method="POST" action="{{ route('alerts.false-positive', $alert) }}" class="mt-5 border-t border-rose-900/50 pt-4" onsubmit="return confirm('Mark this alert as a false positive and dismiss it?');">
                        @csrf
                        <label class="ops-field ops-field--full" for="false-positive-reason">
                            <span class="ops-label">False-positive reason <span class="ops-required">*</span></span>
                            <textarea id="false-positive-reason" name="reason" required maxlength="500" rows="3" placeholder="Explain why this detection is not actionable."></textarea>
                        </label>
                        <button type="submit" class="ops-button ops-button--danger mt-3 w-full">Mark false positive</button>
                    </form>
                </section>
            </aside>
        </div>
    </div>
</x-layouts.app>
