<?php

namespace hexa_package_originality\Services;

use hexa_core\AI\Contracts\AiTransactionRecorder;
use hexa_core\Models\Setting;
use hexa_core\Security\Http\OutboundHttpException;
use hexa_core\Security\Http\OutboundHttpResponse;
use hexa_core\Security\Http\SafeOutboundHttpClient;
use hexa_core\Services\CredentialService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AI-content detection through Originality.ai's scan endpoint.
 */
class OriginalityService
{
    private const DEFAULT_ENDPOINT = 'https://api.originality.ai/api/v3/scan';

    private const CONNECTION_TIMEOUT_SECONDS = 15;

    private const DETECTION_TIMEOUT_SECONDS = 30;

    private const MAX_RESPONSE_BYTES = 4 * 1024 * 1024;

    public function __construct(
        private readonly SafeOutboundHttpClient $http,
        private readonly CredentialService $credentials,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) Setting::getValue('originality_enabled', config('originality.enabled', true));
    }

    public function isDebugMode(): bool
    {
        return (bool) Setting::getValue('originality_debug_mode', false);
    }

    public function getApiKey(): ?string
    {
        return $this->credentials->get('originality', 'api_key')
            ?: Setting::getValue('originality_api_key');
    }

    /** @return array{success: bool, message: string, data?: array} */
    public function detect(string $text): array
    {
        $apiKey = $this->validApiKey($this->getApiKey());
        if ($apiKey === null) {
            return ['success' => false, 'message' => 'Originality.ai API key not configured.'];
        }

        if (! $this->isEnabled()) {
            return ['success' => false, 'message' => 'Originality.ai is disabled.'];
        }

        $text = $this->debugText($text);

        try {
            $response = $this->requestScan($apiKey, $text, 'turbo', self::DETECTION_TIMEOUT_SECONDS, 'detector.scan');
        } catch (Throwable $exception) {
            $this->logTransportFailure('scan', $exception);

            return ['success' => false, 'message' => 'Originality.ai request failed safely.'];
        }

        if (! $response->successful()) {
            return ['success' => false, 'message' => "Originality.ai API error ({$response->status})."];
        }

        $data = $this->jsonObject($response);
        $results = is_array($data['results'] ?? null) ? $data['results'] : [];
        $ai = is_array($results['ai'] ?? null)
            ? $results['ai']
            : (is_array($data['ai'] ?? null) ? $data['ai'] : []);
        $score = is_array($ai['score'] ?? null) ? $ai['score'] : [];

        return [
            'success' => true,
            'message' => 'Detection complete.',
            'data' => [
                'original_score' => $score['original'] ?? $ai['original'] ?? null,
                'ai_score' => $score['ai'] ?? $ai['ai'] ?? null,
                'classification' => $ai['classification'] ?? null,
                'credits_used' => $results['credits'] ?? null,
                'sentences' => [],
                'raw' => $data,
            ],
        ];
    }

    /** @return array{success: bool, message: string} */
    public function testConnection(): array
    {
        $apiKey = $this->validApiKey($this->getApiKey());
        if ($apiKey === null) {
            return ['success' => false, 'message' => 'Originality.ai API key not configured.'];
        }

        try {
            $response = $this->requestScan(
                $apiKey,
                'Test connection verification.',
                'lite',
                self::CONNECTION_TIMEOUT_SECONDS,
                'detector.connection_test',
            );
        } catch (Throwable $exception) {
            $this->logTransportFailure('connection_test', $exception);

            return ['success' => false, 'message' => 'Originality.ai connection failed safely.'];
        }

        if (! $response->successful()) {
            return ['success' => false, 'message' => "Originality.ai API error ({$response->status})."];
        }

        $data = $this->jsonObject($response);
        $results = is_array($data['results'] ?? null) ? $data['results'] : [];
        $credits = $results['credits'] ?? $data['credits'] ?? null;
        $message = 'Originality.ai API connected successfully.';
        if ($credits !== null) {
            $message .= ' Credits used: '.$credits.'.';
        }

        return ['success' => true, 'message' => $message];
    }

    private function requestScan(
        string $apiKey,
        string $text,
        string $model,
        int $timeout,
        string $operation,
    ): OutboundHttpResponse {
        $endpoint = (string) config('originality.api_url', self::DEFAULT_ENDPOINT);
        $units = ['characters' => mb_strlen($text), 'words' => str_word_count($text)];
        $span = app(AiTransactionRecorder::class)->start([
            'provider' => 'originality',
            'package' => 'hexawebsystems/laravel-hexa-package-originality',
            'model' => 'originality-'.$model,
            'operation' => $operation,
            'endpoint' => parse_url($endpoint, PHP_URL_PATH) ?: '/api/v3/scan',
            'request_metadata' => array_merge($units, [
                'timeout_seconds' => $timeout,
                'check_ai' => true,
                'check_plagiarism' => false,
            ]),
        ]);

        try {
            $response = $this->http->request('POST', $endpoint, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-OAI-API-KEY' => $apiKey,
                ],
                'body' => json_encode([
                    'title' => $operation === 'detector.connection_test' ? 'Connection test' : 'Detection scan',
                    'content' => $text,
                    'check_ai' => true,
                    'check_plagiarism' => false,
                    'aiModelVersion' => $model,
                ], JSON_THROW_ON_ERROR),
                'timeout' => $timeout,
                'max_bytes' => self::MAX_RESPONSE_BYTES,
                'max_redirects' => 0,
            ]);
            $payload = $this->jsonObject($response);
            $results = is_array($payload['results'] ?? null) ? $payload['results'] : [];
            $ai = is_array($results['ai'] ?? null)
                ? $results['ai']
                : (is_array($payload['ai'] ?? null) ? $payload['ai'] : []);
            $score = is_array($ai['score'] ?? null) ? $ai['score'] : [];
            $credits = $results['credits'] ?? $payload['credits'] ?? null;
            $usage = array_merge($units, [
                'credits_used' => is_numeric($credits) ? (float) $credits : null,
            ]);
            $attributes = [
                'provider_request_id' => $response->headerValues('x-request-id')[0] ?? null,
                'http_status' => $response->status,
                'usage' => $usage,
                'response_metadata' => [
                    'classification' => $ai['classification'] ?? null,
                    'ai_score' => $score['ai'] ?? null,
                    'original_score' => $score['original'] ?? null,
                ],
            ];

            if ($response->successful()) {
                $span->succeed($attributes);
            } else {
                $span->fail('Originality.ai request failed.', array_merge($attributes, [
                    'error_type' => 'originality_http_error',
                ]));
            }

            return $response;
        } catch (Throwable $exception) {
            $span->fail(
                $exception instanceof OutboundHttpException ? $exception : 'Originality.ai request failed.',
                ['usage' => $units],
            );

            throw $exception;
        }
    }

    private function debugText(string $text): string
    {
        if (! $this->isDebugMode()) {
            return $text;
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $text, 4) ?: [];

        return implode(' ', array_slice($sentences, 0, 3));
    }

    /** @return array<string, mixed> */
    private function jsonObject(OutboundHttpResponse $response): array
    {
        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    private function validApiKey(?string $apiKey): ?string
    {
        $apiKey = trim((string) $apiKey);

        return $apiKey !== ''
            && strlen($apiKey) <= 4096
            && preg_match('/[\x00-\x1f\x7f]/', $apiKey) !== 1
                ? $apiKey
                : null;
    }

    private function logTransportFailure(string $operation, Throwable $exception): void
    {
        Log::warning('Originality.ai request failed safely', [
            'operation' => $operation,
            'failure_code' => $exception instanceof OutboundHttpException
                ? $exception->failureCode()
                : 'request_failed',
        ]);
    }
}
