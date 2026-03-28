@extends('layouts.app')
@section('title', 'Ads Analytics')

@section('content')
<div class="space-y-6" x-data="{ showCampaignForm: false }">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold">Ads Analytics</h2>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('ads.analyze-all') }}" class="inline">@csrf<button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm font-medium hover:bg-amber-700"><i class="fas fa-brain mr-1"></i> Analyze All</button></form>
            <form method="POST" action="{{ route('ads.sync-meta') }}" class="inline">@csrf<button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700"><i class="fab fa-facebook mr-1"></i> Sync Meta</button></form>
            <button @click="showCampaignForm = !showCampaignForm" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700"><i class="fas fa-plus mr-1"></i> New Campaign</button>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="stat-card text-center">
            <p class="text-xs text-slate-500">Total Spend</p>
            <p class="text-xl font-bold text-red-500">${{ number_format($totalSpend ?? 0, 2) }}</p>
        </div>
        <div class="stat-card text-center">
            <p class="text-xs text-slate-500">Total Revenue</p>
            <p class="text-xl font-bold text-emerald-600">${{ number_format($totalRevenue ?? 0, 2) }}</p>
        </div>
        <div class="stat-card text-center">
            <p class="text-xs text-slate-500">Conversions</p>
            <p class="text-xl font-bold">{{ number_format($totalConversions ?? 0) }}</p>
        </div>
        <div class="stat-card text-center">
            <p class="text-xs text-slate-500">Avg ROI</p>
            <p class="text-xl font-bold {{ ($avgRoi ?? 0) > 0 ? 'text-emerald-600' : 'text-red-500' }}">{{ number_format($avgRoi ?? 0, 1) }}%</p>
        </div>
        <div class="stat-card text-center">
            <p class="text-xs text-slate-500">Avg CPA</p>
            <p class="text-xl font-bold">${{ number_format($avgCpa ?? 0, 2) }}</p>
        </div>
        <div class="stat-card text-center">
            <p class="text-xs text-slate-500">Avg CTR</p>
            <p class="text-xl font-bold">{{ number_format($avgCtr ?? 0, 2) }}%</p>
        </div>
    </div>

    {{-- New Campaign Form --}}
    <div x-show="showCampaignForm" x-cloak x-transition class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="text-sm font-semibold mb-4">New Campaign</h3>
        <form method="POST" action="{{ route('ads.campaigns.store') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            @csrf
            <div><label class="block text-xs font-medium mb-1">Name</label><input type="text" name="name" required class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            <div><label class="block text-xs font-medium mb-1">Platform</label><select name="platform" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"><option value="meta">Meta</option><option value="google">Google</option><option value="tiktok">TikTok</option><option value="other">Other</option></select></div>
            <div><label class="block text-xs font-medium mb-1">Budget ($)</label><input type="number" name="budget" step="0.01" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            <div><label class="block text-xs font-medium mb-1">Start Date</label><input type="date" name="start_date" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            <div class="flex items-end"><button type="submit" class="w-full py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">Create</button></div>
        </form>
    </div>

    {{-- Campaigns --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-sm font-semibold">Campaigns</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/30 text-xs text-slate-500">
                <tr><th class="px-6 py-3 text-left">Campaign</th><th class="px-6 py-3 text-center">Platform</th><th class="px-6 py-3 text-center">Status</th><th class="px-6 py-3 text-right">Budget</th><th class="px-6 py-3 text-center">Ad Sets</th><th class="px-6 py-3 text-center">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($campaigns as $campaign)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20">
                    <td class="px-6 py-3 font-medium">{{ $campaign->name }}</td>
                    <td class="px-6 py-3 text-center">
                        @php $platformColors = ['meta' => 'blue', 'google' => 'red', 'tiktok' => 'pink', 'other' => 'gray']; $pc = $platformColors[$campaign->platform] ?? 'gray'; @endphp
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $pc }}-100 text-{{ $pc }}-700 dark:bg-{{ $pc }}-900/30 dark:text-{{ $pc }}-400">{{ ucfirst($campaign->platform) }}</span>
                    </td>
                    <td class="px-6 py-3 text-center">
                        @php $sc = ['active' => 'emerald', 'paused' => 'amber', 'completed' => 'slate', 'draft' => 'blue'][$campaign->status] ?? 'gray'; @endphp
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $sc }}-100 text-{{ $sc }}-700">{{ ucfirst($campaign->status) }}</span>
                    </td>
                    <td class="px-6 py-3 text-right">${{ number_format($campaign->budget ?? 0, 2) }}</td>
                    <td class="px-6 py-3 text-center">{{ $campaign->ad_sets_count }}</td>
                    <td class="px-6 py-3 text-center">
                        <a href="{{ route('ads.ad-sets', $campaign->id) }}" class="text-primary-600 hover:text-primary-800 text-xs font-medium">Manage</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-6 py-8 text-center text-slate-400">No campaigns yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Recent Analysis --}}
    @if(isset($recentAnalysis) && $recentAnalysis->count() > 0)
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700"><h3 class="text-sm font-semibold">Recent Ad Analysis</h3></div>
        <div class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($recentAnalysis as $analysis)
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-medium truncate">{{ $analysis->ad->name ?? 'Ad' }}</p>
                    @php $rc = ['winner' => 'emerald', 'good' => 'blue', 'average' => 'amber', 'bad' => 'red'][$analysis->rating] ?? 'gray'; @endphp
                    <span class="inline-flex px-2 py-0.5 text-xs font-bold rounded-full bg-{{ $rc }}-100 text-{{ $rc }}-700 uppercase">{{ $analysis->rating }}</span>
                </div>
                @if($analysis->recommendations)
                <p class="text-xs text-slate-400 line-clamp-2">{{ $analysis->recommendations }}</p>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
