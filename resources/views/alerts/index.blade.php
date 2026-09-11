<x-layouts.app title="Security Alerts - INTSEC">
    <div class="space-y-6">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.2em] text-cyan-300">Security</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Security Alerts</h1>
            <p class="mt-2 text-sm text-zinc-400">Centralized view of detected security alerts.</p>
        </div>

        @php($stats = [
            ['label' => 'New', 'value' => $summary['new'], 'tone' => 'var(--sev-warning)'],
            ['label' => 'Critical', 'value' => $summary['critical'], 'tone' => 'var(--sev-critical)'],
            ['label' => 'High', 'value' => $summary['high'], 'tone' => 'var(--sev-high)'],
            ['label' => 'Acknowledged', 'value' => $summary['acknowledged'], 'tone' => 'var(--text)'],
            ['label' => 'Investigating', 'value' => $summary['investigating'], 'tone' => 'var(--text)'],
        ])

        <div class="flex flex-wrap">
            @foreach ($stats as $i => $stat)
                <div class="flex-1 min-w-[150px] px-6 py-5" style="{{ $i > 0 ? 'border-left: 1px solid rgba(255,255,255,0.04);' : '' }}">
                    <p class="font-mono-plex text-[26px] font-medium" style="color: {{ $stat['tone'] }};">{{ $stat['value'] }}</p>
                    <p class="mt-1 text-sm" style="color: var(--text-muted);">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </div>

        <form method="GET" action="{{ route('alerts.index') }}" class="flex flex-wrap gap-2.5">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search alerts"
                class="flex-1 min-w-[200px] rounded-md px-3 py-2 text-sm" style="background: #0f1724; border: 1px solid rgba(255,255,255,0.04); color: #e7edf3;">
            <select name="severity" class="rounded-md px-3 py-2 text-sm">
                <option value="">Severity</option>
                @foreach (['Normal','Warning','Suspicious','High','Critical'] as $level)
                    <option value="{{ $level }}" {{ request('severity') === $level ? 'selected' : '' }}>{{ $level }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-md px-3 py-2 text-sm">
                <option value="">Status</option>
                @foreach (['new','acknowledged','investigating','resolved','dismissed'] as $status)
                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <input type="text" name="source_ip" value="{{ request('source_ip') }}" placeholder="Source IP" class="rounded-md px-3 py-2 text-sm">
            <select name="alert_type" class="rounded-md px-3 py-2 text-sm">
                <option value="">Detection type</option>
                @foreach (['brute_force' => 'Brute-force login attempts', 'repeated_ip_activity' => 'Repeated IP activity'] as $value => $label)
                    <option value="{{ $value }}" {{ request('alert_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <select name="assigned_to" class="rounded-md px-3 py-2 text-sm">
                <option value="">Assignee</option>
                @foreach ($admins as $admin)
                    <option value="{{ $admin->id }}" {{ (string) request('assigned_to') === (string) $admin->id ? 'selected' : '' }}>{{ $admin->name }}</option>
                @endforeach
            </select>
            <select name="range" class="rounded-md px-3 py-2 text-sm">
                <option value="">Any time</option>
                @foreach (['today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days'] as $value => $label)
                    <option value="{{ $value }}" {{ request('range') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <input type="date" name="from_date" value="{{ request('from_date') }}" class="rounded-md px-3 py-2 text-sm">
            <input type="date" name="to_date" value="{{ request('to_date') }}" class="rounded-md px-3 py-2 text-sm">
            <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold" style="background: transparent; border: 1px solid #2dd4bf; color: #2dd4bf;">Apply</button>
            <a href="{{ route('alerts.export', request()->query()) }}" class="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-300">Export CSV</a>
        </form>

        <form id="bulk-alerts" method="POST" action="{{ route('alerts.bulk') }}" class="flex items-center gap-2">
            @csrf
            <select name="bulk_action" class="rounded-md px-3 py-2 text-sm">
                <option value="acknowledge">Acknowledge selected</option>
                <option value="dismiss">Dismiss selected</option>
            </select>
            <button type="submit" class="rounded-md border border-cyan-500/40 px-3 py-2 text-sm text-cyan-200">Apply to selected</button>
        </form>

        <div class="rounded-lg overflow-hidden" style="border: 1px solid rgba(255,255,255,0.04);">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" style="min-width: 1000px;">
                    <thead>
                        <tr style="background: #0b1220; border-bottom: 1px solid rgba(255,255,255,0.04);">
                            <th class="px-3.5 py-3"><input type="checkbox" onclick="document.querySelectorAll('.alert-select').forEach((input) => input.checked = this.checked)"></th>
                            <th class="px-3.5 py-3 text-xs text-zinc-400">Alert</th>
                            <th class="px-3.5 py-3 text-xs text-zinc-400">Title</th>
                            <th class="px-3.5 py-3 text-xs text-zinc-400">Detection</th>
                            <th class="px-3.5 py-3 text-xs text-zinc-400">Severity</th>
                            <th class="px-3.5 py-3 text-xs text-zinc-400">Status</th>
                            <th class="px-3.5 py-3 text-xs text-zinc-400">Source IP</th>
                            <th class="px-3.5 py-3 text-xs text-zinc-400">Target</th>
                            <th class="px-3.5 py-3 text-xs text-zinc-400">Assignee</th>
                            <th class="px-3.5 py-3 text-xs text-zinc-400">Incident</th>
                            <th class="px-3.5 py-3 text-xs"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($alerts as $alert)
                            @php($severityColor = ['Critical' => 'text-red-300 border-red-500/40 bg-red-500/10', 'High' => 'text-orange-300 border-orange-500/40 bg-orange-500/10', 'Suspicious' => 'text-amber-300 border-amber-500/40 bg-amber-500/10', 'Warning' => 'text-yellow-300 border-yellow-500/40 bg-yellow-500/10', 'Normal' => 'text-emerald-300 border-emerald-500/40 bg-emerald-500/10'][$alert->severity] ?? 'text-zinc-300 border-zinc-700')
                            @php($statusColor = ['new' => 'text-cyan-200', 'acknowledged' => 'text-blue-200', 'investigating' => 'text-amber-200', 'resolved' => 'text-emerald-200', 'dismissed' => 'text-zinc-400'][$alert->status] ?? 'text-zinc-300')
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.03);">
                                <td class="px-3.5 py-3"><input form="bulk-alerts" class="alert-select" type="checkbox" name="alert_ids[]" value="{{ $alert->id }}"></td>
                                <td class="px-3.5 py-3 font-mono">{{ $alert->alert_id }}</td>
                                <td class="px-3.5 py-3"><a href="{{ route('alerts.show', $alert) }}" class="hover:underline">{{ $alert->title }}</a></td>
                                <td class="px-3.5 py-3 text-xs text-zinc-300">{{ $alert->typeLabel() }}</td>
                                <td class="px-3.5 py-3"><span class="rounded border px-2 py-1 text-xs {{ $severityColor }}">{{ $alert->severity }}</span></td>
                                <td class="px-3.5 py-3 {{ $statusColor }}">{{ ucfirst($alert->status) }}</td>
                                <td class="px-3.5 py-3">{{ $alert->source_ip ?? '—' }}</td>
                                <td class="px-3.5 py-3">{{ $alert->securityEvent?->user?->name ?? '—' }}</td>
                                <td class="px-3.5 py-3 text-xs text-zinc-300">{{ $alert->assignedAdministrator?->name ?? 'Unassigned' }}</td>
                                <td class="px-3.5 py-3">@if($alert->incident) <a href="{{ route('incidents.show', $alert->incident) }}">{{ $alert->incident->incident_id }}</a> @else — @endif</td>
                                <td class="px-3.5 py-3"><a href="{{ route('alerts.show', $alert) }}" class="text-cyan-400">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="px-4 py-14 text-center text-zinc-400">No alerts match the current filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end">{{ $alerts->links() }}</div>
    </div>
</x-layouts.app>
