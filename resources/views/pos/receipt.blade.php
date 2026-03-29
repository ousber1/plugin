<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt - {{ $sale->invoice_number }}</title>
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

        .barcode-area { margin-top: 8px; font-family: 'Libre Barcode 39', monospace; font-size: 28px; letter-spacing: 2px; }

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
        .btn-download { background: #059669; color: #fff; }
        .btn-download:hover { background: #047857; }
        .btn-back { background: #e2e8f0; color: #475569; }
        .btn-back:hover { background: #cbd5e1; }

        @media print {
            body { background: #fff; padding: 0; }
            .receipt { box-shadow: none; max-width: 100%; }
            .actions, .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    @php
        $currency = $settings['currency'] ?? '$';
        $widthClass = 'receipt-' . str_replace('mm', 'mm', $settings['receipt_width'] ?? '80mm');
    @endphp

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
                <div class="info-text">Tel: {{ $settings['store_phone'] }}</div>
            @endif
            @if(!empty($settings['store_email']))
                <div class="info-text">{{ $settings['store_email'] }}</div>
            @endif

            @if(!empty($settings['receipt_header']))
                <div class="info-text" style="margin-top: 4px;">{{ $settings['receipt_header'] }}</div>
            @endif
        </div>

        <div class="double-divider"></div>

        {{-- Receipt Info --}}
        <div style="margin-bottom: 4px;">
            <div class="row"><span>Invoice:</span><span class="bold">{{ $sale->invoice_number }}</span></div>
            <div class="row"><span>Date:</span><span>{{ $sale->created_at->format('d/m/Y H:i') }}</span></div>
            <div class="row"><span>Cashier:</span><span>{{ $sale->user->name ?? 'N/A' }}</span></div>
            @if($sale->customer)
            <div class="row"><span>Customer:</span><span>{{ $sale->customer->name }}</span></div>
            @if($sale->customer->phone)
            <div class="row"><span>Phone:</span><span>{{ $sale->customer->phone }}</span></div>
            @endif
            @endif
            <div class="row"><span>Channel:</span><span>{{ strtoupper($sale->channel) }}</span></div>
        </div>

        <div class="divider"></div>

        {{-- Items --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="right" style="width:35px">Qty</th>
                    <th class="right" style="width:55px">Price</th>
                    <th class="right" style="width:60px">Total</th>
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
                <span>Subtotal:</span>
                <span>{{ $currency }}{{ number_format($sale->subtotal, 2) }}</span>
            </div>
            @if($sale->discount > 0)
            <div class="row" style="color:#c53030;">
                <span>Discount:</span>
                <span>-{{ $currency }}{{ number_format($sale->discount, 2) }}</span>
            </div>
            @endif
            @if($sale->tax > 0)
            <div class="row">
                <span>Tax:</span>
                <span>{{ $currency }}{{ number_format($sale->tax, 2) }}</span>
            </div>
            @endif

            <div class="double-divider"></div>

            <div class="row grand-total">
                <span>TOTAL:</span>
                <span>{{ $currency }}{{ number_format($sale->total, 2) }}</span>
            </div>
        </div>

        <div class="divider"></div>

        {{-- Payments --}}
        <div class="bold" style="margin-bottom: 4px; font-size: 11px;">PAYMENTS:</div>
        @foreach($sale->payments as $payment)
        <div class="row">
            <span>
                <span class="payment-badge">{{ strtoupper($payment->method) }}</span>
                @if($payment->reference)
                    <span style="font-size:9px; color:#888;"> Ref: {{ $payment->reference }}</span>
                @endif
            </span>
            <span class="bold">{{ $currency }}{{ number_format($payment->amount, 2) }}</span>
        </div>
        @endforeach

        @php
            $totalPaid = $sale->payments->sum('amount');
            $change = $totalPaid - $sale->total;
        @endphp

        @if($change > 0)
        <div class="row" style="color:#059669;">
            <span>Change:</span>
            <span>{{ $currency }}{{ number_format($change, 2) }}</span>
        </div>
        @elseif($totalPaid < $sale->total)
        <div class="row" style="color:#c53030;">
            <span>Balance Due:</span>
            <span>{{ $currency }}{{ number_format($sale->total - $totalPaid, 2) }}</span>
        </div>
        @endif

        <div class="divider"></div>

        {{-- Footer --}}
        <div class="center">
            @if(!empty($settings['receipt_footer']))
                <div class="bold" style="margin-bottom: 4px;">{{ $settings['receipt_footer'] }}</div>
            @endif

            {{-- Invoice barcode-style --}}
            <div style="margin-top: 6px; font-family: monospace; font-size: 10px; color: #888;">
                ||| {{ $sale->invoice_number }} |||
            </div>

            <div class="footer-text" style="margin-top: 8px;">
                Printed: {{ now()->format('d/m/Y H:i:s') }}
            </div>
        </div>
    </div>

    {{-- Action Buttons --}}
    <div class="actions no-print">
        <button class="btn-print" onclick="window.print()">
            <i style="margin-right:4px;">🖨️</i> Print Receipt
        </button>
        <button class="btn-back" onclick="window.close(); window.history.back();">
            ← Back
        </button>
    </div>
</body>
</html>
