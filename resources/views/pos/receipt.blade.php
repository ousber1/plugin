<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt - {{ $sale->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 12px; max-width: 300px; margin: 0 auto; padding: 20px 10px; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        .row { display: flex; justify-content: space-between; padding: 2px 0; }
        .items th, .items td { text-align: left; padding: 3px 0; }
        .items { width: 100%; }
        .items .right { text-align: right; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        @media print { body { margin: 0; padding: 10px; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="center">
        <h1>{{ $storeName ?? 'OmniChannel Store' }}</h1>
        <p>{{ $sale->created_at->format('M d, Y h:i A') }}</p>
        <p>Invoice: {{ $sale->invoice_number }}</p>
        <p>Cashier: {{ $sale->user->name ?? 'N/A' }}</p>
    </div>

    <div class="divider"></div>

    <table class="items">
        <thead>
            <tr><th>Item</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Total</th></tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
            <tr>
                <td>{{ Str::limit($item->product->name ?? 'Product', 15) }}</td>
                <td class="right">{{ $item->quantity }}</td>
                <td class="right">{{ number_format($item->unit_price, 2) }}</td>
                <td class="right">{{ number_format($item->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <div class="row"><span>Subtotal:</span><span>${{ number_format($sale->subtotal, 2) }}</span></div>
    @if($sale->discount > 0)
    <div class="row"><span>Discount:</span><span>-${{ number_format($sale->discount, 2) }}</span></div>
    @endif
    @if($sale->tax > 0)
    <div class="row"><span>Tax:</span><span>${{ number_format($sale->tax, 2) }}</span></div>
    @endif
    <div class="divider"></div>
    <div class="row bold" style="font-size: 14px;"><span>TOTAL:</span><span>${{ number_format($sale->total, 2) }}</span></div>
    <div class="divider"></div>

    @foreach($sale->payments as $payment)
    <div class="row"><span>{{ ucfirst($payment->method) }}:</span><span>${{ number_format($payment->amount, 2) }}</span></div>
    @endforeach

    <div class="divider"></div>
    <div class="center" style="margin-top: 10px;">
        <p class="bold">Thank you for your purchase!</p>
        <p style="margin-top: 4px; font-size: 10px;">Powered by OmniChannel</p>
    </div>

    <div class="center no-print" style="margin-top: 20px;">
        <button onclick="window.print()" style="padding: 8px 24px; background: #4f46e5; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
            Print Receipt
        </button>
    </div>
</body>
</html>
