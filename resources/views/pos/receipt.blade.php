@php
    $L = \App\Helpers\Lang::class;
    $isFr = $L::locale() === 'fr';
    $currency = $settings['currency'] ?? 'DH';
    $widthClass = 'receipt-' . str_replace('mm', 'mm', $settings['receipt_width'] ?? '80mm');
@endphp
<!DOCTYPE html>
<html lang="{{ $L::locale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $isFr ? 'Reçu' : 'Receipt' }} - {{ $sale->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
            background: #f5f5f5;
            padding: 20px;
        }
        .receipt {
            background: #fff;
            margin: 0 auto;
            padding: 20px 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .receipt-58mm { max-width: 220px; font-size: 11px; }
        .receipt-80mm { max-width: 300px; font-size: 12px; }
        .receipt-a4 { max-width: 700px; font-size: 13px; padding: 40px; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .divider { border-top: 1px dashed #999; margin: 8px 0; }
        .double-divider { border-top: 2px solid #333; margin: 8px 0; }
        .row { display: flex; justify-content: space-between; padding: 2px 0; }
        .logo { max-height: 60px; max-width: 180px; margin: 0 auto 8px; display: block; object-fit: contain; }
        .store-name { font-size: 18px; font-weight: bold; margin-bottom: 2px; letter-spacing: 1px; }
        .receipt-a4 .store-name { font-size: 24px; }
        .info-text { font-size: 10px; color: #555; line-height: 1.5; }
        .receipt-a4 .info-text { font-size: 12px; }

        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th { text-align: left; padding: 4px 0; font-size: 11px; border-bottom: 1px solid #333; }
        .items-table td { padding: 3px 0; vertical-align: top; }
        .items-table .right { text-align: right; }
        .receipt-a4 .items-table th { font-size: 13px; padding: 6px 4px; }
        .receipt-a4 .items-table td { padding: 5px 4px; }

        .total-section .row { padding: 3px 0; }
        .grand-total { font-size: 16px; font-weight: bold; }
        .receipt-a4 .grand-total { font-size: 20px; }

        .payment-badge {
            display: inline-block;
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .footer-text { font-size: 10px; color: #666; line-height: 1.5; margin-top: 4px; }
        .receipt-a4 .footer-text { font-size: 12px; }

        .fiscal-info { font-size: 9px; color: #888; line-height: 1.6; margin-top: 6px; }

        .actions { text-align: center; margin-top: 25px; }
        .actions button {
            padding: 10px 28px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            margin: 0 5px;
            transition: all 0.2s;
        }
        .btn-print { background: #4f46e5; color: #fff; }
        .btn-print:hover { background: #4338ca; }
        .btn-back { background: #e2e8f0; color: #475569; }
        .btn-back:hover { background: #cbd5e1; }
        .btn-invoice { background: #059669; color: #fff; }
        .btn-invoice:hover { background: #047857; }

        @media print {
            body { background: #fff; padding: 0; }
            .receipt { box-shadow: none; max-width: 100%; }
            .actions, .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="receipt {{ $widthClass }}">
        {{-- Header --}}
        <div class="center">
            @if(($settings['receipt_show_logo'] ?? '1') == '1' && !empty($settings['receipt_logo']))
                <img src="{{ asset('storage/' . $settings['receipt_logo']) }}" alt="Logo" class="logo">
            @endif

            <div class="store-name">{{ $settings['store_name'] ?? 'OmniChannel Store' }}</div>

            @if(!empty($settings['store_address']))
                <div class="info-text">{{ $settings['store_address'] }}</div>
            @endif
            @if(!empty($settings['store_phone']))
                <div class="info-text">{{ $isFr ? 'Tél' : 'Tel' }}: {{ $settings['store_phone'] }}</div>
            @endif
            @if(!empty($settings['store_email']))
                <div class="info-text">{{ $settings['store_email'] }}</div>
            @endif

            @if(!empty($settings['receipt_header']))
                <div class="info-text" style="margin-top: 4px;">{{ $settings['receipt_header'] }}</div>
            @endif

            {{-- Moroccan fiscal IDs --}}
            @if(!empty($settings['ice']) || !empty($settings['if_number']))
            <div class="fiscal-info">
                @if(!empty($settings['ice']))ICE: {{ $settings['ice'] }}@endif
                @if(!empty($settings['if_number'])) | IF: {{ $settings['if_number'] }}@endif
                @if(!empty($settings['rc'])) | RC: {{ $settings['rc'] }}@endif
            </div>
            @endif
        </div>

        <div class="double-divider"></div>

        {{-- Receipt Info --}}
        <div style="margin-bottom: 4px;">
            <div class="row"><span>{{ $isFr ? 'N° Facture' : 'Invoice' }}:</span><span class="bold">{{ $sale->invoice_number }}</span></div>
            <div class="row"><span>{{ $isFr ? 'Date' : 'Date' }}:</span><span>{{ $sale->created_at->format('d/m/Y H:i') }}</span></div>
            <div class="row"><span>{{ $isFr ? 'Caissier' : 'Cashier' }}:</span><span>{{ $sale->user->name ?? 'N/A' }}</span></div>
            @if($sale->customer)
            <div class="row"><span>{{ $isFr ? 'Client' : 'Customer' }}:</span><span>{{ $sale->customer->name }}</span></div>
            @if($sale->customer->phone)
            <div class="row"><span>{{ $isFr ? 'Tél' : 'Phone' }}:</span><span>{{ $sale->customer->phone }}</span></div>
            @endif
            @endif
            <div class="row"><span>{{ $isFr ? 'Canal' : 'Channel' }}:</span><span>{{ strtoupper($sale->channel) }}</span></div>
        </div>

        <div class="divider"></div>

        {{-- Items --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th>{{ $isFr ? 'Article' : 'Item' }}</th>
                    <th class="right" style="width:35px">{{ $isFr ? 'Qté' : 'Qty' }}</th>
                    <th class="right" style="width:55px">{{ $isFr ? 'P.U.' : 'Price' }}</th>
                    <th class="right" style="width:60px">{{ $isFr ? 'Total' : 'Total' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $item)
                <tr>
                    <td>{{ Str::limit($item->product->name ?? 'Product', 20) }}</td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="right">{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="divider"></div>

        {{-- Totals --}}
        <div class="total-section">
            <div class="row">
                <span>{{ $isFr ? 'Sous-total HT' : 'Subtotal' }}:</span>
                <span>{{ number_format($sale->subtotal, 2) }} {{ $currency }}</span>
            </div>
            @if($sale->discount > 0)
            <div class="row" style="color:#c53030;">
                <span>{{ $isFr ? 'Remise' : 'Discount' }}:</span>
                <span>-{{ number_format($sale->discount, 2) }} {{ $currency }}</span>
            </div>
            @endif
            @if($sale->tax > 0)
            <div class="row">
                <span>TVA:</span>
                <span>{{ number_format($sale->tax, 2) }} {{ $currency }}</span>
            </div>
            @endif

            <div class="double-divider"></div>

            <div class="row grand-total">
                <span>{{ $isFr ? 'TOTAL TTC' : 'TOTAL' }}:</span>
                <span>{{ number_format($sale->total, 2) }} {{ $currency }}</span>
            </div>
        </div>

        <div class="divider"></div>

        {{-- Payments --}}
        @php
            $methodNames = [
                'cash' => $isFr ? 'ESPECES' : 'CASH',
                'card' => $isFr ? 'CARTE' : 'CARD',
                'bank_transfer' => $isFr ? 'VIREMENT' : 'TRANSFER',
            ];
        @endphp
        <div class="bold" style="margin-bottom: 4px; font-size: 11px;">{{ $isFr ? 'PAIEMENTS' : 'PAYMENTS' }}:</div>
        @foreach($sale->payments as $payment)
        <div class="row">
            <span>
                <span class="payment-badge">{{ $methodNames[$payment->method] ?? strtoupper($payment->method) }}</span>
                @if($payment->reference)
                    <span style="font-size:9px; color:#888;"> {{ $isFr ? 'Réf' : 'Ref' }}: {{ $payment->reference }}</span>
                @endif
            </span>
            <span class="bold">{{ number_format($payment->amount, 2) }} {{ $currency }}</span>
        </div>
        @endforeach

        @php
            $totalPaid = $sale->payments->sum('amount');
            $change = $totalPaid - $sale->total;
        @endphp

        @if($change > 0)
        <div class="row" style="color:#059669;">
            <span>{{ $isFr ? 'Monnaie rendue' : 'Change' }}:</span>
            <span>{{ number_format($change, 2) }} {{ $currency }}</span>
        </div>
        @elseif($totalPaid < $sale->total)
        <div class="row" style="color:#c53030;">
            <span>{{ $isFr ? 'Reste à payer' : 'Balance Due' }}:</span>
            <span>{{ number_format($sale->total - $totalPaid, 2) }} {{ $currency }}</span>
        </div>
        @endif

        <div class="divider"></div>

        {{-- Footer --}}
        <div class="center">
            @if(!empty($settings['receipt_footer']))
                <div class="bold" style="margin-bottom: 4px;">{{ $settings['receipt_footer'] }}</div>
            @else
                <div class="bold" style="margin-bottom: 4px;">{{ $isFr ? 'Merci pour votre achat !' : 'Thank you for your purchase!' }}</div>
            @endif

            {{-- Invoice barcode --}}
            <div style="margin-top: 6px; font-family: monospace; font-size: 10px; color: #888;">
                ||| {{ $sale->invoice_number }} |||
            </div>

            {{-- Fiscal footer --}}
            @if(!empty($settings['ice']))
            <div class="fiscal-info">
                ICE: {{ $settings['ice'] }}
                @if(!empty($settings['if_number'])) | IF: {{ $settings['if_number'] }} @endif
                @if(!empty($settings['patente'])) | Patente: {{ $settings['patente'] }} @endif
            </div>
            @endif

            <div class="footer-text" style="margin-top: 8px;">
                {{ $isFr ? 'Imprimé le' : 'Printed' }}: {{ now()->format('d/m/Y H:i:s') }}
            </div>
        </div>
    </div>

    {{-- Action Buttons --}}
    <div class="actions no-print">
        <button class="btn-print" onclick="window.print()">
            {{ $isFr ? 'Imprimer' : 'Print' }}
        </button>
        <a href="{{ route('invoices.generate', ['saleId' => $sale->id, 'type' => 'facture']) }}" class="btn-invoice" style="padding:10px 28px; border-radius:8px; font-size:14px; font-weight:bold; text-decoration:none; display:inline-block;">
            {{ $isFr ? 'Facture' : 'Invoice' }}
        </a>
        <button class="btn-back" onclick="window.close(); window.history.back();">
            {{ $isFr ? 'Retour' : 'Back' }}
        </button>
    </div>
</body>
</html>
