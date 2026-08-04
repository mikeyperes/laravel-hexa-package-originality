<?php

namespace hexa_package_originality\Services;

use hexa_core\AI\Contracts\AiTransactionRecorder;
use hexa_core\Models\Setting;
use hexa_core\Services\GenericService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * OriginalityService — AI content detection via Originality.ai API.
 *
 * Detects AI-generated text with per-sentence probability scoring.
 * Free tier: 10,000 words/month. API: api.originality.me
 */
class OriginalityService
{
    protected GenericService $generic;

    /**
     * @param GenericService $generic
     */
    public function __construct(GenericService $generic)
    {
        $this->generic = $generic;
    }

    /**
     * Check if Originality.ai is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return Setting::getValue('originality_enabled', config('originality.enabled', true));
    }

    /**
     * Check if debug mode is on (sends only first 3 sentences).
     *
     * @return bool
     */
    public function isDebugMode(): bool
    {
        return (bool) Setting::getValue('originality_debug_mode', false);
    }

    /**
     * Get the API key (from CredentialService or legacy Setting).
     *
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        if (class_exists(\hexa_core\Services\CredentialService::class)) {
            $cred = app(\hexa_core\Services\CredentialService::class);
            $val = $cred->get('originality', 'api_key');
            if ($val) return $val;
        }
        return Setting::getValue('originality_api_key');
    }

    /**
     * Detect AI-generated content.
     *
     * @param string $text Plain text to analyze
     * @return array{success: bool, message: string, data?: array}
     */
    public function detect(string $text): array
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Originality.ai API key not configured.'];
        }

        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'Originality.ai is disabled.'];
        }

        // Debug mode: only send first 3 sentences
        if ($this->isDebugMode()) {
            $sentences = preg_split('/(?<=[.!?])\s+/', $text, 4);
            $text = implode(' ', array_slice($sentences, 0, 3));
        }

        try {
            $response = $this->requestScan($apiKey, $text, 'turbo', 30, 'detector.scan');

            if (!$response->successful()) {
                $error = $response->json('error') ?? $response->json('message') ?? $response->body();
                return ['success' => false, 'message' => 'Originality.ai API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error))];
            }

            $data = $response->json();
            $ai = $data['results']['ai'] ?? $data['ai'] ?? [];
            $originalScore = $ai['score']['original'] ?? $ai['original'] ?? null;
            $aiScore = $ai['score']['ai'] ?? $ai['ai'] ?? null;

            return [
                'success' => true,
                'message' => 'Detection complete.',
                'data' => [
                    'original_score' => $originalScore,
                    'ai_score' => $aiScore,
                    'classification' => $ai['classification'] ?? null,
                    'credits_used' => $data['results']['credits'] ?? null,
                    'sentences' => [],
                    'raw' => $data,
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Originality.ai request failed: ' . $e->getMessage()];
        }
    }

    /**
     * Test the API connection by verifying the API key is accepted.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Originality.ai API key not configured.'];
        }

        try {
            // Minimal scan to verify key — uses credits
            $response = $this->requestScan($apiKey, 'Test connection verification.', 'lite', 15, 'detector.connection_test');

            if ($response->successful()) {
                $data = $response->json();
                $credits = $data['results']['credits'] ?? $data['credits'] ?? null;
                $msg = 'Originality.ai API connected successfully.';
                if ($credits !== null) {
                    $msg .= ' Credits used: ' . $credits . '.';
                }
                return ['success' => true, 'message' => $msg];
            }

            $error = $response->json('error') ?? $response->json('message') ?? $response->body();
            return ['success' => false, 'message' => 'Originality.ai API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error))];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Originality.ai connection failed: ' . $e->getMessage()];
        }
    }

    private function requestScan(string $apiKey, string $text, string $model, int $timeout, string $operation): Response
    {
        $endpoint = (string) config('originality.api_url', 'https://api.originality.ai/api/v3/scan');
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
            $response = Http::withHeaders([
                'X-OAI-API-KEY' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($timeout)->post($endpoint, [
                'title' => $operation === 'detector.connection_test' ? 'Connection test' : 'Detection scan',
                'content' => $text,
                'check_ai' => true,
                'check_plagiarism' => false,
                'aiModelVersion' => $model,
            ]);

            $credits = $response->json('results.credits') ?? $response->json('credits');
            $usage = array_merge($units, ['credits_used' => is_numeric($credits) ? (float) $credits : null]);
            $attributes = [
                'provider_request_id' => $response->header('x-request-id'),
                'http_status' => $response->status(),
                'usage' => $usage,
                'response_metadata' => [
                    'classification' => $response->json('results.ai.classification') ?? $response->json('ai.classification'),
                    'ai_score' => $response->json('results.ai.score.ai') ?? $response->json('ai.score.ai'),
                    'original_score' => $response->json('results.ai.score.original') ?? $response->json('ai.score.original'),
                ],
            ];

            if ($response->successful()) {
                $span->succeed($attributes);
            } else {
                $span->fail((string) ($response->json('message') ?? 'Originality.ai request failed.'), array_merge($attributes, [
                    'error_type' => 'originality_http_error',
                ]));
            }

            return $response;
        } catch (\Throwable $e) {
            $span->fail($e, ['usage' => $units]);

            throw $e;
        }
    }
}
