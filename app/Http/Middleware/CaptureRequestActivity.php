<?php

namespace App\Http\Middleware;

use App\Models\RequestActivity;
use App\Services\Security\ClientIpResolver;
use App\Services\Security\IpEnrichmentService;
use App\Services\Security\RequestDetectionService;
use App\Services\Security\UserAgentClassifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CaptureRequestActivity
{
    public function __construct(
        private ClientIpResolver $clientIpResolver,
        private UserAgentClassifier $userAgentClassifier,
        private IpEnrichmentService $ipEnrichment,
        private RequestDetectionService $requestDetection,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Platform probes and the browser favicon must never enter the
        // telemetry, enrichment, detection, or broadcast pipeline.
        if ($request->is('up') || $request->is('favicon.ico')) {
            return $next($request);
        }

        if ($this->isExcluded($request)) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $occurredAt = now();

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->persist($request, 500, $startedAt, $occurredAt, null);
            throw $exception;
        }

        $this->persist($request, $response->getStatusCode(), $startedAt, $occurredAt, $response);

        return $response;
    }

    private function persist(Request $request, int $statusCode, int $startedAt, $occurredAt, ?Response $response): void
    {
        try {
            $resolved = $this->clientIpResolver->resolve($request);
            $agent = $this->userAgentClassifier->analyze($request->userAgent());
            $requestId = (string) $request->attributes->get('intsec_request_id', Str::uuid());
            $request->attributes->set('intsec_request_id', $requestId);

            $activity = RequestActivity::query()->create([
                'request_id' => $requestId,
                'source' => config('intsec.source', 'intsec'),
                'user_id' => $request->user()?->id,
                'ip_address' => $resolved['ip'],
                'ip_type' => $resolved['type'],
                'method' => strtoupper($request->method()),
                'path' => '/'.ltrim($request->path(), '/'),
                'route_name' => $request->route()?->getName(),
                'status_code' => $statusCode,
                'user_agent' => $agent['user_agent'],
                'referer' => $this->safeReferer($request),
                'is_authenticated' => $request->user() !== null,
                'duration_ms' => max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000)),
                'request_size' => $this->positiveInteger($request->server('CONTENT_LENGTH')),
                'response_size' => $this->responseSize($response),
                'classification' => 'normal',
                'metadata' => [
                    'device_type' => $agent['device_type'],
                    'browser_name' => $agent['browser_name'],
                    'os_name' => $agent['os_name'],
                ],
                'occurred_at' => $occurredAt,
            ]);

            $this->ipEnrichment->observe($resolved['ip']);
            $this->requestDetection->evaluate($activity);
        } catch (Throwable $exception) {
            Log::warning('INTSEC request telemetry persistence failed.', [
                'exception' => $exception::class,
                'route_name' => $request->route()?->getName(),
                'path' => '/'.ltrim($request->path(), '/'),
            ]);
        }
    }

    private function isExcluded(Request $request): bool
    {
        $path = '/'.ltrim($request->path(), '/');

        return collect(config('intsec.request_monitoring.exclusions', []))
            ->contains(fn (string $pattern): bool => Str::is($pattern, $path));
    }

    private function safeReferer(Request $request): ?string
    {
        $referer = trim((string) $request->headers->get('referer'));

        return $referer === '' ? null : Str::limit($referer, 2048, '');
    }

    private function positiveInteger(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value >= 0 ? (int) $value : null;
    }

    private function responseSize(?Response $response): ?int
    {
        if ($response === null) {
            return null;
        }

        $header = $this->positiveInteger($response->headers->get('Content-Length'));
        if ($header !== null) {
            return $header;
        }

        $content = $response->getContent();

        return is_string($content) ? strlen($content) : null;
    }
}
