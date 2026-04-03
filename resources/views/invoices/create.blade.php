@extends('layouts.app')
@section('title', \App\Helpers\Lang::t('invoice.facture'))

@php $L = \App\Helpers\Lang::class; @endphp

@section('content')
<div class="max-w-4xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('invoices.index') }}" class="p-2 text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2 class="text-xl font-bold">{{ $L::t('invoice.facture') }} - {{ $L::t('orders.create') }}</h2>
    </div>

    @if($sale)
    {{-- Pre-filled from existing sale --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-4">
        <h3 class="font-semibold text-sm border-b border-slate-200 dark:border-slate-700 pb-3">
            <i class="fas fa-file-invoice mr-2 text-primary-500"></i>
            {{ $L::t('invoice.facture') }} - {{ $sale->invoice_number ?? 'INV-' . str_pad($sale->id, 5, '0', STR_PAD_LEFT) }}
        </h3>

        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-slate-500">{{ $L::t('invoice.client') }}:</span>
                <span class="font-medium ml-1">{{ $sale->customer->name ?? $L::t('pos.walk_in') }}</span>
            </div>
            <div>
                <span class="text-slate-500">{{ $L::t('invoice.date') }}:</span>
                <span class="font-medium ml-1">{{ $sale->created_at->format('d/m/Y') }}</span>
            </div>
            <div>
                <span class="text-slate-500">{{ $L::t('invoice.total_ttc') }}:</span>
                <span class="font-bold ml-1">{{ number_format($sale->total, 2) }}</span>
            </div>
            <div>
                <span class="text-slate-500">{{ $L::t('invoice.payment_status') }}:</span>
                <span class="font-medium ml-1">{{ $sale->status === 'completed' ? $L::t('invoice.paid') : $L::t('invoice.unpaid') }}</span>
            </div>
        </div>

        {{-- Items --}}
        <table class="w-full text-sm mt-4">
            <thead class="bg-slate-50 dark:bg-slate-700/50">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold">{{ $L::t('invoice.description') }}</th>
                    <th class="px-3 py-2 text-center font-semibold">{{ $L::t('invoice.quantity') }}</th>
                    <th class="px-3 py-2 text-right font-semibold">{{ $L::t('invoice.unit_price') }}</th>
                    <th class="px-3 py-2 text-right font-semibold">{{ $L::t('invoice.total_ht') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                @foreach($sale->items as $item)
                <tr>
                    <td class="px-3 py-2">{{ $item->product->name ?? 'Product' }}</td>
                    <td class="px-3 py-2 text-center">{{ $item->quantity }}</td>
                    <td class="px-3 py-2 text-right">{{ number_format($item->price, 2) }}</td>
                    <td class="px-3 py-2 text-right font-medium">{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Generate buttons --}}
        <div class="flex flex-wrap gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
            <span class="text-sm font-medium text-slate-500 self-center mr-2">{{ $L::t('common.export') }}:</span>
            <a href="{{ route('invoices.generate', ['saleId' => $sale->id, 'type' => 'facture']) }}" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-semibold hover:bg-primary-700 transition">
                <i class="fas fa-file-invoice mr-1"></i> {{ $L::t('invoice.facture') }}
            </a>
            <a href="{{ route('invoices.generate', ['saleId' => $sale->id, 'type' => 'proforma']) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
                <i class="fas fa-file mr-1"></i> {{ $L::t('invoice.proforma') }}
            </a>
            <a href="{{ route('invoices.generate', ['saleId' => $sale->id, 'type' => 'bon_livraison']) }}" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 transition">
                <i class="fas fa-truck mr-1"></i> {{ $L::t('invoice.bon_livraison') }}
            </a>
            <a href="{{ route('invoices.generate', ['saleId' => $sale->id, 'type' => 'devis']) }}" class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm font-semibold hover:bg-amber-700 transition">
                <i class="fas fa-file-alt mr-1"></i> {{ $L::t('invoice.devis') }}
            </a>
        </div>
    </div>
    @else
    {{-- Select a sale to generate invoice --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <p class="text-slate-500 text-sm mb-4">Select a sale/order to generate an invoice:</p>
        <a href="{{ route('invoices.index') }}" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm font-medium hover:bg-slate-300 dark:hover:bg-slate-600 transition">
            <i class="fas fa-arrow-left mr-1"></i> {{ $L::t('common.back') }}
        </a>
    </div>
    @endif
</div>
@endsection
