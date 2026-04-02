<?php

namespace hexa_package_originality\Services;

use hexa_core\Models\Setting;
use hexa_core\Services\GenericService;
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
            $response = Http::withHeaders([
                'X-OAI-API-KEY' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post(config('originality.api_url', 'https://api.originality.ai/api/v3/scan'), [
                'title' => 'Detection scan',
                'content' => $text,
                'check_ai' => true,
                'check_plagiarism' => false,
                'aiModelVersion' => 'turbo',
            ]);

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
            $response = Http::withHeaders([
                'X-OAI-API-KEY' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post(config('originality.api_url', 'https://api.originality.ai/api/v3/scan'), [
                'title' => 'Connection test',
                'content' => 'Test connection verification.',
                'check_ai' => true,
                'check_plagiarism' => false,
                'aiModelVersion' => 'lite',
            ]);

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
}
