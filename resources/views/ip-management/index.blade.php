<x-layouts.app title="IP Management - INTSEC" realtime-entities="blocked_ip">
    <style>
        .intsec-scope {
            --ink: #0A0E13;
            --panel: #12181F;
            --panel-raised: #1B232C;
            --border: #232D38;
            --text: #E7EDF3;
            --text-muted: #8592A0;
            --accent: #E8A33D;
            --accent-ink: #0A0E13;
            --sev-warning: #D9A441;
            --sev-high: #D9593F;
            --sev-critical: #D93F4E;
            color: var(--text);
        }
        .intsec-scope .font-mono-plex { font-family: 'IBM Plex Mono', ui-monospace, monospace; }
        .intsec-scope input:focus-visible,
        .intsec-scope select:focus-visible,
        .intsec-scope textarea:focus-visible,
        .intsec-scope button:focus-visible,
        .intsec-scope a:focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
        }
        @media (prefers-reduced-motion: reduce) {
            .intsec-scope * { transition: none !important; }
        }
    </style>

    <div class="intsec-scope space-y-7" style="background-color: var(--ink);">
        @if ($errors->any())
            <div class="rounded-md border border-red-700 bg-red-950 px-4 py-3 text-sm text-red-100">
                <p class="font-semibold">Rule was not saved.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('status') === 'rule-created')
            <div class="rounded-md border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-100">IP rule created and enforced.</div>
        @endif
        @if (session('status') === 'rule-updated')
            <div class="rounded-md border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-100">IP rule updated.</div>
        @endif
        @if (session('status') === 'rule-toggled')
            <div class="rounded-md border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-100">IP rule status changed.</div>
        @endif
        @if (session('status') === 'rule-deleted')
            <div class="rounded-md border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-100">IP rule deleted.</div>
        @endif
        @if (session('status') === 'alert-ip-blocked')
            <div class="rounded-md border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-100">Alert source IP is now blocked by IP Management.</div>
        @endif
        @if (session('status') === 'incident-ip-blocked')
            <div class="rounded-md border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-100">Incident source IP is now blocked by IP Management.</div>
        @endif

        <div>
            <p class="font-mono-plex text-xs" style="color: var(--text-muted);">
                <span style="color: var(--accent);">intsec</span> / ip-management
            </p>
            <h1 class="mt-2 text-3xl font-semibold" style="color: var(--text); letter-spacing: -0.01em;">IP Management</h1>
            <p class="mt-2 text-sm" style="color: var(--text-muted);">
                Centralized ALLOW / BLOCK enforcement layer. BLOCK rules take precedence over ALLOW rules (deny-wins).
                Allowlisted IPs remain subject to IDS detection and audit logging.
                Your current IP: <span class="font-mono">{{ $myIp }}</span>
            </p>
        </div>

        @php($stats = [
            ['label' => 'Total rules', 'value' => $summary['total'], 'tone' => 'var(--text)'],
            ['label' => 'Allowed IPs', 'value' => $summary['allowed'], 'tone' => 'var(--sev-warning)'],
            ['label' => 'Blocked IPs', 'value' => $summary['blocked'], 'tone' => 'var(--sev-critical)'],
            ['label' => 'Active rules', 'value' => $summary['active'], 'tone' => 'var(--text)'],
            ['label' => 'Expiring soon (24h)', 'value' => $summary['expiring_soon'], 'tone' => 'var(--sev-high)'],
        ])
        <div class="flex flex-wrap" style="border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
            @foreach ($stats as $i => $stat)
                <div class="flex-1 min-w-[150px] px-6 py-5" style="{{ $i > 0 ? 'border-left: 1px solid var(--border);' : '' }}">
                    <p class="font-mono-plex text-[26px] font-medium" style="color: {{ $stat['tone'] }};">{{ $stat['value'] }}</p>
                    <p class="mt-1 text-sm" style="color: var(--text-muted);">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </div>

        <form method="GET" action="{{ route('ip-management.index') }}" class="flex flex-wrap gap-2.5">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search IP, reason, name"
                class="flex-1 min-w-[200px] rounded-md px-3 py-2 text-sm" style="background: #0f1724; border: 1px solid rgba(255,255,255,0.04); color: #e7edf3;">
            <select name="action" class="rounded-md px-3 py-2 text-sm" style="background: #0f1724; border: 1px solid rgba(255,255,255,0.04); color: #e7edf3;">
                <option value="">Allow / Block</option>
                <option value="allow" {{ request('action') === 'allow' ? 'selected' : '' }}>Allow</option>
                <option value="block" {{ request('action') === 'block' ? 'selected' : '' }}>Block</option>
            </select>
            <select name="state" class="rounded-md px-3 py-2 text-sm" style="background: #0f1724; border: 1px solid rgba(255,255,255,0.04); color: #e7edf3;">
                <option value="">All states</option>
                <option value="active" {{ request('state') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="expired" {{ request('state') === 'expired' ? 'selected' : '' }}>Expired</option>
                <option value="disabled" {{ request('state') === 'disabled' ? 'selected' : '' }}>Disabled</option>
            </select>
            <select name="source" class="rounded-md px-3 py-2 text-sm" style="background: #0f1724; border: 1px solid rgba(255,255,255,0.04); color: #e7edf3;">
                <option value="">All sources</option>
                @foreach (['manual','automatic','alert','incident','system'] as $src)
                    <option value="{{ $src }}" {{ request('source') === $src ? 'selected' : '' }}>{{ ucfirst($src) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold" style="background: transparent; border: 1px solid #2dd4bf; color: #2dd4bf;">Apply</button>
            <button type="button" id="open-ip-rule-modal" class="rounded-md px-4 py-2 text-sm font-semibold" style="background: #2dd4bf; color: #06281f;">+ Add IP rule</button>
        </form>

        @if (session('self_block_warning') || $errors->has('ip_address') || $errors->has('action'))
            <div class="rounded-md border border-amber-700 bg-amber-950 px-4 py-3 text-sm text-amber-100">
                Warning: this would affect your own IP address ({{ $myIp }}). Re-submit with the self-block confirmation ticked to proceed.
            </div>
        @endif

        <div class="rounded-lg overflow-hidden" style="border: 1px solid rgba(255,255,255,0.04);">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" style="min-width: 1100px;">
                    <thead>
                        <tr style="background: #0b1220; border-bottom: 1px solid rgba(255,255,255,0.04);">
                            <th class="px-3.5 py-3 text-xs text-zinc-400">IP / CIDR</th>
                            <th class="px-3.5 py-3 text-xs text-zinc-400">Action</th>
                            <th class="px-3.5 py-3 text-xs text-zinc-400">Status</th>
                            <th class="px-3.5 py-3 text-xs text-zinc-400">Source</th>
                            <th class="px-3.5 py-3 text-xs text-zinc-400">Reason</th>
                            <th class="px-3.5 py-3 text-xs text-zinc-400">Intel</th>
                            <th class="px-3.5 py-3 text-xs text-zinc-400">Created by</th>
                            <th class="px-3.5 py-3 text-xs text-zinc-400">Expires</th>
                            <th class="px-3.5 py-3 text-xs text-zinc-400">Matches</th>
                            <th class="px-3.5 py-3 text-xs"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rules as $rule)
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.03);">
                                <td class="px-3.5 py-3 font-mono text-zinc-100">
                                    {{ $rule->ip_address }}
                                    @if ($rule->name)<div class="text-xs text-zinc-500">{{ $rule->name }}</div>@endif
                                </td>
                                <td class="px-3.5 py-3">
                                    @if ($rule->isAllowRule())
                                        <span class="inline-flex rounded-full border border-emerald-500/40 bg-emerald-500/10 px-2.5 py-1 text-xs text-emerald-200">Allow</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-rose-500/40 bg-rose-500/10 px-2.5 py-1 text-xs text-rose-200">Block</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-3 text-xs">
                                    @if ($rule->isExpired())
                                        <span class="text-zinc-500">Expired</span>
                                    @elseif (! $rule->is_enabled || $rule->status !== 'active')
                                        <span class="text-zinc-500">Disabled</span>
                                    @else
                                        <span class="text-emerald-300">Active</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-3 text-xs text-zinc-300">{{ ucfirst($rule->source) }}</td>
                                <td class="px-3.5 py-3 text-xs text-zinc-300" title="{{ $rule->description }}">{{ \Illuminate\Support\Str::limit($rule->reason ?? '—', 60) }}</td>
                                <td class="px-3.5 py-3 text-xs text-zinc-400">
                                    @php($info = $intel[$rule->id] ?? null)
                                    @if (is_array($info) && ($info['country'] ?? null))
                                        {{ $info['country'] }}{{ $info['city'] ? ' / '.$info['city'] : '' }}
                                        @if ($info['isp'] ?? $info['organization'] ?? null)<div class="text-zinc-500">{{ \Illuminate\Support\Str::limit($info['isp'] ?? $info['organization'], 30) }}</div>@endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3.5 py-3 text-xs text-zinc-300">{{ $rule->administrator?->name ?? 'System' }}</td>
                                <td class="px-3.5 py-3 text-xs text-zinc-300">{{ $rule->expires_at?->format('Y-m-d H:i') ?? 'Permanent' }}</td>
                                <td class="px-3.5 py-3 text-xs text-zinc-300">{{ $rule->match_count }}</td>
                                <td class="px-3.5 py-3">
                                    <div class="flex flex-wrap gap-1.5">
                                        <button type="button" data-edit-rule="{{ $rule->id }}" class="rounded border border-zinc-700 px-2 py-1 text-xs text-zinc-200 hover:border-cyan-500/50">Edit</button>
                                        <form method="POST" action="{{ route('ip-management.action', $rule) }}" onsubmit="return confirm('Switch {{ $rule->ip_address }} to {{ $rule->isAllowRule() ? 'BLOCK' : 'ALLOW' }}?');">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="action" value="{{ $rule->isAllowRule() ? 'block' : 'allow' }}">
                                            <button type="submit" class="rounded border border-zinc-700 px-2 py-1 text-xs text-zinc-200 hover:border-cyan-500/50">{{ $rule->isAllowRule() ? 'Block' : 'Allow' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('ip-management.toggle', $rule) }}" onsubmit="return confirm('{{ $rule->is_enabled ? 'Disable' : 'Enable' }} rule for {{ $rule->ip_address }}?');">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="enabled" value="{{ $rule->is_enabled ? 0 : 1 }}">
                                            <button type="submit" class="rounded border border-zinc-700 px-2 py-1 text-xs text-zinc-200 hover:border-cyan-500/50">{{ $rule->is_enabled ? 'Disable' : 'Enable' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('ip-management.destroy', $rule) }}" onsubmit="return confirm('Delete rule for {{ $rule->ip_address }}? History will be removed from enforcement but the audit log is kept.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded border border-red-700 px-2 py-1 text-xs text-red-300 hover:bg-red-950">Delete</button>
                                        </form>
                                    </div>
                                    @if ($rule->alert)<div class="mt-1 text-xs text-zinc-500">Alert: <a class="text-cyan-300 hover:underline" href="{{ route('alerts.show', $rule->alert) }}">{{ $rule->alert->alert_id }}</a></div>@endif
                                    @if ($rule->incident)<div class="mt-1 text-xs text-zinc-500">Incident: <a class="text-cyan-300 hover:underline" href="{{ route('incidents.show', $rule->incident) }}">{{ $rule->incident->incident_id }}</a></div>@endif
                                </td>
                            </tr>

                            {{-- Edit modal per rule --}}
                            <div id="edit-rule-{{ $rule->id }}" class="fixed inset-0 z-[1000] hidden items-center justify-center overflow-y-auto bg-zinc-950/80 p-4 backdrop-blur-sm">
                                <div class="my-8 w-full max-w-2xl rounded-xl border border-zinc-800 bg-zinc-950 shadow-2xl">
                                    <div class="flex items-center justify-between border-b border-zinc-800 px-5 py-4">
                                        <h2 class="text-lg font-semibold text-white">Edit rule: <span class="font-mono">{{ $rule->ip_address }}</span></h2>
                                        <button type="button" data-close-edit="{{ $rule->id }}" class="rounded-md border border-zinc-700 px-2.5 py-1.5 text-sm text-zinc-300">Close</button>
                                    </div>
                                    <form method="POST" action="{{ route('ip-management.update', $rule) }}" class="space-y-4 p-5">
                                        @csrf @method('PUT')
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <label class="block text-sm text-zinc-300">Action
                                                <select name="action" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                                                    <option value="block" {{ $rule->action === 'block' ? 'selected' : '' }}>Block</option>
                                                    <option value="allow" {{ $rule->action === 'allow' ? 'selected' : '' }}>Allow</option>
                                                </select>
                                            </label>
                                            <label class="block text-sm text-zinc-300">Name / reference
                                                <input type="text" name="name" value="{{ old('name', $rule->name) }}" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                                            </label>
                                        </div>
                                        <label class="block text-sm text-zinc-300">Reason
                                            <input type="text" name="reason" value="{{ old('reason', $rule->reason) }}" required class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                                        </label>
                                        <label class="block text-sm text-zinc-300">Description
                                            <textarea name="description" rows="3" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">{{ old('description', $rule->description) }}</textarea>
                                        </label>
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <label class="block text-sm text-zinc-300">Expiration
                                                <select name="expiration" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                                                    <option value="permanent" {{ $rule->expires_at ? '' : 'selected' }}>Permanent</option>
                                                    <option value="30m">30 minutes</option>
                                                    <option value="1h">1 hour</option>
                                                    <option value="24h">24 hours</option>
                                                    <option value="7d">7 days</option>
                                                    <option value="30d">30 days</option>
                                                    <option value="custom" {{ $rule->expires_at ? 'selected' : '' }}>Custom date</option>
                                                </select>
                                            </label>
                                            <label class="block text-sm text-zinc-300">Custom expiry
                                                <input type="datetime-local" name="expires_at" value="{{ $rule->expires_at?->format('Y-m-d\TH:i') }}" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                                            </label>
                                        </div>
                                        <div class="flex items-center gap-4 text-sm text-zinc-300">
                                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_enabled" value="1" {{ $rule->is_enabled ? 'checked' : '' }}> Enabled</label>
                                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="confirm_self_block" value="1"> Confirm self-block ({{ $myIp }})</label>
                                        </div>
                                        <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold" style="background: #2dd4bf; color: #06281f;">Save changes</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="10" class="px-4 py-14 text-center text-zinc-400">No IP rules match the current filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 border-t border-zinc-800 px-3 py-4 text-sm text-zinc-300">
            <div class="text-zinc-400">
                Showing {{ $rules->firstItem() ?? 0 }}-{{ $rules->lastItem() ?? 0 }} rules on this page
            </div>
            <div class="flex justify-end">
                {{ $rules->appends(request()->query())->links() }}
            </div>
        </div>
    </div>

    {{-- Add rule modal --}}
    <div id="ip-rule-modal" class="fixed inset-0 z-[1000] hidden items-center justify-center overflow-y-auto bg-zinc-950/80 p-4 backdrop-blur-sm">
        <div class="my-8 w-full max-w-2xl rounded-xl border border-zinc-800 bg-zinc-950 shadow-2xl">
            <div class="flex items-center justify-between border-b border-zinc-800 px-5 py-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-[0.2em] text-cyan-300">IP Management</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">Add IP rule</h2>
                </div>
                <button type="button" id="close-ip-rule-modal" class="rounded-md border border-zinc-700 px-2.5 py-1.5 text-sm text-zinc-300">Close</button>
            </div>
            <form method="POST" action="{{ route('ip-management.store') }}" class="space-y-4 p-5">
                @csrf
                <label class="block text-sm text-zinc-300">IP address or CIDR range
                    <input type="text" name="ip_address" value="{{ old('ip_address') }}" required placeholder="192.168.1.10, 2001:db8::1, or 10.0.0.0/8" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 font-mono text-zinc-100">
                </label>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm text-zinc-300">Action
                        <select name="action" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                            <option value="block" {{ old('action') === 'block' ? 'selected' : '' }}>Block</option>
                            <option value="allow" {{ old('action') === 'allow' ? 'selected' : '' }}>Allow</option>
                        </select>
                    </label>
                    <label class="block text-sm text-zinc-300">Name / reference
                        <input type="text" name="name" value="{{ old('name') }}" placeholder="Optional label" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                    </label>
                </div>
                <label class="block text-sm text-zinc-300">Reason
                    <input type="text" name="reason" value="{{ old('reason') }}" required placeholder="Why is this rule needed?" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                </label>
                <label class="block text-sm text-zinc-300">Description
                    <textarea name="description" rows="3" placeholder="Optional notes" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">{{ old('description') }}</textarea>
                </label>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm text-zinc-300">Expiration
                        <select name="expiration" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                            <option value="permanent">Permanent</option>
                            <option value="30m">30 minutes</option>
                            <option value="1h">1 hour</option>
                            <option value="24h">24 hours</option>
                            <option value="7d">7 days</option>
                            <option value="30d">30 days</option>
                            <option value="custom">Custom date</option>
                        </select>
                    </label>
                    <label class="block text-sm text-zinc-300">Custom expiry
                        <input type="datetime-local" name="expires_at" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                    </label>
                </div>
                <div class="flex flex-wrap items-center gap-4 text-sm text-zinc-300">
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_enabled" value="1" checked> Enabled</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="confirm_self_block" value="1"> I confirm blocking my own IP ({{ $myIp }}) if matched</label>
                </div>
                <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold" style="background: #2dd4bf; color: #06281f;">Create rule</button>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var modal = document.getElementById('ip-rule-modal');
            var open = document.getElementById('open-ip-rule-modal');
            var close = document.getElementById('close-ip-rule-modal');
            if (open && modal) { open.addEventListener('click', function () { modal.classList.remove('hidden'); modal.classList.add('flex'); }); }
            if (close && modal) { close.addEventListener('click', function () { modal.classList.add('hidden'); modal.classList.remove('flex'); }); }
            if (modal) { modal.addEventListener('click', function (e) { if (e.target === modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); } }); }
            document.querySelectorAll('[data-edit-rule]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var m = document.getElementById('edit-rule-' + btn.getAttribute('data-edit-rule'));
                    if (m) { m.classList.remove('hidden'); m.classList.add('flex'); }
                });
            });
            document.querySelectorAll('[data-close-edit]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var m = document.getElementById('edit-rule-' + btn.getAttribute('data-close-edit'));
                    if (m) { m.classList.add('hidden'); m.classList.remove('flex'); }
                });
            });
        })();
    </script>
</x-layouts.app>
