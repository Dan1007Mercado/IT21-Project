<x-layouts.app title="Audit Logs - INTSEC">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.2em] text-cyan-300">Administration</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Audit logs</h1>
        </div>
        <a href="{{ route('admin.settings') }}" class="rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-300 hover:border-cyan-500/40">System settings</a>
    </div>

    <section class="mt-8 overflow-hidden rounded-lg border border-zinc-800 bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[920px] text-left text-sm">
                <thead class="border-b border-zinc-800 text-xs uppercase tracking-[0.14em] text-zinc-500">
                    <tr>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Performed by</th>
                        <th class="px-4 py-3">Resource</th>
                        <th class="px-4 py-3">IP</th>
                        <th class="px-4 py-3">Occurred</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="px-4 py-3 text-zinc-200">{{ str_replace('_', ' ', ucfirst($log->action)) }}</td>
                            <td class="px-4 py-3 text-zinc-300">{{ $log->actor?->name ?? 'System' }}</td>
                            <td class="px-4 py-3 text-zinc-400">{{ $log->resource_type ?? 'n/a' }}</td>
                            <td class="px-4 py-3 text-zinc-500">{{ $log->ip_address ?? 'Unknown' }}</td>
                            <td class="px-4 py-3 text-zinc-500">{{ $log->occurred_at?->format('M j, Y H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-zinc-400">No audit records are available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-6">
        {{ $logs->links() }}
    </div>
</x-layouts.app>
