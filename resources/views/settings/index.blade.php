@extends('layouts.app')
@section('title', 'Originality.ai Settings')
@section('header', 'Originality.ai Settings')

@section('content')
<div class="max-w-2xl mx-auto space-y-6" x-data="originalitySettings()">

    {{-- Setup Instructions --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-6">
        <h2 class="text-lg font-semibold text-blue-900 mb-2">Setup Instructions</h2>
        <ol class="list-decimal list-inside text-sm text-blue-800 space-y-1">
            <li>Create an account at <a href="https://app.originality.ai/signup" target="_blank" class="underline inline-flex items-center gap-1">app.originality.ai <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a> and subscribe to Enterprise plan</li>
            <li>Go to <a href="https://app.originality.ai/home/api-token-dashboard" target="_blank" class="underline inline-flex items-center gap-1">API Token Dashboard <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a> and generate your API key</li>
            <li>Paste below and click Test to verify</li>
        </ol>
        <p class="text-xs text-blue-600 mt-2">Docs: <a href="https://docs.originality.ai/api-v2-0-new" target="_blank" class="underline inline-flex items-center gap-1">API v2 docs <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a> | Rate limit: 500 req/min. Disable auto top-up in <a href="https://app.originality.ai/home/account#topup" target="_blank" class="underline inline-flex items-center gap-1">account settings <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>.</p>
    </div>

    {{-- API Key (core component) --}}
    <x-hexa-credential-field
        slug="originality"
        key-name="api_key"
        label="Originality.ai API Key"
        :test-url="route('originality.test')"
        help="Uses X-OAI-API-KEY header. Get your key from the API Token Dashboard."
    />

    {{-- Settings --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">
        <h3 class="font-semibold text-gray-800">Settings</h3>

        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-700">Enable Originality.ai</p>
                <p class="text-xs text-gray-400">Include in pipeline AI detection scans</p>
            </div>
            <button @click="enabled = !enabled" type="button"
                class="relative inline-flex h-7 w-14 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                :class="enabled ? 'bg-green-500' : 'bg-gray-300'"
                role="switch" :aria-checked="enabled">
                <span class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    :class="enabled ? 'translate-x-7' : 'translate-x-0'"></span>
            </button>
        </div>

        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-700">Debug Mode</p>
                <p class="text-xs text-gray-400">Sends only first 3 sentences to save credits</p>
            </div>
            <button @click="debugMode = !debugMode" type="button"
                class="relative inline-flex h-7 w-14 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                :class="debugMode ? 'bg-yellow-500' : 'bg-gray-300'"
                role="switch" :aria-checked="debugMode">
                <span class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    :class="debugMode ? 'translate-x-7' : 'translate-x-0'"></span>
            </button>
        </div>

        <button @click="save()" :disabled="saving" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 inline-flex items-center gap-2">
            <svg x-show="saving" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span x-text="saving ? 'Saving...' : (saved ? 'Saved!' : 'Save Settings')"></span>
        </button>

        <div x-show="saveResult" x-cloak class="p-3 rounded-lg text-sm border" :class="saveSuccess ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'" x-text="saveResult"></div>
    </div>

    {{-- Pages --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-3">Pages</h3>
        <div class="space-y-2">
            <a href="{{ route('originality.settings') }}" class="flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Settings
            </a>
            <a href="{{ route('originality.raw') }}" class="flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                Raw Test Page
            </a>
        </div>
    </div>
</div>

@push('scripts')
<script>
function originalitySettings() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    return {
        enabled: {{ \hexa_core\Models\Setting::getValue('originality_enabled', true) ? 'true' : 'false' }},
        debugMode: {{ \hexa_core\Models\Setting::getValue('originality_debug_mode', false) ? 'true' : 'false' }},
        saving: false, saved: false, saveResult: '', saveSuccess: false,
        async save() {
            this.saving = true; this.saved = false; this.saveResult = '';
            try {
                const r = await fetch('{{ route("originality.settings.save") }}', { method: 'POST', headers, body: JSON.stringify({ enabled: this.enabled, debug_mode: this.debugMode }) });
                const d = await r.json();
                this.saveSuccess = d.success;
                this.saveResult = d.message || (d.success ? 'Settings saved.' : 'Save failed.');
                this.saved = d.success;
                setTimeout(() => { this.saved = false; this.saveResult = ''; }, 3000);
            } catch(e) { this.saveSuccess = false; this.saveResult = 'Network error.'; }
            this.saving = false;
        },
    };
}
</script>
@endpush
@endsection
