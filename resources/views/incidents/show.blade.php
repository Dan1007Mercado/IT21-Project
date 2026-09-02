<x-layouts.app title="Incident {{ $incident->incident_id }} - INTSEC">
    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.2em] text-cyan-300">Incident overview</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">{{ $incident->title }}</h1>
                <p class="mt-2 font-mono text-sm text-cyan-300">{{ $incident->incident_id }}</p>
            </div>
            <div class="flex gap-3">
                <span class="inline-flex rounded-full border border-rose-500/40 bg-rose-500/10 px-3 py-1.5 text-xs font-medium text-rose-200">{{ $incident->severity }}</span>
                <span class="inline-flex rounded-full border border-cyan-500/40 bg-cyan-500/10 px-3 py-1.5 text-xs font-medium text-cyan-200">{{ ucfirst(str_replace('_', ' ', $incident->status)) }}</span>
            </div>
        </div>

        @if (session('status') === 'incident-created')
            <div class="rounded-md border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-100">Incident created successfully.</div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
            <div class="space-y-6">
                <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-5">
                    <h2 class="text-lg font-semibold text-white">Incident details</h2>
                    <dl class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase tracking-[0.2em] text-zinc-500">Type</dt>
                            <dd class="mt-1 text-zinc-200">{{ ucfirst(str_replace('_', ' ', $incident->incident_type)) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-[0.2em] text-zinc-500">Created</dt>
                            <dd class="mt-1 text-zinc-200">{{ $incident->created_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-[0.2em] text-zinc-500">Last detected</dt>
                            <dd class="mt-1 text-zinc-200">{{ $incident->last_detected_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-[0.2em] text-zinc-500">Assigned administrator</dt>
                            <dd class="mt-1 text-zinc-200">{{ $incident->assignedAdministrator?->name ?? 'Unassigned' }}</dd>
                        </div>
                    </dl>
                    <div class="mt-6">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-zinc-500">Description</h3>
                        <p class="mt-3 whitespace-pre-wrap text-zinc-300">{{ $incident->description ?: 'No description provided.' }}</p>
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-5">
                    <h2 class="text-lg font-semibold text-white">Incident update</h2>
                    <form method="POST" action="{{ route('incidents.update', $incident) }}" class="mt-4 space-y-4">
                        @csrf
                        @method('PUT')
                        <label class="block text-sm text-zinc-300">
                            Title
                            <input type="text" name="title" value="{{ $incident->title }}" required class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                        </label>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block text-sm text-zinc-300">
                                Type
                                <input type="text" name="incident_type" value="{{ $incident->incident_type }}" required class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                            </label>
                            <label class="block text-sm text-zinc-300">
                                Source IP
                                <input type="text" name="source_ip" value="{{ $incident->source_ip }}" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                            </label>
                        </div>
                        <label class="block text-sm text-zinc-300">
                            Detection reason
                            <input type="text" name="detection_reason" value="{{ $incident->detection_reason }}" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                        </label>
                        <label class="block text-sm text-zinc-300">
                            Description
                            <textarea name="description" rows="4" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">{{ $incident->description }}</textarea>
                        </label>
                        <button type="submit" class="rounded-md border border-cyan-500/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 hover:bg-cyan-500/20">Save incident details</button>
                    </form>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-5">
                    <h2 class="text-lg font-semibold text-white">Source and detection</h2>
                    <dl class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase tracking-[0.2em] text-zinc-500">Source IP</dt>
                            <dd class="mt-1 text-zinc-200">{{ $incident->source_ip ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-[0.2em] text-zinc-500">Target account</dt>
                            <dd class="mt-1 text-zinc-200">{{ $incident->user?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-[0.2em] text-zinc-500">Detection reason</dt>
                            <dd class="mt-1 text-zinc-200">{{ $incident->detection_reason ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-[0.2em] text-zinc-500">Detection rule</dt>
                            <dd class="mt-1 text-zinc-200">{{ $incident->detection_rule ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-[0.2em] text-zinc-500">Related events</dt>
                            <dd class="mt-1 text-zinc-200">{{ $incident->event_count ?: 0 }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-[0.2em] text-zinc-500">First detected</dt>
                            <dd class="mt-1 text-zinc-200">{{ $incident->first_detected_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-5">
                    <h2 class="text-lg font-semibold text-white">Investigation remarks</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($incident->remarks as $remark)
                            <div class="rounded-lg border border-zinc-800 bg-zinc-900/80 p-3">
                                <div class="flex items-center justify-between gap-4 text-xs uppercase tracking-[0.15em] text-zinc-500">
                                    <span>{{ $remark->author?->name ?? 'System' }}</span>
                                    <span>{{ $remark->created_at?->format('Y-m-d H:i:s') }}</span>
                                </div>
                                <p class="mt-2 whitespace-pre-wrap text-sm text-zinc-300">{{ $remark->remark }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-zinc-400">No investigation remarks have been recorded yet.</p>
                        @endforelse
                    </div>

                    <form method="POST" action="{{ route('incidents.remarks.store', $incident) }}" class="mt-5 space-y-3">
                        @csrf
                        <label class="block text-sm text-zinc-300">
                            Add remark
                            <textarea name="remark" rows="4" required class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100 placeholder:text-zinc-500"></textarea>
                        </label>
                        <button type="submit" class="rounded-md bg-cyan-400 px-4 py-2 text-sm font-semibold text-zinc-950 hover:bg-cyan-300">Add remark</button>
                    </form>
                </div>
            </div>

            <aside class="space-y-6">
                <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-5">
                    <h2 class="text-lg font-semibold text-white">Response and status</h2>
                    <form method="POST" action="{{ route('incidents.status.update', $incident) }}" class="mt-4 space-y-4">
                        @csrf
                        @method('PATCH')
                        <label class="block text-sm text-zinc-300">
                            Status
                            <select name="status" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                                @foreach (['open', 'investigating', 'contained', 'resolved', 'false_positive'] as $status)
                                    <option value="{{ $status }}" {{ $incident->status === $status ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm text-zinc-300">
                            Status reason
                            <input type="text" name="reason" placeholder="Optional reason" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100 placeholder:text-zinc-500">
                        </label>
                        <button type="submit" class="w-full rounded-md border border-cyan-500/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 hover:bg-cyan-500/20">Update status</button>
                    </form>

                    <form method="POST" action="{{ route('incidents.severity.update', $incident) }}" class="mt-5 space-y-4">
                        @csrf
                        @method('PATCH')
                        <label class="block text-sm text-zinc-300">
                            Severity
                            <select name="severity" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                                @foreach (['Normal', 'Warning', 'Suspicious', 'High', 'Critical'] as $level)
                                    <option value="{{ $level }}" {{ $incident->severity === $level ? 'selected' : '' }}>{{ $level }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="w-full rounded-md border border-zinc-700 bg-zinc-900 px-4 py-2 text-sm font-medium text-zinc-200 hover:border-cyan-500/40 hover:text-white">Update severity</button>
                    </form>

                    <form method="POST" action="{{ route('incidents.assign', $incident) }}" class="mt-5 space-y-4">
                        @csrf
                        <label class="block text-sm text-zinc-300">
                            Assign administrator
                            <select name="assigned_to" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">
                                @foreach (\App\Models\User::where('role', 'administrator')->get() as $admin)
                                    <option value="{{ $admin->id }}" {{ $incident->assigned_to == $admin->id ? 'selected' : '' }}>{{ $admin->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="w-full rounded-md border border-zinc-700 bg-zinc-900 px-4 py-2 text-sm font-medium text-zinc-200 hover:border-cyan-500/40 hover:text-white">Save assignment</button>
                    </form>

                    <form method="POST" action="{{ route('incidents.response.store', $incident) }}" class="mt-5 space-y-4">
                        @csrf
                        <label class="block text-sm text-zinc-300">
                            Response actions
                            <textarea name="response_actions" rows="3" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">{{ $incident->response_actions }}</textarea>
                        </label>
                        <label class="block text-sm text-zinc-300">
                            Resolution notes
                            <textarea name="resolution_notes" rows="3" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100">{{ $incident->resolution_notes }}</textarea>
                        </label>
                        <button type="submit" class="w-full rounded-md border border-cyan-500/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 hover:bg-cyan-500/20">Save response details</button>
                    </form>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-5">
                    <h2 class="text-lg font-semibold text-white">Timeline</h2>
                    <ol class="mt-4 space-y-4 border-l border-zinc-800 pl-4">
                        @foreach ($timeline as $entry)
                            <li class="relative">
                                <span class="absolute -left-[1.18rem] mt-1.5 h-2.5 w-2.5 rounded-full bg-cyan-400"></span>
                                <div class="ml-2">
                                    <p class="text-xs uppercase tracking-[0.2em] text-zinc-500">{{ $entry['timestamp']?->format('Y-m-d H:i:s') ?? '—' }}</p>
                                    <p class="mt-1 text-sm font-semibold text-white">{{ $entry['title'] }}</p>
                                    <p class="mt-1 text-sm text-zinc-300">{{ $entry['detail'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </aside>
        </div>
    </div>
</x-layouts.app>
