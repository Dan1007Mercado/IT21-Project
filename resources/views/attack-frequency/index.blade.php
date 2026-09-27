<x-layouts.app title="Attack Frequency - INTSEC">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.2em] text-cyan-300">Security monitoring</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">IP Request Frequency</h1>
        </div>
        <div class="flex flex-wrap gap-2 text-xs text-zinc-300">
            <span class="rounded-full border border-zinc-700 bg-zinc-950/60 px-2.5 py-1.5">Tracked IPs: {{ $attackFrequency->total() }}</span>
        </div>
    </div>

    <form method="GET" action="{{ route('attack-frequency') }}" class="mt-6 flex flex-wrap gap-3">
        <input
            type="text"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search IP address"
            class="min-w-[220px] flex-1 rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-500"
        >

        <select name="min_requests" class="rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100">
            <option value="">All request counts</option>
            @foreach ([5, 10, 25, 50, 100] as $threshold)
                <option value="{{ $threshold }}" {{ request('min_requests') == (string) $threshold ? 'selected' : '' }}>
                    {{ $threshold }}+ requests
                </option>
            @endforeach
        </select>

        <button type="submit" class="rounded-md border border-cyan-500/40 bg-cyan-500/10 px-4 py-2 text-sm font-medium text-cyan-200">
            Filter
        </button>
        <a href="{{ route('attack-frequency') }}" class="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-300">
            Clear
        </a>
    </form>

    <section class="mt-8 overflow-hidden rounded-lg border border-zinc-800 bg-zinc-900">
        <div class="border-b border-zinc-800 px-5 py-4">
            <h2 class="text-lg font-semibold text-white">Frequency detail</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-left text-sm text-zinc-200">
                <thead class="bg-zinc-950/70">
                    <tr>
                        <th class="px-5 py-3 font-medium text-zinc-300">IP Address</th>
                        <th class="px-5 py-3 font-medium text-zinc-300">Request Count</th>
                        <th class="px-5 py-3 font-medium text-zinc-300">Activity</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($attackFrequency as $entry)
                        <tr class="border-t border-zinc-800">
                            <td class="px-5 py-4 font-mono text-zinc-100">{{ $entry['ip'] }}</td>
                            <td class="px-5 py-4 text-zinc-300">{{ $entry['count'] }}</td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs {{ $entry['count'] >= 10 ? 'border-amber-500/40 bg-amber-500/10 text-amber-200' : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200' }}">
                                    {{ $entry['count'] >= 10 ? 'High activity' : 'Normal activity' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-10 text-center text-zinc-400">No repeated attack patterns detected for the current filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-6 px-1 pb-2">
        {{ $attackFrequency->appends(request()->query())->links() }}
    </div>
</x-layouts.app>
