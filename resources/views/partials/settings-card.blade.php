@push('settings-cards')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Originality.ai </h3>
        <span class="text-xs text-gray-400">v{{ config('originality.version', '?') }}</span>
    </div>
    <p class="text-sm text-gray-600 mb-4">AI content detection with per-sentence probability scoring. Free: 10k words/month.</p>
    <a href="{{ route('originality.settings') }}" class="text-blue-600 hover:underline text-sm">Settings &rarr;</a>
</div>
@endpush
