@php
    $L = \App\Helpers\Lang::class;
    $currency = $settings['currency'] ?? 'MAD';

    // Document title by type
    $titles = [
        'facture'         => ['fr' => 'FACTURE', 'en' => 'INVOICE'],
        'proforma'        => ['fr' => 'FACTURE PROFORMA', 'en' => 'PROFORMA INVOICE'],
        'bon_livraison'   => ['fr' => 'BON DE LIVRAISON', 'en' => 'DELIVERY NOTE'],
        'devis'           => ['fr' => 'DEVIS', 'en' => 'QUOTE'],
    ];
    $docTitle = $titles[$type][$lang] ?? $titles['facture'][$lang];
    $invoiceNum = $sale->invoice_number ?? 'INV-' . str_pad($sale->id, 5, '0', STR_PAD_LEFT);

    $isFr = $lang === 'fr';
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $docTitle }} {{ $invoiceNum }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            color: #1e293b;
            background: #f1f5f9;
            padding: 20px;
        }
        .invoice-page {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        @media print {
            body { background: #fff; padding: 0; }
            .invoice-page { box-shadow: none; border-radius: 0; padding: 20px; max-width: 100%; }
            .no-print { display: none !important; }
        }

        /* Header */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #4f46e5;
        }
        .company-info { flex: 1; }
        .company-name {
            font-size: 22px;
            font-weight: 800;
            color: #4f46e5;
            margin-bottom: 6px;
        }
        .company-details {
            font-size: 11px;
            color: #64748b;
            line-height: 1.6;
        }
        .company-logo {
            max-height: 70px;
            max-width: 160px;
            object-fit: contain;
            margin-bottom: 8px;
        }
        .doc-type-box {
            text-align: right;
        }
        .doc-type-title {
            font-size: 28px;
            font-weight: 900;
            color: #4f46e5;
            letter-spacing: 2px;
        }
        .doc-number {
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
        }
        .doc-date {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }

        /* Fiscal IDs */
        .fiscal-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 24px;
            font-size: 11px;
        }
        .fiscal-item {
            display: flex;
            gap: 4px;
        }
        .fiscal-label {
            font-weight: 700;
            color: #475569;
        }
        .fiscal-value {
            color: #1e293b;
        }

        /* Client box */
        .parties-row {
            display: flex;
            gap: 24px;
            margin-bottom: 24px;
        }
        .party-box {
            flex: 1;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 14px;
        }
        .party-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #4f46e5;
            margin-bottom: 8px;
        }
        .party-name {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .party-detail {
            font-size: 11px;
            color: #64748b;
            line-height: 1.5;
        }

        /* Items table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .items-table thead th {
            background: #4f46e5;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 10px 12px;
            text-align: left;
        }
        .items-table thead th:first-child {
            border-radius: 6px 0 0 0;
        }
        .items-table thead th:last-child {
            border-radius: 0 6px 0 0;
            text-align: right;
        }
        .items-table thead th.text-center { text-align: center; }
        .items-table thead th.text-right { text-align: right; }
        .items-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
        }
        .items-table tbody tr:last-child td {
            border-bottom: 2px solid #4f46e5;
        }
        .items-table tbody td.text-center { text-align: center; }
        .items-table tbody td.text-right { text-align: right; }
        .items-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        /* Totals */
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 30px;
        }
        .totals-box {
            width: 300px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 12px;
        }
        .totals-row.border-top {
            border-top: 1px solid #e2e8f0;
            margin-top: 4px;
            padding-top: 10px;
        }
        .totals-row.grand-total {
            background: #4f46e5;
            color: #fff;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 800;
            margin-top: 8px;
        }

        /* Bank info */
        .bank-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 14px;
            margin-bottom: 24px;
        }
        .bank-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #4f46e5;
            margin-bottom: 8px;
        }
        .bank-detail {
            font-size: 12px;
            color: #334155;
            line-height: 1.6;
        }

        /* Footer */
        .invoice-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }
        .conditions-box {
            flex: 1;
            font-size: 11px;
            color: #64748b;
            line-height: 1.6;
        }
        .signature-box {
            width: 200px;
            text-align: center;
            padding-top: 10px;
        }
        .signature-line {
            border-top: 1px dashed #94a3b8;
            margin-top: 60px;
            padding-top: 6px;
            font-size: 10px;
            color: #64748b;
        }
        .thank-you {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: #64748b;
            font-style: italic;
        }

        /* Action buttons */
        .action-bar {
            max-width: 800px;
            margin: 0 auto 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-print { background: #4f46e5; color: #fff; }
        .btn-print:hover { background: #4338ca; }
        .btn-back { background: #e2e8f0; color: #334155; }
        .btn-back:hover { background: #cbd5e1; }
        .btn-type { background: #fff; color: #334155; border: 1px solid #e2e8f0; }
        .btn-type:hover { background: #f8fafc; }
        .btn-type.active { background: #4f46e5; color: #fff; border-color: #4f46e5; }
        .lang-switch {
            margin-left: auto;
            display: flex;
            gap: 4px;
        }
        .lang-btn {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #64748b;
            cursor: pointer;
        }
        .lang-btn.active {
            background: #4f46e5;
            color: #fff;
            border-color: #4f46e5;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

{{-- Action Bar (not printed) --}}
<div class="action-bar no-print">
    <a href="{{ route('invoices.index') }}" class="action-btn btn-back">
        <i class="fas fa-arrow-left"></i> {{ $isFr ? 'Retour' : 'Back' }}
    </a>
    <button onclick="window.print()" class="action-btn btn-print">
        <i class="fas fa-print"></i> {{ $isFr ? 'Imprimer' : 'Print' }}
    </button>

    {{-- Type switcher --}}
    @foreach(['facture', 'proforma', 'bon_livraison', 'devis'] as $t)
    <a href="{{ route('invoices.generate', ['saleId' => $sale->id, 'type' => $t, 'lang' => $lang]) }}"
       class="action-btn btn-type {{ $type === $t ? 'active' : '' }}">
        {{ $titles[$t][$lang] }}
    </a>
    @endforeach

    {{-- Language switcher --}}
    <div class="lang-switch">
        <a href="{{ route('invoices.generate', ['saleId' => $sale->id, 'type' => $type, 'lang' => 'fr']) }}"
           class="lang-btn {{ $lang === 'fr' ? 'active' : '' }}">FR</a>
        <a href="{{ route('invoices.generate', ['saleId' => $sale->id, 'type' => $type, 'lang' => 'en']) }}"
           class="lang-btn {{ $lang === 'en' ? 'active' : '' }}">EN</a>
    </div>
</div>

{{-- Invoice Document --}}
<div class="invoice-page">

    {{-- Header --}}
    <div class="invoice-header">
        <div class="company-info">
            @if(($settings['receipt_show_logo'] ?? '1') === '1' && !empty($settings['receipt_logo']))
            <img src="{{ asset('storage/' . $settings['receipt_logo']) }}" alt="Logo" class="company-logo">
            @endif
            <div class="company-name">{{ $settings['store_name'] ?? 'My Store' }}</div>
            <div class="company-details">
                @if(!empty($settings['store_address'])){{ $settings['store_address'] }}<br>@endif
                @if(!empty($settings['store_phone']))<i class="fas fa-phone" style="font-size:10px"></i> {{ $settings['store_phone'] }}@endif
                @if(!empty($settings['store_phone']) && !empty($settings['store_email'])) &nbsp;|&nbsp; @endif
                @if(!empty($settings['store_email']))<i class="fas fa-envelope" style="font-size:10px"></i> {{ $settings['store_email'] }}@endif
            </div>
        </div>
        <div class="doc-type-box">
            <div class="doc-type-title">{{ $docTitle }}</div>
            <div class="doc-number">N&deg; {{ $invoiceNum }}</div>
            <div class="doc-date">{{ $isFr ? 'Date' : 'Date' }}: {{ $sale->created_at->format('d/m/Y') }}</div>
            @if($type === 'facture' || $type === 'proforma')
            <div class="doc-date">{{ $isFr ? 'Echéance' : 'Due' }}: {{ $sale->created_at->addDays(30)->format('d/m/Y') }}</div>
            @endif
        </div>
    </div>

    {{-- Moroccan Fiscal Identifiers --}}
    @if(!empty($settings['ice']) || !empty($settings['if_number']) || !empty($settings['rc']) || !empty($settings['cnss']) || !empty($settings['patente']))
    <div class="fiscal-row">
        @if(!empty($settings['ice']))
        <div class="fiscal-item"><span class="fiscal-label">ICE:</span> <span class="fiscal-value">{{ $settings['ice'] }}</span></div>
        @endif
        @if(!empty($settings['if_number']))
        <div class="fiscal-item"><span class="fiscal-label">IF:</span> <span class="fiscal-value">{{ $settings['if_number'] }}</span></div>
        @endif
        @if(!empty($settings['rc']))
        <div class="fiscal-item"><span class="fiscal-label">RC:</span> <span class="fiscal-value">{{ $settings['rc'] }}</span></div>
        @endif
        @if(!empty($settings['cnss']))
        <div class="fiscal-item"><span class="fiscal-label">CNSS:</span> <span class="fiscal-value">{{ $settings['cnss'] }}</span></div>
        @endif
        @if(!empty($settings['patente']))
        <div class="fiscal-item"><span class="fiscal-label">{{ $isFr ? 'Patente' : 'Patente' }}:</span> <span class="fiscal-value">{{ $settings['patente'] }}</span></div>
        @endif
    </div>
    @endif

    {{-- Parties: Seller / Client --}}
    <div class="parties-row">
        <div class="party-box">
            <div class="party-title">{{ $isFr ? 'VENDEUR' : 'SELLER' }}</div>
            <div class="party-name">{{ $settings['store_name'] ?? 'My Store' }}</div>
            <div class="party-detail">
                @if(!empty($settings['store_address'])){{ $settings['store_address'] }}<br>@endif
                @if(!empty($settings['store_phone'])){{ $isFr ? 'Tél' : 'Phone' }}: {{ $settings['store_phone'] }}<br>@endif
                @if(!empty($settings['store_email']))Email: {{ $settings['store_email'] }}@endif
            </div>
        </div>
        <div class="party-box">
            <div class="party-title">{{ $isFr ? 'CLIENT' : 'CLIENT' }}</div>
            @if($sale->customer)
            <div class="party-name">{{ $sale->customer->name }}</div>
            <div class="party-detail">
                @if(!empty($sale->customer->phone)){{ $isFr ? 'Tél' : 'Phone' }}: {{ $sale->customer->phone }}<br>@endif
                @if(!empty($sale->customer->email))Email: {{ $sale->customer->email }}<br>@endif
                @if(!empty($sale->customer->city)){{ $isFr ? 'Ville' : 'City' }}: {{ $sale->customer->city }}@endif
            </div>
            @else
            <div class="party-name">{{ $isFr ? 'Client de passage' : 'Walk-in Customer' }}</div>
            @endif
        </div>
    </div>

    {{-- Items Table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:40px">#</th>
                <th>{{ $isFr ? 'Désignation' : 'Description' }}</th>
                <th class="text-center" style="width:80px">{{ $isFr ? 'Qté' : 'Qty' }}</th>
                <th class="text-right" style="width:110px">{{ $isFr ? 'Prix unitaire' : 'Unit Price' }}</th>
                <th class="text-right" style="width:120px">{{ $isFr ? 'Montant HT' : 'Amount' }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->product->name ?? 'Product' }}</td>
                <td class="text-center">{{ $item->quantity }}</td>
                <td class="text-right">{{ number_format($item->price, 2) }} {{ $currency }}</td>
                <td class="text-right">{{ number_format($item->total, 2) }} {{ $currency }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <div class="totals-section">
        <div class="totals-box">
            <div class="totals-row">
                <span>{{ $isFr ? 'Total HT' : 'Subtotal (excl. tax)' }}</span>
                <span>{{ number_format($subtotal, 2) }} {{ $currency }}</span>
            </div>
            @if($sale->discount ?? 0 > 0)
            <div class="totals-row">
                <span>{{ $isFr ? 'Remise' : 'Discount' }}</span>
                <span>-{{ number_format($sale->discount, 2) }} {{ $currency }}</span>
            </div>
            @endif
            <div class="totals-row border-top">
                <span>TVA ({{ $taxRate }}%)</span>
                <span>{{ number_format($tvaAmount, 2) }} {{ $currency }}</span>
            </div>
            <div class="totals-row grand-total">
                <span>{{ $isFr ? 'TOTAL TTC' : 'TOTAL (incl. tax)' }}</span>
                <span>{{ number_format($totalTtc, 2) }} {{ $currency }}</span>
            </div>
        </div>
    </div>

    {{-- Bank Details --}}
    @if($type === 'facture' || $type === 'proforma')
    @if(!empty($settings['bank_name']) || !empty($settings['bank_rib']))
    <div class="bank-section">
        <div class="bank-title"><i class="fas fa-university" style="font-size:11px"></i> {{ $isFr ? 'COORDONNÉES BANCAIRES' : 'BANK DETAILS' }}</div>
        <div class="bank-detail">
            @if(!empty($settings['bank_name'])){{ $isFr ? 'Banque' : 'Bank' }}: <strong>{{ $settings['bank_name'] }}</strong><br>@endif
            @if(!empty($settings['bank_rib']))RIB: <strong>{{ $settings['bank_rib'] }}</strong>@endif
        </div>
    </div>
    @endif
    @endif

    {{-- Footer: Conditions & Signature --}}
    <div class="invoice-footer">
        <div class="conditions-box">
            @if(!empty($settings['invoice_conditions']))
            <strong>{{ $isFr ? 'Conditions de paiement:' : 'Payment Terms:' }}</strong><br>
            {{ $settings['invoice_conditions'] }}
            @endif
            @if(!empty($settings['invoice_footer']))
            <br><br>{{ $settings['invoice_footer'] }}
            @endif
        </div>
        <div class="signature-box">
            <div class="signature-line">{{ $isFr ? 'Signature et cachet' : 'Signature & Stamp' }}</div>
        </div>
    </div>

    {{-- Thank you --}}
    <div class="thank-you">
        {{ $isFr ? 'Merci pour votre confiance' : 'Thank you for your business' }}
    </div>
</div>

</body>
</html>
