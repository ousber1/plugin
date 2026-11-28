<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\Finance\FinanceStats;
use App\Services\Reports\ReportService;

/** Reports with period filter, table, chart (when meaningful) and CSV export. */
final class ReportController extends Controller
{
    public function index(): Response
    {
        $report = in_array($this->request->str('report'), ReportService::REPORTS, true) ? $this->request->str('report') : 'sales';
        $key = in_array($this->request->str('period'), ['month', 'prev_month', 'quarter', 'year', 'custom'], true) ? $this->request->str('period') : 'month';
        $p = FinanceStats::period($key, $this->request->str('from'), $this->request->str('to'));
        $group = in_array($this->request->str('group'), ['day', 'week', 'month'], true) ? $this->request->str('group') : ($key === 'year' ? 'month' : 'day');
        $data = ReportService::run($report, $p['from'], $p['to'], ['group' => $group]);
        if ($this->request->str('export') === 'csv') {
            ActivityLogger::log('report.exported', null, null, $report . ' ' . $p['from'] . ' → ' . $p['to']);
            $name = 'bwa-' . $report . '-' . substr($p['from'], 0, 10) . '_' . date('Y-m-d', strtotime($p['to'] . ' -1 day')) . '.csv';
            return new Response(ReportService::csv($data), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="' . $name . '"', 'Cache-Control' => 'private, no-store',
            ]);
        }
        $chart = null;
        if ($data['chart'] !== null && $data['rows'] !== []) {
            $rows = array_slice($data['rows'], 0, 15);
            $chart = ['report' => ['labels' => array_map(fn ($r) => (string) $r[$data['chart']['label']], $rows), 'values' => array_map(fn ($r) => (float) $r[$data['chart']['value']], $rows)],
                'currency' => (string) setting('currency_symbol', 'DH'), 'i18n' => ['empty' => __('admin.chart_empty'), 'orders' => ''],
                'money' => in_array($data['chart']['value'], ['sales', 'revenue', 'ttc'], true)];
        }
        return $this->view('admin/reports/index', ['report' => $report, 'key' => $key, 'period' => $p, 'group' => $group, 'data' => $data, 'chart' => $chart]);
    }
}
