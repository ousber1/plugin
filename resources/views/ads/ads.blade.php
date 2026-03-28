@extends('layouts.app')
@section('title', 'Ads - ' . $adSet->name)

@section('content')
<div class="space-y-6" x-data="{ showAdForm: false, showMetricsForm: false, metricsAdId: null }">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('ads.ad-sets', $adSet->campaign->id) }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-arrow-left"></i></a>
            <div>
                <h2 class="text-xl font-bold">{{ $adSet->name }}</h2>
                <p class="text-sm text-slate-500">{{ $adSet->campaign->name }} - Ads</p>
            </div>
        </div>
        <button @click="showAdForm = !showAdForm" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700"><i class="fas fa-plus mr-1"></i> Add Ad</button>
    </div>

    {{-- New Ad Form --}}
    <div x-show="showAdForm" x-cloak x-transition class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form method="POST" action="{{ route('ads.ads.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="ad_set_id" value="{{ $adSet->id }}">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="block text-xs font-medium mb-1">Ad Name</label><input type="text" name="name" required class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                <div><label class="block text-xs font-medium mb-1">Headline</label><input type="text" name="headline" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                <div class="md:col-span-2"><label class="block text-xs font-medium mb-1">Body</label><textarea name="body" rows="3" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></textarea></div>
                <div><label class="block text-xs font-medium mb-1">CTA</label><input type="text" name="cta" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent" placeholder="e.g. Shop Now"></div>
                <div class="flex items-end"><button type="submit" class="w-full py-2 bg-primary-600 text-white rounded-lg text-sm font-medium">Create Ad</button></div>
            </div>
        </form>
    </div>

    {{-- Ads Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @forelse($adSet->ads as $ad)
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold">{{ $ad->name }}</h3>
                <div class="flex items-center gap-2">
                    @if($ad->analysis)
                    @php $rc = ['winner' => 'emerald', 'good' => 'blue', 'average' => 'amber', 'bad' => 'red'][$ad->analysis->rating] ?? 'gray'; @endphp
                    <span class="inline-flex px-2 py-0.5 text-xs font-bold rounded-full bg-{{ $rc }}-100 text-{{ $rc }}-700 uppercase">{{ $ad->analysis->rating }}</span>
                    @endif
                    @php $asc = ['active' => 'emerald', 'paused' => 'amber', 'completed' => 'slate'][$ad->status] ?? 'gray'; @endphp
                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $asc }}-100 text-{{ $asc }}-700">{{ ucfirst($ad->status) }}</span>
                </div>
            </div>

            @if($ad->headline)<p class="text-sm font-medium text-primary-600 mb-1">{{ $ad->headline }}</p>@endif
            @if($ad->body)<p class="text-sm text-slate-500 mb-2 line-clamp-2">{{ $ad->body }}</p>@endif
            @if($ad->cta)<span class="inline-flex px-2 py-0.5 text-xs bg-slate-100 dark:bg-slate-700 rounded mb-3">CTA: {{ $ad->cta }}</span>@endif

            {{-- Metrics Summary --}}
            @php $totalMetrics = $ad->getTotalMetrics(); @endphp
            @if($totalMetrics)
            <div class="grid grid-cols-4 gap-2 mt-3 p-3 bg-slate-50 dark:bg-slate-700/30 rounded-lg text-center">
                <div><p class="text-xs text-slate-400">Clicks</p><p class="text-sm font-bold">{{ number_format($totalMetrics['clicks']) }}</p></div>
                <div><p class="text-xs text-slate-400">CTR</p><p class="text-sm font-bold">{{ number_format($totalMetrics['ctr'], 1) }}%</p></div>
                <div><p class="text-xs text-slate-400">CPA</p><p class="text-sm font-bold">${{ number_format($totalMetrics['cpa'], 2) }}</p></div>
                <div><p class="text-xs text-slate-400">ROI</p><p class="text-sm font-bold {{ $totalMetrics['roi'] > 0 ? 'text-emerald-600' : 'text-red-500' }}">{{ number_format($totalMetrics['roi'], 1) }}%</p></div>
            </div>
            @endif

            {{-- AI Suggestions --}}
            @if($ad->analysis && $ad->analysis->ai_suggestions)
            <div class="mt-3 p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
                <p class="text-xs font-medium text-purple-700 dark:text-purple-400 mb-1"><i class="fas fa-magic mr-1"></i> AI Suggestions</p>
                <p class="text-xs text-purple-600 dark:text-purple-300 line-clamp-3">{{ $ad->analysis->ai_suggestions }}</p>
            </div>
            @endif

            {{-- Actions --}}
            <div class="flex gap-2 mt-4">
                <button @click="metricsAdId = {{ $ad->id }}; showMetricsForm = true" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-700 rounded-lg text-xs font-medium hover:bg-slate-200">
                    <i class="fas fa-chart-bar mr-1"></i> Add Metrics
                </button>
                <form method="POST" action="{{ route('ads.analyze', $ad->id) }}" class="inline">@csrf
                    <button type="submit" class="px-3 py-1.5 bg-amber-100 text-amber-700 rounded-lg text-xs font-medium hover:bg-amber-200"><i class="fas fa-brain mr-1"></i> Analyze</button>
                </form>
                <form method="POST" action="{{ route('ads.ai-suggestions', $ad->id) }}" class="inline">@csrf
                    <button type="submit" class="px-3 py-1.5 bg-purple-100 text-purple-700 rounded-lg text-xs font-medium hover:bg-purple-200"><i class="fas fa-magic mr-1"></i> AI</button>
                </form>
            </div>
        </div>
        @empty
        <div class="col-span-full text-center py-12 text-slate-400">
            <i class="fas fa-ad text-4xl mb-3"></i><p>No ads yet</p>
        </div>
        @endforelse
    </div>

    {{-- Metrics Modal --}}
    <div x-show="showMetricsForm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showMetricsForm = false">
        <form method="POST" action="{{ route('ads.metrics.store') }}" class="bg-white dark:bg-slate-800 rounded-xl p-6 w-full max-w-md" x-transition>
            @csrf
            <h3 class="text-lg font-bold mb-4">Add Metrics</h3>
            <input type="hidden" name="ad_id" :value="metricsAdId">
            <div class="space-y-3">
                <div><label class="block text-xs font-medium mb-1">Date</label><input type="date" name="date" required value="{{ date('Y-m-d') }}" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs font-medium mb-1">Impressions</label><input type="number" name="impressions" required min="0" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                    <div><label class="block text-xs font-medium mb-1">Clicks</label><input type="number" name="clicks" required min="0" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                    <div><label class="block text-xs font-medium mb-1">Cost ($)</label><input type="number" name="cost" required min="0" step="0.01" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                    <div><label class="block text-xs font-medium mb-1">Conversions</label><input type="number" name="conversions" required min="0" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                    <div class="col-span-2"><label class="block text-xs font-medium mb-1">Revenue ($)</label><input type="number" name="revenue" required min="0" step="0.01" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
                </div>
            </div>
            <div class="flex gap-3 mt-4">
                <button type="button" @click="showMetricsForm = false" class="flex-1 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm">Cancel</button>
                <button type="submit" class="flex-1 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium">Save Metrics</button>
            </div>
        </form>
    </div>
</div>
@endsection
