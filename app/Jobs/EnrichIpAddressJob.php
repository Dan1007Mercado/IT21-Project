<?php

namespace App\Jobs;

use App\Services\Security\IpEnrichmentService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnrichIpAddressJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 900;

    public function __construct(public string $ipAddress)
    {
        $this->onQueue('security-enrichment');
    }

    public function uniqueId(): string
    {
        return $this->ipAddress;
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(IpEnrichmentService $enrichment): void
    {
        $enrichment->enrich($this->ipAddress);
    }
}
