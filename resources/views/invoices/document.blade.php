@php
    $L = \App\Helpers\Lang::class;
    $currency = $settings['currency'] ?? 'DH';
    $template = $settings['invoice_template'] ?? 'modern';
    $color = $settings['invoice_color'] ?? '#4f46e5';
    $dueDays = intval($settings['invoice_due_days'] ?? 30);
    $showLogo = ($settings['invoice_show_logo'] ?? '1') === '1';

    $titles = [
        'facture'         => ['fr' => 'FACTURE', 'en' => 'INVOICE'],
        'proforma'        => ['fr' => 'FACTURE PROFORMA', 'en' => 'PROFORMA INVOICE'],
        'bon_livraison'   => ['fr' => 'BON DE LIVRAISON', 'en' => 'DELIVERY NOTE'],
        'devis'           => ['fr' => 'DEVIS', 'en' => 'QUOTE'],
    ];
    $docTitle = $titles[$type][$lang] ?? $titles['facture'][$lang];
    $invoiceNum = $sale->invoice_number ?? 'INV-' . str_pad($sale->id, 5, '0', STR_PAD_LEFT);
    $isFr = $lang === 'fr';

    // Template-specific config
    $isClassic = $template === 'classic';
    $isMinimal = $template === 'minimal';
    $borderRadius = $isClassic ? '0' : ($isMinimal ? '4px' : '8px');
    $mainColor = $isMinimal ? '#1e293b' : $color;
    $headerBg = $isMinimal ? '#ffffff' : $mainColor;
    $headerText = $isMinimal ? $mainColor : '#ffffff';

    // Number to words functions
    function numberToFrenchWords($number) {
        $number = abs($number);
        $intPart = intval($number);
        $decPart = round(($number - $intPart) * 100);

        $units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
                   'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        $tens = ['', 'dix', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];

        $convert = function($n) use (&$convert, $units, $tens) {
            if ($n < 0) return '';
            if ($n == 0) return 'zero';
            if ($n < 20) return $units[$n];
            if ($n < 70) {
                $t = $tens[intval($n / 10)];
                $u = $n % 10;
                if ($u == 1 && $n < 70) return $t . ' et un';
                return $u > 0 ? $t . '-' . $units[$u] : $t;
            }
            if ($n < 80) {
                $sub = $n - 60;
                if ($sub == 11) return 'soixante et onze';
                return 'soixante-' . ($sub < 20 ? $units[$sub] : $tens[intval($sub/10)] . ($sub%10 > 0 ? '-'.$units[$sub%10] : ''));
            }
            if ($n < 100) {
                $sub = $n - 80;
                if ($sub == 0) return 'quatre-vingts';
                return 'quatre-vingt-' . ($sub < 20 ? $units[$sub] : $tens[intval($sub/10)] . ($sub%10 > 0 ? '-'.$units[$sub%10] : ''));
            }
            if ($n < 200) {
                $r = $n - 100;
                return $r == 0 ? 'cent' : 'cent ' . $convert($r);
            }
            if ($n < 1000) {
                $h = intval($n / 100);
                $r = $n % 100;
                $prefix = $units[$h] . ' cent';
                if ($r == 0) return $prefix . 's';
                return $prefix . ' ' . $convert($r);
            }
            if ($n < 2000) {
                $r = $n - 1000;
                return $r == 0 ? 'mille' : 'mille ' . $convert($r);
            }
            if ($n < 1000000) {
                $k = intval($n / 1000);
                $r = $n % 1000;
                return $convert($k) . ' mille' . ($r > 0 ? ' ' . $convert($r) : '');
            }
            if ($n < 1000000000) {
                $m = intval($n / 1000000);
                $r = $n % 1000000;
                $prefix = ($m == 1 ? 'un million' : $convert($m) . ' millions');
                return $prefix . ($r > 0 ? ' ' . $convert($r) : '');
            }
            return (string)$n;
        };

        $result = $convert($intPart);
        if ($decPart > 0) {
            $result .= ' dirhams et ' . $convert($decPart) . ' centimes';
        } else {
            $result .= ' dirhams';
        }
        return mb_strtoupper(mb_substr($result, 0, 1)) . mb_substr($result, 1);
    }

    function numberToEnglishWords($number) {
        $number = abs($number);
        $intPart = intval($number);
        $decPart = round(($number - $intPart) * 100);

        $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
                 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

        $convert = function($n) use (&$convert, $ones, $tens) {
            if ($n == 0) return 'zero';
            if ($n < 20) return $ones[$n];
            if ($n < 100) return $tens[intval($n/10)] . ($n%10 > 0 ? '-' . $ones[$n%10] : '');
            if ($n < 1000) {
                $h = intval($n/100);
                $r = $n % 100;
                return $ones[$h] . ' hundred' . ($r > 0 ? ' and ' . $convert($r) : '');
            }
            if ($n < 1000000) {
                $k = intval($n/1000);
                $r = $n % 1000;
                return $convert($k) . ' thousand' . ($r > 0 ? ' ' . $convert($r) : '');
            }
            if ($n < 1000000000) {
                $m = intval($n/1000000);
                $r = $n % 1000000;
                return $convert($m) . ' million' . ($r > 0 ? ' ' . $convert($r) : '');
            }
            return (string)$n;
        };

        $result = $convert($intPart);
        if ($decPart > 0) {
            $result .= ' dirhams and ' . $convert($decPart) . ' centimes';
        } else {
            $result .= ' dirhams';
        }
        return ucfirst($result);
    }

    $amountInWords = $isFr ? numberToFrenchWords($totalTtc) : numberToEnglishWords($totalTtc);
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $docTitle }} {{ $invoiceNum }}</title>
    <style>
        :root {
            --main: {{ $mainColor }};
            --main-light: {{ $mainColor }}15;
            --radius: {{ $borderRadius }};
        }
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
            border-radius: var(--radius);
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }

        /* ===== PRINT: Repeat header/footer on every page ===== */
        @media print {
            body { background: #fff; padding: 0; }
            .invoice-page { box-shadow: none; border-radius: 0; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }

            /* The outer table drives page-break header/footer repetition */
            .print-wrapper { width: 100%; }
            .print-wrapper > thead { display: table-header-group; }
            .print-wrapper > tfoot { display: table-footer-group; }
            .print-wrapper > tbody { display: table-row-group; }

            /* Add spacing so body content doesn't overlap the fixed header/footer */
            .print-header-cell { padding-bottom: 12px; }
            .print-footer-cell { padding-top: 12px; }

            /* Prevent items from breaking across pages */
            .items-table tr { page-break-inside: avoid; }
        }

        /* On screen, hide the tfoot/thead repetition wrappers - show inline */
        @media screen {
            .print-wrapper > thead,
            .print-wrapper > tfoot,
            .print-wrapper > tbody { display: table-row-group; }
        }

        /* ===== HEADER ===== */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 16px;
            margin-bottom: 16px;
            @if($isClassic)
            border-bottom: 3px double var(--main);
            @elseif($isMinimal)
            border-bottom: 1px solid #e2e8f0;
            @else
            border-bottom: 3px solid var(--main);
            @endif
        }
        .company-info { flex: 1; }
        .company-name {
            font-size: {{ $isMinimal ? '18px' : '22px' }};
            font-weight: {{ $isClassic ? '700' : '800' }};
            color: var(--main);
            margin-bottom: 6px;
            @if($isClassic) text-transform: uppercase; letter-spacing: 1px; @endif
        }
        .company-details { font-size: 11px; color: #64748b; line-height: 1.6; }
        .company-logo { max-height: 70px; max-width: 160px; object-fit: contain; margin-bottom: 8px; }
        .doc-type-box { text-align: right; }
        .doc-type-title {
            font-size: {{ $isMinimal ? '22px' : '28px' }};
            font-weight: 900;
            color: var(--main);
            letter-spacing: {{ $isClassic ? '3px' : '2px' }};
            @if($isClassic) text-transform: uppercase; border-bottom: 2px solid var(--main); padding-bottom: 4px; @endif
        }
        .doc-number { font-size: 13px; color: #64748b; margin-top: 4px; }
        .doc-date { font-size: 12px; color: #64748b; margin-top: 2px; }

        /* ===== FISCAL ROW ===== */
        .fiscal-row {
            display: flex; flex-wrap: wrap; gap: 12px;
            background: var(--main-light);
            border: 1px solid #e2e8f0;
            border-radius: var(--radius);
            padding: 10px 14px;
            margin-bottom: 16px;
            font-size: 11px;
        }
        .fiscal-item { display: flex; gap: 4px; }
        .fiscal-label { font-weight: 700; color: #475569; }
        .fiscal-value { color: #1e293b; }

        /* ===== PARTIES ===== */
        .parties-row { display: flex; gap: 24px; margin-bottom: 20px; }
        .party-box { flex: 1; border: 1px solid #e2e8f0; border-radius: var(--radius); padding: 14px; }
        .party-title {
            font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
            color: var(--main); margin-bottom: 8px;
        }
        .party-name { font-size: 14px; font-weight: 700; margin-bottom: 4px; }
        .party-detail { font-size: 11px; color: #64748b; line-height: 1.5; }

        /* ===== ITEMS TABLE ===== */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .items-table thead th {
            background: {{ $headerBg }};
            color: {{ $headerText }};
            font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.04em;
            padding: 10px 12px; text-align: left;
            @if($isMinimal) background: #f8fafc; color: #334155; border-bottom: 2px solid #e2e8f0; @endif
        }
        @if(!$isMinimal)
        .items-table thead th:first-child { border-radius: var(--radius) 0 0 0; }
        .items-table thead th:last-child { border-radius: 0 var(--radius) 0 0; }
        @endif
        .items-table thead th.text-center { text-align: center; }
        .items-table thead th.text-right { text-align: right; }
        .items-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
        }
        .items-table tbody tr:last-child td {
            @if($isClassic) border-bottom: 2px double var(--main);
            @elseif($isMinimal) border-bottom: 1px solid #cbd5e1;
            @else border-bottom: 2px solid var(--main); @endif
        }
        .items-table tbody td.text-center { text-align: center; }
        .items-table tbody td.text-right { text-align: right; }
        @if(!$isMinimal) .items-table tbody tr:nth-child(even) { background: var(--main-light); } @endif

        /* ===== TOTALS ===== */
        .totals-section { display: flex; justify-content: flex-end; margin-bottom: 24px; }
        .totals-box { width: 300px; }
        .totals-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 12px; }
        .totals-row.border-top { border-top: 1px solid #e2e8f0; margin-top: 4px; padding-top: 10px; }
        .totals-row.grand-total {
            @if($isMinimal)
            background: #1e293b; color: #fff;
            @else
            background: var(--main); color: #fff;
            @endif
            padding: 10px 14px; border-radius: var(--radius);
            font-size: 15px; font-weight: 800; margin-top: 8px;
        }

        /* ===== BANK ===== */
        .bank-section {
            background: var(--main-light); border: 1px solid #e2e8f0;
            border-radius: var(--radius); padding: 14px; margin-bottom: 24px;
        }
        .bank-title { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--main); margin-bottom: 8px; }
        .bank-detail { font-size: 12px; color: #334155; line-height: 1.6; }

        /* ===== CONDITIONS / SIGNATURE FOOTER ===== */
        .invoice-footer {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-top: 24px; padding-top: 16px;
            @if($isClassic) border-top: 2px double #94a3b8;
            @else border-top: 2px solid #e2e8f0; @endif
        }
        .conditions-box { flex: 1; font-size: 11px; color: #64748b; line-height: 1.6; }
        .signature-box { width: 200px; text-align: center; padding-top: 10px; }
        .signature-line { border-top: 1px dashed #94a3b8; margin-top: 60px; padding-top: 6px; font-size: 10px; color: #64748b; }
        .thank-you { text-align: center; margin-top: 20px; font-size: 12px; color: #64748b; font-style: italic; }

        /* ===== SOCIETE FOOTER (repeated on print) ===== */
        .societe-footer {
            padding-top: 12px;
            border-top: 2px solid var(--main);
            text-align: center;
        }
        .societe-footer .sf-name {
            font-size: 11px; font-weight: 700; color: var(--main);
            margin-bottom: 4px; text-transform: uppercase;
        }
        .societe-footer .sf-details {
            font-size: 10px; color: #475569; line-height: 1.8;
        }
        .societe-footer .sf-icon {
            font-size: 9px; color: var(--main);
        }

        /* ===== ACTION BAR ===== */
        .action-bar { max-width: 800px; margin: 0 auto 20px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .action-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: 6px; font-size: 12px; font-weight: 600;
            text-decoration: none; cursor: pointer; border: none; transition: all 0.2s;
        }
        .btn-print { background: var(--main); color: #fff; }
        .btn-print:hover { opacity: 0.9; }
        .btn-back { background: #e2e8f0; color: #334155; }
        .btn-back:hover { background: #cbd5e1; }
        .btn-type { background: #fff; color: #334155; border: 1px solid #e2e8f0; }
        .btn-type:hover { background: #f8fafc; }
        .btn-type.active { background: var(--main); color: #fff; border-color: var(--main); }
        .lang-switch { margin-left: auto; display: flex; gap: 4px; }
        .lang-btn {
            padding: 8px 12px; border-radius: 6px; font-size: 12px; font-weight: 700;
            text-decoration: none; border: 1px solid #e2e8f0; background: #fff; color: #64748b; cursor: pointer;
        }
        .lang-btn.active { background: var(--main); color: #fff; border-color: var(--main); }
        .tpl-switch { display: flex; gap: 4px; border-left: 1px solid #e2e8f0; padding-left: 8px; margin-left: 8px; }
        .tpl-btn {
            padding: 8px 10px; border-radius: 6px; font-size: 11px; font-weight: 600;
            text-decoration: none; border: 1px solid #e2e8f0; background: #fff; color: #64748b; cursor: pointer;
        }
        .tpl-btn.active { background: var(--main); color: #fff; border-color: var(--main); }

        /* ===== AMOUNT IN WORDS BOX ===== */
        .amount-words-box {
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius);
            padding: 14px;
            background: var(--main-light);
        }
        .amount-words-title {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            color: var(--main); margin-bottom: 6px; letter-spacing: 0.03em;
        }
        .amount-words-value {
            font-size: 13px; font-weight: 600; color: #1e293b; font-style: italic;
        }

        /* ===== PAGE NUMBER via CSS counter (print only) ===== */
        @media print {
            @page {
                margin: 15mm 10mm 20mm 10mm;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

{{-- Action Bar (not printed) --}}
<div class="action-bar no-print">
    <a href="{{ route('invoices.index') }}" class="action-btn btn-back"><i class="fas fa-arrow-left"></i> {{ $isFr ? 'Retour' : 'Back' }}</a>
    <button onclick="window.print()" class="action-btn btn-print"><i class="fas fa-print"></i> {{ $isFr ? 'Imprimer' : 'Print' }}</button>

    @foreach(['facture', 'proforma', 'bon_livraison', 'devis'] as $t)
    <a href="{{ route('invoices.generate', ['saleId' => $sale->id, 'type' => $t, 'lang' => $lang, 'tpl' => $template]) }}" class="action-btn btn-type {{ $type === $t ? 'active' : '' }}">{{ $titles[$t][$lang] }}</a>
    @endforeach

    <div class="tpl-switch">
        @foreach(['modern' => 'Modern', 'classic' => 'Classic', 'minimal' => 'Minimal'] as $tKey => $tLabel)
        <a href="{{ route('invoices.generate', ['saleId' => $sale->id, 'type' => $type, 'lang' => $lang, 'tpl' => $tKey]) }}" class="tpl-btn {{ $template === $tKey ? 'active' : '' }}">{{ $tLabel }}</a>
        @endforeach
    </div>

    <div class="lang-switch">
        <a href="{{ route('invoices.generate', ['saleId' => $sale->id, 'type' => $type, 'lang' => 'fr', 'tpl' => $template]) }}" class="lang-btn {{ $lang === 'fr' ? 'active' : '' }}">FR</a>
        <a href="{{ route('invoices.generate', ['saleId' => $sale->id, 'type' => $type, 'lang' => 'en', 'tpl' => $template]) }}" class="lang-btn {{ $lang === 'en' ? 'active' : '' }}">EN</a>
    </div>
</div>

{{-- ============================================================
     INVOICE DOCUMENT
     Uses <table> with <thead>/<tfoot> so browsers repeat
     the company header & societe footer on EVERY printed page
     ============================================================ --}}
<div class="invoice-page">
<table class="print-wrapper" style="width:100%; border-collapse:collapse;">

    {{-- ===== REPEATING HEADER (every page on print) ===== --}}
    <thead>
        <tr><td class="print-header-cell" style="padding:0; border:none;">
            <div class="invoice-header">
                <div class="company-info">
                    @if($showLogo && !empty($settings['receipt_logo']))
                    <img src="{{ asset('storage/' . $settings['receipt_logo']) }}" alt="Logo" class="company-logo">
                    @endif
                    <div class="company-name">{{ $settings['store_name'] ?? 'My Store' }}</div>
                    <div class="company-details">
                        @if(!empty($settings['store_address'])){{ $settings['store_address'] }}<br>@endif
                        @if(!empty($settings['store_city'])){{ $settings['store_city'] }}<br>@endif
                        @if(!empty($settings['store_phone']))<i class="fas fa-phone" style="font-size:10px"></i> {{ $settings['store_phone'] }}@endif
                        @if(!empty($settings['store_phone']) && !empty($settings['store_email'])) &nbsp;|&nbsp; @endif
                        @if(!empty($settings['store_email']))<i class="fas fa-envelope" style="font-size:10px"></i> {{ $settings['store_email'] }}@endif
                        @if(!empty($settings['store_website']))<br><i class="fas fa-globe" style="font-size:10px"></i> {{ $settings['store_website'] }}@endif
                    </div>
                </div>
                <div class="doc-type-box">
                    <div class="doc-type-title">{{ $docTitle }}</div>
                    <div class="doc-number">N&deg; {{ $invoiceNum }}</div>
                    <div class="doc-date">Date: {{ $sale->created_at->format('d/m/Y') }}</div>
                    @if($type === 'facture' || $type === 'proforma')
                    <div class="doc-date">{{ $isFr ? 'Echeance' : 'Due' }}: {{ $sale->created_at->addDays($dueDays)->format('d/m/Y') }}</div>
                    @endif
                </div>
            </div>
        </td></tr>
    </thead>

    {{-- ===== REPEATING FOOTER (every page on print) ===== --}}
    <tfoot>
        <tr><td class="print-footer-cell" style="padding:0; border:none;">
            <div class="societe-footer">
                <div class="sf-name">{{ $settings['store_name'] ?? 'My Store' }}</div>
                <div class="sf-details">
                    @if(!empty($settings['store_address']))<i class="fas fa-map-marker-alt sf-icon"></i> {{ $settings['store_address'] }}@endif
                    @if(!empty($settings['store_city'])), {{ $settings['store_city'] }}@endif
                    @if(!empty($settings['store_phone'])) &bull; <i class="fas fa-phone sf-icon"></i> {{ $settings['store_phone'] }}@endif
                    @if(!empty($settings['store_email'])) &bull; <i class="fas fa-envelope sf-icon"></i> {{ $settings['store_email'] }}@endif
                    @if(!empty($settings['store_website'])) &bull; <i class="fas fa-globe sf-icon"></i> {{ $settings['store_website'] }}@endif
                    <br>
                    @php $fiscals = []; @endphp
                    @foreach(['ice' => 'ICE', 'if_number' => 'IF', 'rc' => 'RC', 'cnss' => 'CNSS', 'patente' => 'Patente'] as $fk => $fl)
                    @if(!empty($settings[$fk])) @php $fiscals[] = $fl . ': ' . $settings[$fk]; @endphp @endif
                    @endforeach
                    @if(count($fiscals) > 0)<span style="font-weight:600;">{{ implode(' &bull; ', $fiscals) }}</span><br>@endif
                    @if(!empty($settings['bank_name']) || !empty($settings['bank_rib']))
                    <i class="fas fa-university sf-icon"></i>
                    @if(!empty($settings['bank_name'])){{ $settings['bank_name'] }}@endif
                    @if(!empty($settings['bank_rib'])) - RIB: {{ $settings['bank_rib'] }}@endif
                    @endif
                </div>
            </div>
        </td></tr>
    </tfoot>

    {{-- ===== BODY CONTENT ===== --}}
    <tbody>
        <tr><td style="padding:0; border:none;">

            {{-- Fiscal IDs --}}
            @if(!empty($settings['ice']) || !empty($settings['if_number']) || !empty($settings['rc']) || !empty($settings['cnss']) || !empty($settings['patente']))
            <div class="fiscal-row">
                @foreach(['ice' => 'ICE', 'if_number' => 'IF', 'rc' => 'RC', 'cnss' => 'CNSS', 'patente' => 'Patente'] as $fKey => $fLabel)
                @if(!empty($settings[$fKey]))
                <div class="fiscal-item"><span class="fiscal-label">{{ $fLabel }}:</span> <span class="fiscal-value">{{ $settings[$fKey] }}</span></div>
                @endif
                @endforeach
            </div>
            @endif

            {{-- Seller / Client --}}
            <div class="parties-row">
                <div class="party-box">
                    <div class="party-title">{{ $isFr ? 'VENDEUR' : 'SELLER' }}</div>
                    <div class="party-name">{{ $settings['store_name'] ?? 'My Store' }}</div>
                    <div class="party-detail">
                        @if(!empty($settings['store_address'])){{ $settings['store_address'] }}<br>@endif
                        @if(!empty($settings['store_city'])){{ $settings['store_city'] }}<br>@endif
                        @if(!empty($settings['store_phone'])){{ $isFr ? 'Tel' : 'Phone' }}: {{ $settings['store_phone'] }}<br>@endif
                        @if(!empty($settings['store_email']))Email: {{ $settings['store_email'] }}@endif
                    </div>
                </div>
                <div class="party-box">
                    <div class="party-title">{{ $isFr ? 'CLIENT' : 'CLIENT' }}</div>
                    @if($sale->customer)
                    <div class="party-name">{{ $sale->customer->name }}</div>
                    <div class="party-detail">
                        @if(!empty($sale->customer->phone)){{ $isFr ? 'Tel' : 'Phone' }}: {{ $sale->customer->phone }}<br>@endif
                        @if(!empty($sale->customer->email))Email: {{ $sale->customer->email }}<br>@endif
                        @if(!empty($sale->customer->address)){{ $sale->customer->address }}<br>@endif
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
                        <th>{{ $isFr ? 'Designation' : 'Description' }}</th>
                        <th class="text-center" style="width:80px">{{ $isFr ? 'Qte' : 'Qty' }}</th>
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
                        <td class="text-right">{{ number_format($item->unit_price ?? $item->price ?? 0, 2) }} {{ $currency }}</td>
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
                    @if(($sale->discount ?? 0) > 0)
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

            {{-- Amount in words --}}
            <div class="amount-words-box">
                <div class="amount-words-title">
                    {{ $isFr ? 'Arrêtée la présente facture à la somme de :' : 'This invoice is set at the amount of:' }}
                </div>
                <div class="amount-words-value">
                    {{ $amountInWords }}
                </div>
            </div>

            {{-- Bank Details --}}
            @if($type === 'facture' || $type === 'proforma')
            @if(!empty($settings['bank_name']) || !empty($settings['bank_rib']))
            <div class="bank-section">
                <div class="bank-title"><i class="fas fa-university" style="font-size:11px"></i> {{ $isFr ? 'COORDONNEES BANCAIRES' : 'BANK DETAILS' }}</div>
                <div class="bank-detail">
                    @if(!empty($settings['bank_name'])){{ $isFr ? 'Banque' : 'Bank' }}: <strong>{{ $settings['bank_name'] }}</strong><br>@endif
                    @if(!empty($settings['bank_rib']))RIB: <strong>{{ $settings['bank_rib'] }}</strong>@endif
                </div>
            </div>
            @endif
            @endif

            {{-- Footer: Conditions, Legal, Signature --}}
            <div class="invoice-footer">
                <div class="conditions-box">
                    @if(!empty($settings['invoice_conditions']))
                    <strong>{{ $isFr ? 'Conditions de paiement:' : 'Payment Terms:' }}</strong><br>
                    {{ $settings['invoice_conditions'] }}<br>
                    @endif
                    @if(!empty($settings['invoice_mention_legale']))
                    <br><strong>{{ $isFr ? 'Mentions legales:' : 'Legal Notice:' }}</strong><br>
                    {{ $settings['invoice_mention_legale'] }}<br>
                    @endif
                    @if(!empty($settings['invoice_notes']))
                    <br><em>{{ $settings['invoice_notes'] }}</em><br>
                    @endif
                    @if(!empty($settings['invoice_footer']))
                    <br>{{ $settings['invoice_footer'] }}
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

        </td></tr>
    </tbody>

</table>
</div>
</body>
</html>
