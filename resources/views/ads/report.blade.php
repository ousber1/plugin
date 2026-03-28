@extends('layouts.app')
@section('title', 'Ads Report')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('ads.index') }}" class="text-slate-400 hover:text-slate-600"><i class="fas fa-arrow-left"></i></a>
            <h2 class="text-xl font-bold">Ads Performance Report</h2>
        </div>
    </div>

    <form method="GET" class="flex gap-3 bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
        <input type="date" name="start_date" value="{{ $startDate }}" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
        <input type="date" name="end_date" value="{{ $endDate }}" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">Filter</button>
    </form>

    {{-- Platform Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($platformStats as $platform => $stats)
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="font-semibold text-sm mb-3">{{ ucfirst($platform) }}</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Spend</span><span class="font-bold text-red-500">${{ number_format($stats['spend'], 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Revenue</span><span class="font-bold text-emerald-600">${{ number_format($stats['revenue'], 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Clicks</span><span class="font-bold">{{ number_format($stats['clicks']) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Conversions</span><span class="font-bold">{{ number_format($stats['conversions']) }}</span></div>
                @php $roi = $stats['spend'] > 0 ? (($stats['revenue'] - $stats['spend']) / $stats['spend']) * 100 : 0; @endphp
                <div class="flex justify-between border-t border-slate-200 dark:border-slate-700 pt-2"><span class="text-slate-500">ROI</span><span class="font-bold {{ $roi > 0 ? 'text-emerald-600' : 'text-red-500' }}">{{ number_format($roi, 1) }}%</span></div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection
