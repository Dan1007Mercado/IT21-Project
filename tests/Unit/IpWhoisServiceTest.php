<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Security\AuthActivityLogger;
use App\Services\Security\IpWhoisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IpWhoisServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
    }

    public function test_it_returns_normalized_public_ip_location(): void
    {
        Http::fake([
            'https://ipwho.is/8.8.8.8' => Http::response([
                'success' => true,
                'ip' => '8.8.8.8',
                'country' => 'United States',
                'country_code' => 'US',
                'region' => 'California',
                'region_code' => 'CA',
                'city' => 'Mountain View',
                'latitude' => 37.4056,
                'longitude' => -122.0775,
                'postal' => '94043',
                'connection' => [
                    'asn' => 15169,
                    'org' => 'Google LLC',
                    'isp' => 'Google LLC',
                ],
                'timezone' => ['id' => 'America/Los_Angeles'],
            ], 200),
        ]);

        $result = app(IpWhoisService::class)->lookup('8.8.8.8');

        $this->assertNotNull($result);
        $this->assertSame('8.8.8.8', $result['ip']);
        $this->assertSame('United States', $result['country']);
        $this->assertSame('Mountain View', $result['city']);
        $this->assertSame(37.4056, $result['latitude']);
        $this->assertSame(-122.0775, $result['longitude']);
    }

    public function test_it_returns_null_for_private_ip(): void
    {
        $this->assertNull(app(IpWhoisService::class)->lookup('192.168.1.15'));
        $this->assertNull(app(IpWhoisService::class)->lookup('127.0.0.1'));
    }

    public function test_it_returns_null_for_failed_ipwho_response(): void
    {
        Http::fake([
            'https://ipwho.is/203.0.113.5' => Http::response([
                'success' => false,
                'message' => 'invalid ip',
            ], 200),
        ]);

        $this->assertNull(app(IpWhoisService::class)->lookup('203.0.113.5'));
    }

    public function test_it_uses_cache_for_repeated_lookup(): void
    {
        Http::fake([
            'https://ipwho.is/1.1.1.1' => Http::response([
                'success' => true,
                'ip' => '1.1.1.1',
                'country' => 'Australia',
                'country_code' => 'AU',
                'region' => 'New South Wales',
                'region_code' => 'NSW',
                'city' => 'Sydney',
                'latitude' => -33.8688,
                'longitude' => 151.2093,
                'postal' => '2000',
                'connection' => [
                    'asn' => 13335,
                    'org' => 'Cloudflare, Inc.',
                    'isp' => 'Cloudflare, Inc.',
                ],
                'timezone' => ['id' => 'Australia/Sydney'],
            ], 200),
        ]);

        $service = app(IpWhoisService::class);

        $first = $service->lookup('1.1.1.1');
        $second = $service->lookup('1.1.1.1');

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        Http::assertSentCount(1);
    }

    public function test_it_handles_http_failures_and_invalid_ip(): void
    {
        Http::fake([
            'https://ipwho.is/invalid' => Http::response('', 500),
        ]);

        $this->assertNull(app(IpWhoisService::class)->lookup('invalid'));
        $this->assertNull(app(IpWhoisService::class)->lookup('256.0.0.1'));
    }

    public function test_auth_activity_logger_enriches_public_ip_geolocation(): void
    {
        Http::fake([
            'https://ipwho.is/8.8.8.8' => Http::response([
                'success' => true,
                'ip' => '8.8.8.8',
                'country' => 'United States',
                'country_code' => 'US',
                'region' => 'California',
                'region_code' => 'CA',
                'city' => 'Mountain View',
                'latitude' => 37.4056,
                'longitude' => -122.0775,
                'postal' => '94043',
                'connection' => [
                    'asn' => 15169,
                    'org' => 'Google LLC',
                    'isp' => 'Google LLC',
                ],
                'timezone' => ['id' => 'America/Los_Angeles'],
            ], 200),
        ]);

        $user = User::factory()->create();
        $request = Request::create('/login', 'POST', [], [], [], [
            'REMOTE_ADDR' => '8.8.8.8',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
        ]);

        $log = app(AuthActivityLogger::class)->record(
            $request,
            'login',
            'successful',
            $user,
            'user@intsec.test'
        );

        $this->assertSame('8.8.8.8', $log->ip_address);
        $this->assertSame('United States', $log->country);
        $this->assertSame('Mountain View', $log->city);
        $this->assertSame(37.4056, $log->latitude);
        $this->assertSame(-122.0775, $log->longitude);
        $this->assertSame('Google LLC', $log->organization);
        $this->assertSame('America/Los_Angeles', $log->timezone);
    }
}
