@extends('layouts.app')
@section('title', \App\Helpers\Lang::t('invoice.facture'))

@php $L = \App\Helpers\Lang::class; @endphp

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h2 class="text-xl font-bold">{{ $L::t('invoice.facture') }}s</h2>
        <a href="{{ route('invoices.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-semibold hover:bg-primary-700 transition">
            <i class="fas fa-plus"></i> {{ $L::t('invoice.facture') }}
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium mb-1">{{ $L::t('common.search') }}</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ $L::t('common.search') }}..." class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent w-48">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">Type</label>
                <select name="type" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                    <option value="">{{ $L::t('common.all') }}</option>
                    @foreach($types as $t)
                    <option value="{{ $t }}" {{ request('type') == $t ? 'selected' : '' }}>{{ $L::t('invoice.' . $t, ucfirst($t)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">{{ $L::t('orders.status') }}</label>
                <select name="status" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
                    <option value="">{{ $L::t('common.all') }}</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>{{ $L::t('invoice.paid') }}</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>{{ $L::t('invoice.unpaid') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">{{ $L::t('invoice.date') }}</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">&nbsp;</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-transparent">
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium hover:bg-slate-300 dark:hover:bg-slate-600 transition">
                <i class="fas fa-filter mr-1"></i> {{ $L::t('common.filter') }}
            </button>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-semibold">{{ $L::t('invoice.number') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ $L::t('invoice.client') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ $L::t('invoice.date') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ $L::t('invoice.total_ttc') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ $L::t('invoice.payment_status') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ $L::t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($invoices as $invoice)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                        <td class="px-4 py-3 font-medium">{{ $invoice->invoice_number ?? 'INV-' . str_pad($invoice->id, 5, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-4 py-3">{{ $invoice->customer->name ?? $L::t('pos.walk_in') }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $invoice->created_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 font-semibold">{{ number_format($invoice->total, 2) }}</td>
                        <td class="px-4 py-3">
                            @if($invoice->status === 'completed')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">{{ $L::t('invoice.paid') }}</span>
                            @elseif($invoice->status === 'pending')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">{{ $L::t('invoice.unpaid') }}</span>
                            @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400">{{ ucfirst($invoice->status) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1">
                                <a href="{{ route('invoices.generate', ['saleId' => $invoice->id, 'type' => 'facture']) }}" class="p-1.5 text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded-lg transition" title="{{ $L::t('invoice.facture') }}">
                                    <i class="fas fa-file-invoice"></i>
                                </a>
                                <a href="{{ route('invoices.generate', ['saleId' => $invoice->id, 'type' => 'bon_livraison']) }}" class="p-1.5 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-lg transition" title="{{ $L::t('invoice.bon_livraison') }}">
                                    <i class="fas fa-truck"></i>
                                </a>
                                <a href="{{ route('invoices.generate', ['saleId' => $invoice->id, 'type' => 'devis']) }}" class="p-1.5 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition" title="{{ $L::t('invoice.devis') }}">
                                    <i class="fas fa-file-alt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                            <i class="fas fa-file-invoice text-3xl mb-2 block"></i>
                            Aucune facture / No invoices found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($invoices->hasPages())
        <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">
            {{ $invoices->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
