<x-layouts.app title="Incident management - INTSEC">
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

            --sev-normal: #5B7A99;
            --sev-warning: #D9A441;
            --sev-suspicious: #D9823F;
            --sev-high: #D9593F;
            --sev-critical: #D93F4E;

            color: var(--text);
        }
        .intsec-scope .font-mono-plex { font-family: 'IBM Plex Mono', ui-monospace, monospace; }

        .intsec-scope details.create summary { list-style: none; cursor: pointer; }
        .intsec-scope details.create summary::-webkit-details-marker { display: none; }
        .intsec-scope details.create[open] summary { border-bottom: 1px solid var(--border); }

        .intsec-scope .sev { display: inline-flex; align-items: center; gap: 7px; font-weight: 500; }
        .intsec-scope .sev::before { content: ""; width: 7px; height: 7px; border-radius: 9999px; background: var(--sev); flex: none; }

        .intsec-scope .status-dot { display: inline-flex; align-items: center; gap: 6px; }
        .intsec-scope .status-dot::before { content: ""; width: 6px; height: 6px; border-radius: 9999px; }
        .intsec-scope .status-active { color: var(--accent); font-weight: 500; }
        .intsec-scope .status-active::before { background: var(--accent); }
        .intsec-scope .status-quiet { color: var(--text-muted); }
        .intsec-scope .status-quiet::before { background: var(--text-muted); }

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

        {{-- Wayfinding + title --}}
        <div>
            <p class="font-mono-plex text-xs" style="color: var(--text-muted);">
                <span style="color: var(--accent);">intsec</span> / incidents
            </p>
            <h1 class="mt-2 text-3xl font-semibold" style="color: var(--text); letter-spacing: -0.01em;">
                Incident management
            </h1>
            <p class="mt-2 text-sm" style="color: var(--text-muted);">Open incidents</p>
        </div>

        {{-- Telemetry strip: current queue state, the primary at-a-glance signal --}}
        @php($stats = [
            ['label' => 'Open', 'value' => $summary['open'], 'tone' => 'var(--sev-warning)'],
            ['label' => 'Investigating', 'value' => $summary['investigating'], 'tone' => 'var(--text)'],
            ['label' => 'High / critical', 'value' => $summary['high_critical'], 'tone' => 'var(--sev-critical)'],
            ['label' => 'Contained', 'value' => $summary['contained'], 'tone' => 'var(--text)'],
            ['label' => 'Resolved', 'value' => $summary['resolved'], 'tone' => 'var(--text)'],
        ])
        <div class="flex flex-wrap" style="border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
            @foreach ($stats as $i => $stat)
                <div class="flex-1 min-w-[150px] px-6 py-5" style="{{ $i > 0 ? 'border-left: 1px solid var(--border);' : '' }}">
                    <p class="font-mono-plex text-[26px] font-medium" style="color: {{ $stat['tone'] }};">{{ $stat['value'] }}</p>
                    <p class="mt-1 text-sm" style="color: var(--text-muted);">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="flex items-center justify-between">
            <div></div>
            <button type="button" id="open-incident-modal" class="inline-flex items-center gap-2 rounded-md border border-cyan-400/50 bg-cyan-400/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:bg-cyan-400/20">
                <span class="text-lg leading-none">+</span>
                New incident
            </button>
        </div>

        <div id="incident-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-zinc-950/80 p-4 backdrop-blur-sm">
            <div class="w-full max-w-4xl rounded-xl border border-zinc-800 bg-zinc-950 shadow-2xl shadow-cyan-950/20">
                <div class="flex items-center justify-between border-b border-zinc-800 px-5 py-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-[0.2em] text-cyan-300">Create incident</p>
                        <h2 class="mt-1 text-xl font-semibold text-white">New incident</h2>
                    </div>
                    <button type="button" data-close-incident-modal class="rounded-md border border-zinc-700 px-2.5 py-1.5 text-sm text-zinc-300 hover:border-zinc-600 hover:text-white">Close</button>
                </div>

                <form method="POST" action="{{ route('incidents.store') }}" class="space-y-5 p-5">
                    @csrf
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <label class="block text-xs font-medium uppercase tracking-[0.15em] text-zinc-400">
                            Title
                            <input type="text" name="title" required class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-500 focus:border-cyan-400 focus:outline-none">
                        </label>
                        <label class="block text-xs font-medium uppercase tracking-[0.15em] text-zinc-400">
                            Type
                            <input type="text" name="incident_type" value="authentication" required class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100 focus:border-cyan-400 focus:outline-none">
                        </label>
                        <label class="block text-xs font-medium uppercase tracking-[0.15em] text-zinc-400">
                            Severity
                            <select name="severity" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100 focus:border-cyan-400 focus:outline-none">
                                @foreach (['Normal', 'Warning', 'Suspicious', 'High', 'Critical'] as $level)
                                    <option value="{{ $level }}">{{ $level }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-xs font-medium uppercase tracking-[0.15em] text-zinc-400">
                            Source IP
                            <input type="text" name="source_ip" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-500 focus:border-cyan-400 focus:outline-none">
                        </label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-xs font-medium uppercase tracking-[0.15em] text-zinc-400">
                            Detection reason
                            <input type="text" name="detection_reason" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-500 focus:border-cyan-400 focus:outline-none">
                        </label>
                        <label class="block text-xs font-medium uppercase tracking-[0.15em] text-zinc-400">
                            Target user
                            <select name="user_id" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100 focus:border-cyan-400 focus:outline-none">
                                <option value="">None</option>
                                @foreach (\App\Models\User::orderBy('name')->get() as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <label class="block text-xs font-medium uppercase tracking-[0.15em] text-zinc-400">
                        Description
                        <textarea name="description" rows="4" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-500 focus:border-cyan-400 focus:outline-none"></textarea>
                    </label>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" data-close-incident-modal class="rounded-md border border-zinc-700 px-4 py-2 text-sm font-medium text-zinc-200 hover:border-zinc-600 hover:text-white">Cancel</button>
                        <button type="submit" class="rounded-md bg-cyan-400 px-4 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-cyan-300">Save incident</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('incidents.index') }}" class="flex flex-wrap gap-2.5">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search incidents"
                class="flex-1 min-w-[200px] rounded-md px-3 py-2 text-sm"
                style="background: var(--panel); border: 1px solid var(--border); color: var(--text);">
            <select name="incident_type" class="rounded-md px-3 py-2 text-sm"
                style="background: var(--panel); border: 1px solid var(--border); color: var(--text);">
                <option value="">Type</option>
                <option value="authentication" {{ request('incident_type') === 'authentication' ? 'selected' : '' }}>Authentication</option>
                <option value="authorization" {{ request('incident_type') === 'authorization' ? 'selected' : '' }}>Authorization</option>
                <option value="ip_activity" {{ request('incident_type') === 'ip_activity' ? 'selected' : '' }}>IP activity</option>
            </select>
            <select name="severity" class="rounded-md px-3 py-2 text-sm"
                style="background: var(--panel); border: 1px solid var(--border); color: var(--text);">
                <option value="">Severity</option>
                @foreach (['Normal','Warning','Suspicious','High','Critical'] as $level)
                    <option value="{{ $level }}" {{ request('severity') === $level ? 'selected' : '' }}>{{ $level }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-md px-3 py-2 text-sm"
                style="background: var(--panel); border: 1px solid var(--border); color: var(--text);">
                <option value="">Status</option>
                @foreach (['open','investigating','contained','resolved','false_positive'] as $status)
                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
            <input type="date" name="from_date" value="{{ request('from_date') }}" class="rounded-md px-3 py-2 text-sm"
                style="background: var(--panel); border: 1px solid var(--border); color: var(--text);">
            <input type="date" name="to_date" value="{{ request('to_date') }}" class="rounded-md px-3 py-2 text-sm"
                style="background: var(--panel); border: 1px solid var(--border); color: var(--text);">
            <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold"
                style="background: transparent; border: 1px solid var(--accent); color: var(--accent);">
                Apply
            </button>
        </form>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('incident-modal');
                const openBtn = document.getElementById('open-incident-modal');
                const closeButtons = document.querySelectorAll('[data-close-incident-modal]');

                const openModal = () => {
                    if (!modal) return;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                };

                const closeModal = () => {
                    if (!modal) return;
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                };

                openBtn?.addEventListener('click', openModal);
                closeButtons.forEach((button) => button.addEventListener('click', closeModal));

                modal?.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        closeModal();
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                        closeModal();
                    }
                });
            });
        </script>

        {{-- Incident log --}}
        <div class="rounded-lg overflow-hidden" style="border: 1px solid var(--border);">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" style="min-width: 1080px; border-collapse: collapse;">
                    <thead>
                        <tr style="background: var(--panel); border-bottom: 1px solid var(--border);">
                            <th class="px-3.5 py-3 font-medium text-xs whitespace-nowrap" style="color: var(--text-muted);">Incident</th>
                            <th class="px-3.5 py-3 font-medium text-xs whitespace-nowrap" style="color: var(--text-muted);">Title</th>
                            <th class="px-3.5 py-3 font-medium text-xs whitespace-nowrap" style="color: var(--text-muted);">Type</th>
                            <th class="px-3.5 py-3 font-medium text-xs whitespace-nowrap" style="color: var(--text-muted);">Severity</th>
                            <th class="px-3.5 py-3 font-medium text-xs whitespace-nowrap" style="color: var(--text-muted);">Source IP</th>
                            <th class="px-3.5 py-3 font-medium text-xs whitespace-nowrap" style="color: var(--text-muted);">Target user</th>
                            <th class="px-3.5 py-3 font-medium text-xs whitespace-nowrap" style="color: var(--text-muted);">Status</th>
                            <th class="px-3.5 py-3 font-medium text-xs whitespace-nowrap" style="color: var(--text-muted);">Assigned to</th>
                            <th class="px-3.5 py-3 font-medium text-xs whitespace-nowrap" style="color: var(--text-muted);">First detected</th>
                            <th class="px-3.5 py-3 font-medium text-xs whitespace-nowrap" style="color: var(--text-muted);">Last detected</th>
                            <th class="px-3.5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php($severityColor = [
                            'Normal' => 'var(--sev-normal)',
                            'Warning' => 'var(--sev-warning)',
                            'Suspicious' => 'var(--sev-suspicious)',
                            'High' => 'var(--sev-high)',
                            'Critical' => 'var(--sev-critical)',
                        ])
                        @php($unresolvedStatuses = ['open', 'investigating'])
                        @forelse ($incidents as $incident)
                            @php($sevVar = $severityColor[$incident->severity] ?? 'var(--sev-normal)')
                            <tr style="border-bottom: 1px solid var(--border);"
                                onmouseover="this.style.background='var(--panel-raised)'"
                                onmouseout="this.style.background='transparent'">
                                <td class="font-mono-plex px-3.5 py-3" style="border-left: 3px solid {{ $sevVar }}; color: var(--text);">
                                    {{ $incident->incident_id }}
                                </td>
                                <td class="px-3.5 py-3">
                                    <a href="{{ route('incidents.show', $incident) }}" class="font-medium hover:underline" style="color: var(--text);">
                                        {{ $incident->title }}
                                    </a>
                                </td>
                                <td class="px-3.5 py-3 capitalize" style="color: var(--text-muted);">{{ str_replace('_', ' ', $incident->incident_type) }}</td>
                                <td class="px-3.5 py-3">
                                    <span class="sev" style="--sev: {{ $sevVar }}; color: {{ $sevVar }};">{{ $incident->severity }}</span>
                                </td>
                                <td class="font-mono-plex px-3.5 py-3" style="color: var(--text);">{{ $incident->source_ip ?: '—' }}</td>
                                <td class="px-3.5 py-3" style="color: var(--text);">{{ $incident->user?->name ?? '—' }}</td>
                                <td class="px-3.5 py-3">
                                    <span class="status-dot text-sm {{ in_array($incident->status, $unresolvedStatuses) ? 'status-active' : 'status-quiet' }}">
                                        {{ ucfirst(str_replace('_', ' ', $incident->status)) }}
                                    </span>
                                </td>
                                <td class="px-3.5 py-3" style="color: {{ $incident->assignedAdministrator ? 'var(--text)' : 'var(--text-muted)' }};">
                                    {{ $incident->assignedAdministrator?->name ?? 'Unassigned' }}
                                </td>
                                <td class="font-mono-plex px-3.5 py-3" style="color: var(--text-muted);">{{ $incident->first_detected_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="font-mono-plex px-3.5 py-3" style="color: var(--text-muted);">{{ $incident->last_detected_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="px-3.5 py-3">
                                    <a href="{{ route('incidents.show', $incident) }}" class="font-medium hover:underline" style="color: var(--accent);">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-14 text-center" style="color: var(--text-muted);">
                                    No incidents match the current filter set.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end">
            {{ $incidents->links() }}
        </div>
    </div>
</x-layouts.app>