@extends('layouts.app')
@section('title', 'Originality.ai — Raw Test')
@section('header', 'Originality.ai — Raw Test')

@section('content')
<div class="max-w-4xl mx-auto space-y-4" x-data="originalityRaw()">

    {{-- Functions Index --}}
    <div class="bg-gray-900 rounded-xl p-6 text-sm font-mono">
        <h2 class="text-white font-semibold mb-3">Originality.ai Functions</h2>
        <table class="w-full text-left">
            <thead><tr class="text-gray-400 border-b border-gray-700"><th class="py-1.5 px-2">Function</th><th class="py-1.5 px-2">Method</th><th class="py-1.5 px-2">Route</th><th class="py-1.5 px-2">Status</th></tr></thead>
            <tbody class="text-gray-300">
                <tr class="border-b border-gray-800"><td class="py-1.5 px-2">Detect AI content</td><td class="py-1.5 px-2 text-blue-400">detect()</td><td class="py-1.5 px-2 text-green-400">POST /originality/detect</td><td class="py-1.5 px-2 text-green-400">LIVE</td></tr>
                <tr class="border-b border-gray-800"><td class="py-1.5 px-2">Test connection</td><td class="py-1.5 px-2 text-blue-400">testConnection()</td><td class="py-1.5 px-2 text-green-400">POST /originality/test</td><td class="py-1.5 px-2 text-green-400">LIVE</td></tr>
            </tbody>
        </table>
    </div>

    {{-- Test Input --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-3">Test AI Detection</h3>
        <textarea x-model="text" rows="8" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm" placeholder="Paste text to check for AI content..."></textarea>
        <div class="flex items-center gap-3 mt-3">
            <button @click="detect()" :disabled="detecting" class="bg-purple-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-purple-700 disabled:opacity-50 inline-flex items-center gap-2">
                <svg x-show="detecting" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="detecting ? 'Analyzing...' : 'Detect AI'"></span>
            </button>
            <span x-show="result" x-cloak class="text-sm" :class="result?.success ? 'text-green-600' : 'text-red-600'" x-text="result?.message"></span>
        </div>
    </div>

    {{-- Results --}}
    <div x-show="result?.success" x-cloak class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-3">Results</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <div class="text-center">
                <p class="text-2xl font-bold" :class="(result?.data?.completely_generated_prob || 0) > 0.5 ? 'text-red-600' : 'text-green-600'" x-text="Math.round((result?.data?.completely_generated_prob || 0) * 100) + '%'"></p>
                <p class="text-xs text-gray-500">AI Generated</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-800" x-text="Math.round((result?.data?.average_generated_prob || 0) * 100) + '%'"></p>
                <p class="text-xs text-gray-500">Avg Sentence Prob</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-800" x-text="Math.round(result?.data?.overall_burstiness || 0)"></p>
                <p class="text-xs text-gray-500">Burstiness</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-800" x-text="result?.data?.predicted_class || '—'"></p>
                <p class="text-xs text-gray-500">Classification</p>
            </div>
        </div>

        {{-- Per-sentence --}}
        <div x-show="result?.data?.sentences?.length > 0" class="space-y-1">
            <h4 class="text-sm font-semibold text-gray-700 mb-2">Per-Sentence Analysis</h4>
            <template x-for="(s, idx) in (result?.data?.sentences || [])" :key="idx">
                <div class="flex items-start gap-2 text-sm p-2 rounded" :class="(s.generated_prob || 0) > 0.5 ? 'bg-red-50' : 'bg-green-50'">
                    <span class="text-xs font-mono px-1.5 py-0.5 rounded flex-shrink-0" :class="(s.generated_prob || 0) > 0.5 ? 'bg-red-200 text-red-800' : 'bg-green-200 text-green-800'" x-text="Math.round((s.generated_prob || 0) * 100) + '%'"></span>
                    <span class="text-gray-700 break-words" x-text="s.sentence"></span>
                </div>
            </template>
        </div>
    </div>
</div>

@push('scripts')
<script>
function originalityRaw() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    return {
        text: '', detecting: false, result: null,
        async detect() {
            if (!this.text.trim()) return;
            this.detecting = true; this.result = null;
            try { const r = await fetch('{{ route("originality.detect") }}', { method: 'POST', headers, body: JSON.stringify({ text: this.text }) }); this.result = await r.json(); } catch(e) { this.result = { success: false, message: 'Request failed' }; }
            this.detecting = false;
        }
    };
}
</script>
@endpush
@endsection
