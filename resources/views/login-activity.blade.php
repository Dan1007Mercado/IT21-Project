<x-layouts.app title="Login Activity - INTSEC">
    <h1 class="text-3xl font-semibold text-white">Login activity</h1>
    <p class="mt-2 text-sm text-zinc-400">Recent authentication events recorded for your account.</p>

    <section class="mt-8 overflow-hidden rounded-lg border border-zinc-800 bg-zinc-900">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-800 text-xs uppercase text-zinc-500">
                <tr>
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">Action</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">IP address</th>
                    <th class="px-4 py-3">User agent</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-4 py-3 text-zinc-300">{{ $log->occurred_at->format('M j, Y H:i') }}</td>
                        <td class="px-4 py-3 text-zinc-300">{{ ucfirst($log->action) }}</td>
                        <td class="px-4 py-3">
                            <span class="{{ $log->status === 'successful' ? 'text-emerald-300' : 'text-red-300' }}">{{ ucfirst($log->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-zinc-400">{{ $log->ip_address ?? 'Unknown' }}</td>
                        <td class="max-w-md truncate px-4 py-3 text-zinc-500">{{ $log->user_agent ?? 'Unknown' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-400">No login activity has been recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="mt-6">
        {{ $logs->links() }}
    </div>
</x-layouts.app>
