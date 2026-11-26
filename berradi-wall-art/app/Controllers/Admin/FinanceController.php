<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Response;
use App\Services\Finance\FinanceStats;

/** Finance overview for a period: sales, invoicing, cash, VAT position, receivables and payables. */
final class FinanceController extends Controller
{
    public function index(): Response
    {
        $key = in_array($this->request->str('period'), ['month', 'prev_month', 'quarter', 'year', 'custom'], true) ? $this->request->str('period') : 'month';
        $p = FinanceStats::period($key, $this->request->str('from'), $this->request->str('to'));
        $flow = FinanceStats::cashflow(12);
        $months = [];
        foreach ($flow as $r) {
            $months[] = DashboardController::monthLabel($r['month']);
        }
        return $this->view('admin/finance/index', [
            'period' => $p, 'key' => $key, 's' => FinanceStats::summary($p['from'], $p['to']), 'receivables' => FinanceStats::receivables(),
            'payables' => FinanceStats::payables(), 'flow' => $flow,
            'chart' => ['netCash' => ['labels' => $months, 'values' => array_map(fn ($r) => (float) $r['net'], $flow)], 'currency' => (string) setting('currency_symbol', 'DH'),
                'i18n' => ['empty' => __('admin.chart_empty'), 'orders' => '']],
        ]);
    }
}
