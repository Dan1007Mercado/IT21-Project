<?php

namespace App\Services\Security;

use App\Models\BlockedIp;
use App\Models\RequestActivity;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class RequestActivityQueryService
{
    public function startForRange(?string $range): CarbonInterface
    {
        return match ($range) {
            '1h' => now()->subHour(),
            '24h', 'today' => now()->subDay(),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDays(7),
        };
    }

    /** @return array<int, array{label: string, count: int}> */
    public function hourlyTrend(int $hours = 24, ?string $source = null): array
    {
        $start = now()->subHours($hours - 1)->startOfHour();
        $expression = $this->bucketExpression('hour');
        $counts = RequestActivity::query()
            ->forSource($source ?? config('intsec.source', 'intsec'))
            ->where('occurred_at', '>=', $start)
            ->selectRaw("{$expression} as bucket, COUNT(*) as total")
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        return collect(range($hours - 1, 0))->map(function (int $hoursAgo) use ($counts): array {
            $time = now()->subHours($hoursAgo)->startOfHour();
            $key = $time->format('Y-m-d H:00:00');

            return ['label' => $time->format('M j H:00'), 'count' => (int) ($counts[$key] ?? 0)];
        })->values()->all();
    }

    /** @return array<int, array{label: string, count: int}> */
    public function dailyTrend(int $days = 7, ?string $source = null): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $expression = $this->bucketExpression('day');
        $counts = RequestActivity::query()
            ->forSource($source ?? config('intsec.source', 'intsec'))
            ->where('occurred_at', '>=', $start)
            ->selectRaw("{$expression} as bucket, COUNT(*) as total")
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        return collect(range($days - 1, 0))->map(function (int $daysAgo) use ($counts): array {
            $date = now()->subDays($daysAgo);

            return ['label' => $date->format('M j'), 'count' => (int) ($counts[$date->format('Y-m-d')] ?? 0)];
        })->values()->all();
    }

    public function applyFrequencyFilters(Builder $query, array $filters, ?string $source = null): Builder
    {
        $query->forSource($source ?? config('intsec.source', 'intsec'));
        $query->where('occurred_at', '>=', $this->startForRange($filters['range'] ?? '7d'));

        if (filled($filters['ip'] ?? null)) {
            $query->where('ip_address', 'like', '%'.trim((string) $filters['ip']).'%');
        }

        if (filled($filters['classification'] ?? null)) {
            $query->where('classification', $filters['classification']);
        }

        if (filled($filters['status_family'] ?? null) && preg_match('/^[1-5]xx$/', (string) $filters['status_family'])) {
            $base = ((int) $filters['status_family'][0]) * 100;
            $query->whereBetween('status_code', [$base, $base + 99]);
        }

        return $query;
    }

    /** @return Collection<string, string> */
    public function mostRequestedPaths(Collection $ips, \DateTimeInterface $start, ?string $source = null): Collection
    {
        if ($ips->isEmpty()) {
            return collect();
        }

        return RequestActivity::query()
            ->forSource($source ?? config('intsec.source', 'intsec'))
            ->whereIn('ip_address', $ips)
            ->where('occurred_at', '>=', $start)
            ->selectRaw('ip_address, path, COUNT(*) as total')
            ->groupBy('ip_address', 'path')
            ->orderByDesc('total')
            ->get()
            ->groupBy('ip_address')
            ->map(fn (Collection $rows): string => (string) $rows->first()->path);
    }

    /** @return Collection<string, bool> */
    public function blockedStates(Collection $ips): Collection
    {
        $rules = BlockedIp::query()->enforcing()->where('action', BlockedIp::ACTION_BLOCK)->get(['ip_address']);

        return $ips->mapWithKeys(fn (string $ip): array => [
            $ip => $rules->contains(fn (BlockedIp $rule): bool => IpNetwork::matches($rule->ip_address, $ip)),
        ]);
    }

    private function bucketExpression(string $granularity): string
    {
        $driver = RequestActivity::query()->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return $granularity === 'hour'
                ? "strftime('%Y-%m-%d %H:00:00', occurred_at)"
                : "strftime('%Y-%m-%d', occurred_at)";
        }

        return $granularity === 'hour'
            ? "DATE_FORMAT(occurred_at, '%Y-%m-%d %H:00:00')"
            : "DATE_FORMAT(occurred_at, '%Y-%m-%d')";
    }
}
