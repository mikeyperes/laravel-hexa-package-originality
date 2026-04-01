<?php

namespace hexa_package_originality\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Models\Setting;
use hexa_package_originality\Services\OriginalityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * OriginalityController — settings + raw test page + detect endpoint.
 */
class OriginalityController extends Controller
{
    protected OriginalityService $service;

    /**
     * @param OriginalityService $service
     */
    public function __construct(OriginalityService $service)
    {
        $this->service = $service;
    }

    /**
     * Settings page.
     *
     * @return View
     */
    public function settings(): View
    {
        return view('originality::settings.index');
    }

    /**
     * Save settings.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function saveSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'api_key' => 'nullable|string|max:500',
            'enabled' => 'nullable|boolean',
            'debug_mode' => 'nullable|boolean',
        ]);

        if (isset($validated['api_key']) && !empty($validated['api_key'])) {
            Setting::setValue('originality_api_key', $validated['api_key']);
        }
        Setting::setValue('originality_enabled', $validated['enabled'] ?? true);
        Setting::setValue('originality_debug_mode', $validated['debug_mode'] ?? false);

        hexaLog('originality', 'settings_updated', 'Originality.ai  settings updated');

        return response()->json(['success' => true, 'message' => 'Settings saved.']);
    }

    /**
     * Test API connection.
     *
     * @return JsonResponse
     */
    public function testConnection(): JsonResponse
    {
        return response()->json($this->service->testConnection());
    }

    /**
     * Raw test page.
     *
     * @return View
     */
    public function raw(): View
    {
        return view('originality::raw.index');
    }

    /**
     * Detect AI content.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function detect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string|min:10',
        ]);

        return response()->json($this->service->detect($validated['text']));
    }
}
