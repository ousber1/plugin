<?php

namespace App\Http\Controllers;

use App\Models\AdMetric;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    public function sales(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());
        $channel = $request->get('channel');
        $groupBy = $request->get('group_by', 'day');

        $query = Sale::whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->where('status', '!=', 'cancelled');

        if ($channel) {
            $query->where('channel', $channel);
        }

        $dateFormat = match ($groupBy) {
            'week' => '%Y-%W',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $driver = DB::getDriverName();
        $dateExpr = $driver === 'sqlite'
            ? "strftime('{$dateFormat}', created_at)"
            : "DATE_FORMAT(created_at, '" . str_replace('%W', '%u', $dateFormat) . "')";

        $salesByPeriod = (clone $query)->select(
            DB::raw("{$dateExpr} as period"),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(total) as revenue'),
            DB::raw('SUM(discount) as discounts')
        )->groupBy('period')->orderBy('period')->get();

        $totalRevenue = (clone $query)->sum('total');
        $totalOrders = (clone $query)->count();
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        $posSales = (clone $query)->where('channel', 'pos')->sum('total');
        $onlineSales = (clone $query)->where('channel', 'online')->sum('total');

        $sales = $query->with('customer', 'items.product')->latest()->paginate(50);

        return view('reports.sales', compact(
            'sales', 'salesByPeriod', 'totalRevenue', 'totalOrders', 'avgOrderValue',
            'posSales', 'onlineSales', 'startDate', 'endDate', 'channel', 'groupBy'
        ));
    }

    public function products(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $topProducts = SaleItem::select(
            'product_id',
            DB::raw('SUM(quantity) as total_qty'),
            DB::raw('SUM(total) as total_revenue')
        )
            ->whereHas('sale', fn($q) => $q->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])->where('status', '!=', 'cancelled'))
            ->groupBy('product_id')
            ->orderByDesc('total_revenue')
            ->with('product')
            ->limit(20)
            ->get();

        $slowProducts = Product::active()
            ->whereDoesntHave('saleItems', fn($q) => $q->whereHas('sale', fn($q2) => $q2->where('created_at', '>=', now()->subDays(30))))
            ->limit(20)
            ->get();

        $productMargins = SaleItem::select(
            'product_id',
            DB::raw('SUM(quantity) as qty'),
            DB::raw('SUM(sale_items.total) as revenue')
        )
            ->whereHas('sale', fn($q) => $q->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59']))
            ->groupBy('product_id')
            ->with('product')
            ->get()
            ->map(function ($item) {
                $cost = ($item->product->cost_price ?? 0) * $item->qty;
                $margin = $item->revenue - $cost;
                $marginPct = $item->revenue > 0 ? ($margin / $item->revenue) * 100 : 0;
                return [
                    'product' => $item->product,
                    'qty' => $item->qty,
                    'revenue' => $item->revenue,
                    'cost' => $cost,
                    'margin' => $margin,
                    'margin_pct' => $marginPct,
                ];
            })
            ->sortByDesc('margin');

        return view('reports.products', compact('topProducts', 'slowProducts', 'productMargins', 'startDate', 'endDate'));
    }

    public function customers(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $topCustomers = Customer::select('customers.*')
            ->withCount(['sales as order_count' => fn($q) => $q->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])])
            ->withSum(['sales as total_revenue' => fn($q) => $q->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])], 'total')
            ->orderByDesc('total_revenue')
            ->limit(20)
            ->get();

        $newCustomers = Customer::whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])->count();
        $totalCustomers = Customer::count();
        $repeatCustomers = Customer::has('sales', '>=', 2)->count();

        return view('reports.customers', compact('topCustomers', 'newCustomers', 'totalCustomers', 'repeatCustomers', 'startDate', 'endDate'));
    }

    public function profit(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $salesRevenue = Sale::whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->where('status', '!=', 'cancelled')
            ->sum('total');

        $costOfGoods = SaleItem::whereHas('sale', fn($q) => $q->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])->where('status', '!=', 'cancelled'))
            ->get()
            ->sum(fn($item) => ($item->product->cost_price ?? 0) * $item->quantity);

        $adSpend = AdMetric::whereBetween('date', [$startDate, $endDate])->sum('cost');

        $grossProfit = $salesRevenue - $costOfGoods;
        $netProfit = $grossProfit - $adSpend;
        $grossMargin = $salesRevenue > 0 ? ($grossProfit / $salesRevenue) * 100 : 0;
        $netMargin = $salesRevenue > 0 ? ($netProfit / $salesRevenue) * 100 : 0;

        return view('reports.profit', compact(
            'salesRevenue', 'costOfGoods', 'adSpend', 'grossProfit', 'netProfit',
            'grossMargin', 'netMargin', 'startDate', 'endDate'
        ));
    }

    public function exportPdf(Request $request)
    {
        return redirect()->back()->with('info', 'PDF export requires dompdf package. Run: composer require barryvdh/laravel-dompdf');
    }

    public function exportExcel(Request $request)
    {
        $type = $request->get('type', 'sales');
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $filename = "{$type}_report_{$startDate}_to_{$endDate}.csv";
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename={$filename}"];

        $callback = function () use ($type, $startDate, $endDate) {
            $file = fopen('php://output', 'w');

            if ($type === 'sales') {
                fputcsv($file, ['Invoice', 'Date', 'Customer', 'Channel', 'Status', 'Total', 'Payment Status']);
                Sale::whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
                    ->with('customer')
                    ->orderBy('created_at')
                    ->chunk(100, function ($sales) use ($file) {
                        foreach ($sales as $sale) {
                            fputcsv($file, [
                                $sale->invoice_number,
                                $sale->created_at->format('Y-m-d H:i'),
                                $sale->customer->name ?? 'Walk-in',
                                $sale->channel,
                                $sale->status,
                                $sale->total,
                                $sale->payment_status,
                            ]);
                        }
                    });
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
