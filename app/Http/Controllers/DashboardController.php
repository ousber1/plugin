<?php

namespace App\Http\Controllers;

use App\Models\AdMetric;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $totalRevenue = Sale::whereNull('deleted_at')->where('status', '!=', 'cancelled')->sum('total');
        $todayRevenue = Sale::whereNull('deleted_at')->where('status', '!=', 'cancelled')
            ->whereDate('created_at', Carbon::today())->sum('total');
        $monthlyRevenue = Sale::whereNull('deleted_at')->where('status', '!=', 'cancelled')
            ->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('total');
        $totalOrders = Sale::whereNull('deleted_at')->count();
        $totalCustomers = Customer::count();

        $recentSales = Sale::with('customer:id,name')->whereNull('deleted_at')
            ->latest()->limit(10)->get();

        $lowStockProducts = Product::where('is_active', true)->whereNull('deleted_at')
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')->limit(10)->get();

        $topProducts = SaleItem::select('product_id', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(total) as total_revenue'))
            ->with('product:id,name')->groupBy('product_id')->orderByDesc('total_qty')->limit(5)->get();

        // Chart data: last 30 days
        $salesChartData = Sale::whereNull('deleted_at')->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total) as total'))
            ->groupBy('date')->orderBy('date')->get();

        $channelData = [
            'pos' => Sale::whereNull('deleted_at')->where('channel', 'pos')->sum('total'),
            'online' => Sale::whereNull('deleted_at')->where('channel', 'online')->sum('total'),
        ];

        $adsMetrics = [
            'total_spend' => AdMetric::sum('cost'),
            'avg_roi' => AdMetric::whereNotNull('roi')->avg('roi') ?? 0,
        ];

        return view('dashboard.index', compact(
            'totalRevenue', 'todayRevenue', 'monthlyRevenue', 'totalOrders', 'totalCustomers',
            'recentSales', 'lowStockProducts', 'topProducts', 'salesChartData', 'channelData', 'adsMetrics'
        ));
    }
}
