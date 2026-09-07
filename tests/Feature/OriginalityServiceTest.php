<?php

namespace Tests\Feature;

use hexa_core\Services\CredentialService;
use hexa_package_originality\Services\OriginalityService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class OriginalityServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requireInstalledPackage("hexawebsystems/laravel-hexa-package-originality", OriginalityService::class);
    }

    public function test_connection_uses_package_credential_and_provider_endpoint(): void
    {
        $credentials = Mockery::mock(CredentialService::class);
        $credentials->shouldReceive("get")->once()->with("originality", "api_key")->andReturn("test-key");
        $this->app->instance(CredentialService::class, $credentials);

        Http::fake(["*api.originality.ai/*" => Http::response(["success" => true], 200)]);

        $result = app(OriginalityService::class)->testConnection();

        $this->assertTrue($result["success"]);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), "api.originality.ai/api/v3/scan") && $request->hasHeader("X-OAI-API-KEY", "test-key"));
    }
}
