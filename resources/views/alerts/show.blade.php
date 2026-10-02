<x-layouts.app title="Alert {{ $alert->alert_id }} - INTSEC">
    <div class="space-y-6">
        @php($severityColor = ['Critical' => 'border-red-500/40 bg-red-500/10 text-red-200', 'High' => 'border-orange-500/40 bg-orange-500/10 text-orange-200', 'Suspicious' => 'border-amber-500/40 bg-amber-500/10 text-amber-200', 'Warning' => 'border-yellow-500/40 bg-yellow-500/10 text-yellow-200', 'Normal' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200'][$alert->severity] ?? 'border-zinc-700 text-zinc-300')
        @php($statusColor = ['new' => 'border-cyan-500/40 bg-cyan-500/10 text-cyan-200', 'acknowledged' => 'border-blue-500/40 bg-blue-500/10 text-blue-200', 'investigating' => 'border-amber-500/40 bg-amber-500/10 text-amber-200', 'resolved' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200', 'dismissed' => 'border-zinc-700 text-zinc-400'][$alert->status] ?? 'border-zinc-700 text-zinc-300')
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.2em] text-cyan-300">{{ config('intsec.sources.'.$alert->source, $alert->source) }} · Security Alert</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">{{ $alert->title }}</h1>
                <p class="mt-1 font-mono text-sm text-cyan-300">{{ $alert->alert_id }}</p>
            </div>
            <div class="flex gap-3">
                <span class="inline-flex rounded-full border px-3 py-1.5 text-xs {{ $severityColor }}">{{ $alert->severity }}</span>
                <span class="inline-flex rounded-full border px-3 py-1.5 text-xs {{ $statusColor }}">{{ ucfirst($alert->status) }}</span>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1.4fr_0.6fr]">
            <div class="space-y-6">
                <div class="rounded-xl border p-5 bg-zinc-950/60">
                    <h2 class="text-lg font-semibold text-white">Detection</h2>
                    <dl class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <dt class="text-xs text-zinc-500">Type</dt>
                            <dd class="mt-1 text-zinc-200">{{ $alert->typeLabel() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500">Occurred</dt>
                            <dd class="mt-1 text-zinc-200">{{ $alert->occurred_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4">
                        <h3 class="text-sm font-semibold text-zinc-500">Description</h3>
                        <p class="mt-2 text-zinc-300 whitespace-pre-wrap">{{ $alert->description ?? '—' }}</p>
                    </div>
                </div>

                <div class="rounded-xl border p-5 bg-zinc-950/60">
                    <h2 class="text-lg font-semibold text-white">Source</h2>
                    <dl class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <dt class="text-xs text-zinc-500">Source IP</dt>
                            <dd class="mt-1 text-zinc-200">{{ $alert->source_ip ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500">IP Management decision</dt>
                            <dd class="mt-1 text-zinc-200">
                                @if (($ipDecision ?? null) === 'blocked')
                                    <span class="inline-flex rounded-full border border-rose-500/40 bg-rose-500/10 px-2.5 py-1 text-xs text-rose-200">Blocked</span>
                                @elseif (($ipDecision ?? null) === 'allowed')
                                    <span class="inline-flex rounded-full border border-emerald-500/40 bg-emerald-500/10 px-2.5 py-1 text-xs text-emerald-200">Allowed</span>
                                @elseif ($alert->source_ip)
                                    <span class="text-xs text-zinc-400">No matching rule</span>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500">Related event</dt>
                            <dd class="mt-1 text-zinc-200">@if($alert->securityEvent)<a href="{{ route('admin.audit-logs') }}">View event</a>@else — @endif</dd>
                        </div>
                    </dl>
                    @if (($ipRules ?? collect())->isNotEmpty())
                        <div class="mt-4 space-y-1 text-xs text-zinc-400">
                            @foreach ($ipRules as $rule)
                                <p>Rule #{{ $rule->id }}: {{ $rule->ip_address }} ({{ ucfirst($rule->action) }}, {{ ucfirst($rule->source) }})</p>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <aside class="space-y-6">
                <div class="rounded-xl border p-5 bg-zinc-950/60">
                    <h2 class="text-lg font-semibold text-white">Actions</h2>
                    <form method="POST" action="{{ route('alerts.acknowledge', $alert) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="w-full rounded-md bg-cyan-400 px-4 py-2 text-sm font-semibold text-zinc-950">Acknowledge</button>
                    </form>

                    @if ($alert->incident)
                        <a href="{{ route('incidents.show', $alert->incident) }}" class="mt-3 block w-full rounded-md border border-cyan-500/40 px-4 py-2 text-center text-sm text-cyan-200">View Incident {{ $alert->incident->incident_id }}</a>
                    @else
                        <form method="POST" action="{{ route('alerts.create-incident', $alert) }}" class="mt-3">
                            @csrf
                            <button type="submit" class="w-full rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200">Create Incident</button>
                        </form>
                        <form method="POST" action="{{ route('alerts.attach-incident', $alert) }}" class="mt-3">
                            @csrf
                            <select name="incident_id" class="w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100" required>
                                <option value="">Attach to open incident</option>
                                @foreach ($openIncidents as $incident)
                                    <option value="{{ $incident->id }}">{{ $incident->incident_id }} - {{ $incident->title }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="mt-2 w-full rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200">Attach Incident</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('alerts.assign', $alert) }}" class="mt-3">
                        @csrf
                        <select name="assigned_to" class="w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100">
                            @foreach ($admins as $administrator)
                                <option value="{{ $administrator->id }}" {{ $alert->assigned_to === $administrator->id ? 'selected' : '' }}>{{ $administrator->id === auth()->id() ? 'Assign to me' : $administrator->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="mt-2 w-full rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200">Assign alert</button>
                    </form>

                    @if ($alert->source_ip)
                        <form method="POST" action="{{ route('alerts.block-ip', $alert) }}" class="mt-3" onsubmit="return confirm('Block {{ $alert->source_ip }}? A BLOCK rule will be created in IP Management.');">
                            @csrf
                            <input type="hidden" name="expiration" value="permanent">
                            <button type="submit" class="w-full rounded-md border border-rose-500/40 bg-rose-500/10 px-4 py-2 text-sm font-semibold text-rose-200">Block IP ({{ $alert->source_ip }})</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('alerts.status.update', $alert) }}" class="mt-3">
                        @csrf
                        @method('PATCH')
                        <label class="block text-sm text-zinc-300">Status
                            <select name="status" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                                @foreach (['new','acknowledged','investigating','resolved','dismissed'] as $status)
                                    <option value="{{ $status }}" {{ $alert->status === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm text-zinc-300">Reason
                            <input type="text" name="reason" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                        </label>
                        <button type="submit" class="w-full rounded-md border border-cyan-500/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 mt-2">Update status</button>
                    </form>

                    <form method="POST" action="{{ route('alerts.severity.update', $alert) }}" class="mt-3">
                        @csrf @method('PATCH')
                        <select name="severity" class="w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100">
                            @foreach (['Normal','Warning','Suspicious','High','Critical'] as $severity)
                                <option value="{{ $severity }}" {{ $alert->severity === $severity ? 'selected' : '' }}>{{ $severity }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="mt-2 w-full rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200">Update severity</button>
                    </form>

                    <form method="POST" action="{{ route('alerts.false-positive', $alert) }}" class="mt-3">
                        @csrf
                        <input name="reason" required maxlength="500" placeholder="False-positive reason" class="w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100">
                        <button type="submit" class="mt-2 w-full rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200">Mark false positive</button>
                    </form>
                </div>

                <div class="rounded-xl border p-5 bg-zinc-950/60">
                    <h2 class="text-lg font-semibold text-white">Metadata</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        @forelse ($alert->metadata ?? [] as $key => $value)
                            <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ str_replace('_', ' ', $key) }}</dt><dd class="text-right text-zinc-200">{{ is_array($value) ? implode(', ', $value) : $value }}</dd></div>
                        @empty
                            <p class="text-sm text-zinc-500">No additional metadata.</p>
                        @endforelse
                    </dl>
                </div>
            </aside>
                </div>

                <div class="rounded-xl border p-5 bg-zinc-950/60">
                    <h2 class="text-lg font-semibold text-white">Investigation remarks</h2>
                    <form method="POST" action="{{ route('alerts.remarks.store', $alert) }}" class="mt-4">
                        @csrf
                        <textarea name="remark" required maxlength="2000" rows="3" class="w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100" placeholder="Add an investigation remark"></textarea>
                        <button type="submit" class="mt-2 rounded-md border border-cyan-500/40 px-4 py-2 text-sm text-cyan-200">Add remark</button>
                    </form>
                    <div class="mt-4 divide-y divide-zinc-800">
                        @forelse ($alert->remarks as $remark)
                            <div class="py-3"><p class="whitespace-pre-wrap text-sm text-zinc-300">{{ $remark->remark }}</p><p class="mt-1 text-xs text-zinc-500">{{ $remark->author?->name ?? 'System' }} · {{ $remark->created_at->diffForHumans() }}</p></div>
                        @empty
                            <p class="py-3 text-sm text-zinc-500">No investigation remarks yet.</p>
                        @endforelse
                    </div>
                </div>

                @if ($relatedAlerts->isNotEmpty())
                    <div class="rounded-xl border p-5 bg-zinc-950/60">
                        <h2 class="text-lg font-semibold text-white">Related alerts</h2>
                        <div class="mt-3 divide-y divide-zinc-800">
                            @foreach ($relatedAlerts as $related)
                                <a href="{{ route('alerts.show', $related) }}" class="block py-3 text-sm text-zinc-300 hover:text-cyan-300">{{ $related->typeLabel() }} · {{ $related->title }} <span class="text-xs text-zinc-500">{{ $related->occurred_at?->diffForHumans() }}</span></a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
</x-layouts.app>
