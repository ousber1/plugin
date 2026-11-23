<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    protected function guard(string $report): void
    {
        $this->authorize('reports.view');
        if (in_array($report, ['margins', 'receivables', 'expenses', 'sales_product', 'sales_channel', 'sales_customer'])) {
            $this->authorize('finance.view');
        }
    }

    public function index(Request $request, ReportService $reports)
    {
        $report = $request->query('report', 'sales_product');
        abort_unless(isset(ReportService::REPORTS[$report]), 404);
        $this->guard($report);
        [$from, $to] = $reports->period($request->query('from'), $request->query('to'));
        $data = $reports->run($report, $from, $to);

        return view('admin.reports', compact('report', 'from', 'to', 'data'));
    }

    public function csv(Request $request, string $report, ReportService $reports)
    {
        abort_unless(isset(ReportService::REPORTS[$report]), 404);
        $this->guard($report);
        [$from, $to] = $reports->period($request->query('from'), $request->query('to'));
        $data = $reports->run($report, $from, $to);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $data['columns'], ';');
            foreach ($data['rows'] as $row) {
                fputcsv($out, $row, ';');
            }
            fputcsv($out, []);
            fputcsv($out, ['Définition : '.$data['definition']], ';');
            fclose($out);
        }, $report.'-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
