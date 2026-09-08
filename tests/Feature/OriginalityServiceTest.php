<?php

namespace Tests\Feature;

use hexa_core\Security\Http\OutboundHttpRequest;
use hexa_core\Security\Http\OutboundHttpResponse;
use hexa_core\Security\Http\OutboundUrlGuard;
use hexa_core\Security\Http\SafeOutboundHttpClient;
use hexa_core\Services\CredentialService;
use hexa_package_originality\Services\OriginalityService;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

final class OriginalityServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requireInstalledPackage('hexawebsystems/laravel-hexa-package-originality', OriginalityService::class);
        Http::preventStrayRequests();
    }

    public function test_connection_uses_bounded_pinned_transport_and_package_credential(): void
    {
        $credentials = $this->mockCredential('fixture-key');
        $requests = [];
        $service = $this->service($credentials, $requests, new OutboundHttpResponse(200, [], '{"results":{"credits":1}}'));

        $result = $service->testConnection();

        $this->assertTrue($result['success']);
        $this->assertSame('Originality.ai API connected successfully. Credits used: 1.', $result['message']);
        $this->assertCount(1, $requests);
        $this->assertSame('https://api.originality.ai/api/v3/scan', $requests[0]->target->url);
        $this->assertSame('fixture-key', $requests[0]->headers['X-OAI-API-KEY']);
        $this->assertSame(15, $requests[0]->timeoutSeconds);
        $this->assertSame(4 * 1024 * 1024, $requests[0]->maxResponseBytes);
        $this->assertSame('lite', json_decode((string) $requests[0]->body, true, 512, JSON_THROW_ON_ERROR)['aiModelVersion']);
        Http::assertNothingSent();
    }

    public function test_provider_failures_do_not_leak_credentials_or_response_details(): void
    {
        $credentials = $this->mockCredential('fixture-key');
        $requests = [];
        $service = $this->service($credentials, $requests, new OutboundHttpResponse(
            500,
            [],
            '{"error":"fixture-key provider detail"}',
        ));

        $result = $service->testConnection();
        $serialized = json_encode($result, JSON_THROW_ON_ERROR);

        $this->assertFalse($result['success']);
        $this->assertStringNotContainsString('fixture-key', $serialized);
        $this->assertStringNotContainsString('provider detail', $serialized);
        Http::assertNothingSent();
    }

    public function test_detection_preserves_provider_classification_scores_and_credits(): void
    {
        $credentials = $this->mockCredential('fixture-key');
        $requests = [];
        $service = $this->service($credentials, $requests, new OutboundHttpResponse(200, [], json_encode([
            'results' => [
                'credits' => 2,
                'ai' => [
                    'classification' => 'AI',
                    'score' => ['ai' => 0.93, 'original' => 0.07],
                ],
            ],
        ], JSON_THROW_ON_ERROR)));

        $result = $service->detect('Fixture content for a detector request.');

        $this->assertTrue($result['success']);
        $this->assertSame('AI', $result['data']['classification']);
        $this->assertSame(0.93, $result['data']['ai_score']);
        $this->assertSame(0.07, $result['data']['original_score']);
        $this->assertSame(2, $result['data']['credits_used']);
        $this->assertSame(30, $requests[0]->timeoutSeconds);
        Http::assertNothingSent();
    }

    public function test_service_source_has_no_raw_http_or_exception_detail_fallback(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/src/Services/OriginalityService.php');

        $this->assertStringContainsString('SafeOutboundHttpClient', $source);
        $this->assertStringNotContainsString('Facades\\Http', $source);
        $this->assertStringNotContainsString('Http::', $source);
        $this->assertStringNotContainsString('->getMessage()', $source);
        $this->assertStringNotContainsString('->body()', $source);
    }

    public function test_settings_controller_writes_api_keys_only_through_credential_vault(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/src/Http/Controllers/OriginalityController.php');

        $this->assertStringContainsString("credentials->store('originality', 'api_key'", $source);
        $this->assertStringNotContainsString("Setting::setValue('originality_api_key', \$validated", $source);
    }

    /** @param list<OutboundHttpRequest> $requests */
    private function service(
        CredentialService $credentials,
        array &$requests,
        OutboundHttpResponse $response,
    ): OriginalityService {
        $client = new SafeOutboundHttpClient(
            new OutboundUrlGuard(static fn (string $host): array => ['93.184.216.34']),
            static function (OutboundHttpRequest $request) use (&$requests, $response): OutboundHttpResponse {
                $requests[] = $request;

                return $response;
            },
        );

        return new OriginalityService($client, $credentials);
    }

    private function mockCredential(string $apiKey): CredentialService
    {
        $credentials = Mockery::mock(CredentialService::class);
        $credentials->shouldReceive('get')->once()->with('originality', 'api_key')->andReturn($apiKey);

        return $credentials;
    }
}
