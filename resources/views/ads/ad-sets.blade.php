@extends('layouts.app')
@section('title', 'Ad Sets - ' . $campaign->name)

@section('content')
<div class="space-y-6" x-data="{ showForm: false }">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('ads.index') }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-arrow-left"></i></a>
            <div>
                <h2 class="text-xl font-bold">{{ $campaign->name }}</h2>
                <p class="text-sm text-slate-500">{{ ucfirst($campaign->platform) }} Campaign - Ad Sets</p>
            </div>
        </div>
        <button @click="showForm = !showForm" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
            <i class="fas fa-plus mr-1"></i> Add Ad Set
        </button>
    </div>

    <div x-show="showForm" x-cloak x-transition class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <form method="POST" action="{{ route('ads.ad-sets.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf
            <input type="hidden" name="campaign_id" value="{{ $campaign->id }}">
            <div><label class="block text-xs font-medium mb-1">Name</label><input type="text" name="name" required class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            <div><label class="block text-xs font-medium mb-1">Budget ($)</label><input type="number" name="budget" step="0.01" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent"></div>
            <div class="flex items-end"><button type="submit" class="w-full py-2 bg-primary-600 text-white rounded-lg text-sm font-medium">Create</button></div>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($campaign->adSets as $adSet)
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold">{{ $adSet->name }}</h3>
                @php $sc = ['active' => 'emerald', 'paused' => 'amber', 'completed' => 'slate'][$adSet->status] ?? 'gray'; @endphp
                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $sc }}-100 text-{{ $sc }}-700">{{ ucfirst($adSet->status) }}</span>
            </div>
            <p class="text-sm text-slate-400 mb-2">Budget: ${{ number_format($adSet->budget ?? 0, 2) }}</p>
            <p class="text-sm text-slate-400 mb-4">{{ $adSet->ads->count() }} ads</p>
            <a href="{{ route('ads.ads', $adSet->id) }}" class="inline-flex px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                <i class="fas fa-ad mr-1"></i> View Ads
            </a>
        </div>
        @empty
        <div class="col-span-full text-center py-12 text-slate-400">
            <i class="fas fa-layer-group text-4xl mb-3"></i>
            <p>No ad sets yet. Create one to get started.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
