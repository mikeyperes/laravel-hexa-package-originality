@extends('layouts.app')
@section('title', 'Originality.ai Settings')
@section('header', 'Originality.ai Settings')

@section('content')
<div class="max-w-2xl mx-auto space-y-6" x-data="originalitySettings()">

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-1">Originality.ai AI Detection</h3>
        <p class="text-sm text-gray-500 mb-4">Detect AI-generated content with per-sentence probability scoring.</p>

        <div class="bg-blue-50 rounded-lg p-4 text-sm text-blue-800 mb-4 space-y-2">
            <p class="font-semibold">Setup Instructions</p>
            <ol class="list-decimal list-inside space-y-1 text-blue-700">
                <li>Create an account at <a href="https://app.originality.ai/signup" target="_blank" class="underline inline-flex items-center gap-1">app.originality.ai <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a> and subscribe to Enterprise plan</li>
                <li>Go to <a href="https://app.originality.ai/home/api-token-dashboard" target="_blank" class="underline inline-flex items-center gap-1">API Token Dashboard <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a> and generate your API key</li>
                <li>Paste the key below and click Test to verify</li>
            </ol>
            <p class="text-xs text-blue-600">Docs: <a href="https://docs.originality.ai/api-v2-0-new" target="_blank" class="underline inline-flex items-center gap-1">API v2 docs <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a> | Rate limit: 500 req/min. Disable auto top-up in <a href="https://app.originality.ai/home/account#topup" target="_blank" class="underline inline-flex items-center gap-1">account settings <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>.</p>
        </div>

        <div class="space-y-4">
            {{-- API Key --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">API Key</label>
                <div class="flex gap-2">
                    <input type="password" x-model="apiKey" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="{{ \hexa_core\Models\Setting::getValue('originality_api_key') ? '••••••••' : 'Enter API key' }}">
                    <button @click="testApi()" :disabled="testing" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                        <svg x-show="testing" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="testing ? 'Testing...' : 'Test'"></span>
                    </button>
                </div>
                <p x-show="testResult" x-cloak class="mt-1 text-xs" :class="testSuccess ? 'text-green-600' : 'text-red-600'" x-text="testResult"></p>
            </div>

            {{-- Enable/Disable --}}
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" x-model="enabled" class="rounded border-gray-300 text-blue-600">
                <span class="text-sm text-gray-700">Enable Originality.ai</span>
            </label>

            {{-- Debug Mode --}}
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" x-model="debugMode" class="rounded border-gray-300 text-yellow-600">
                <span class="text-sm text-gray-700">Debug Mode <span class="text-gray-400">(sends only first 3 sentences to save API credits)</span></span>
            </label>

            <button @click="save()" :disabled="saving" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
                <svg x-show="saving" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="saving ? 'Saving...' : (saved ? 'Saved!' : 'Save Settings')"></span>
            </button>
        </div>
    </div>

    <div class="text-center">
        <a href="{{ route('originality.raw') }}" class="text-sm text-blue-600 hover:underline">Open Raw Test Page &rarr;</a>
    </div>
</div>

@push('scripts')
<script>
function originalitySettings() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    return {
        apiKey: '', enabled: {{ \hexa_core\Models\Setting::getValue('originality_enabled', true) ? 'true' : 'false' }}, debugMode: {{ \hexa_core\Models\Setting::getValue('originality_debug_mode', false) ? 'true' : 'false' }},
        saving: false, saved: false, testing: false, testResult: '', testSuccess: false,
        async save() {
            this.saving = true; this.saved = false;
            try { const r = await fetch('{{ route("originality.settings.save") }}', { method: 'POST', headers, body: JSON.stringify({ api_key: this.apiKey || null, enabled: this.enabled, debug_mode: this.debugMode }) }); const d = await r.json(); this.saved = d.success; setTimeout(() => this.saved = false, 3000); } catch(e) {}
            this.saving = false;
        },
        async testApi() {
            this.testing = true; this.testResult = '';
            if (this.apiKey) await this.save();
            try { const r = await fetch('{{ route("originality.test") }}', { method: 'POST', headers }); const d = await r.json(); this.testSuccess = d.success; this.testResult = d.message; } catch(e) { this.testSuccess = false; this.testResult = 'Request failed'; }
            this.testing = false;
        }
    };
}
</script>
@endpush
@endsection
