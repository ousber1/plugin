<?php

namespace App\Http\Controllers;

use App\Models\AdMetric;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $currency = Setting::get('currency') ?? 'DH';

        $totalRevenue = Sale::whereNull('deleted_at')->where('status', '!=', 'cancelled')->sum('total');
        $todayRevenue = Sale::whereNull('deleted_at')->where('status', '!=', 'cancelled')
            ->whereDate('created_at', Carbon::today())->sum('total');
        $monthlyRevenue = Sale::whereNull('deleted_at')->where('status', '!=', 'cancelled')
            ->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('total');
        $totalOrders = Sale::whereNull('deleted_at')->count();
        $totalCustomers = Customer::count();
        $totalProducts = Product::whereNull('deleted_at')->where('is_active', true)->count();

        // Today stats
        $todayOrders = Sale::whereNull('deleted_at')->whereDate('created_at', Carbon::today())->count();
        $newCustomersThisMonth = Customer::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();

        // Yesterday comparison
        $yesterdayRevenue = Sale::whereNull('deleted_at')->where('status', '!=', 'cancelled')
            ->whereDate('created_at', Carbon::yesterday())->sum('total');
        $revenueChange = $yesterdayRevenue > 0 ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1) : 0;

        // Profit estimate (cost_price vs selling_price)
        $totalProfit = SaleItem::join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereNull('sales.deleted_at')
            ->where('sales.status', '!=', 'cancelled')
            ->select(DB::raw('SUM(sale_items.total - (products.cost_price * sale_items.quantity)) as profit'))
            ->value('profit') ?? 0;

        // Payment methods breakdown
        $paymentMethods = DB::table('payments')
            ->select('method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('method')
            ->get()
            ->keyBy('method');

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

        // Monthly revenue chart (last 12 months)
        $monthlyChartData = Sale::whereNull('deleted_at')->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subMonths(12))
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw('SUM(total) as total'))
            ->groupBy('month')->orderBy('month')->get();

        $adsMetrics = [
            'total_spend' => AdMetric::sum('cost'),
            'avg_roi' => AdMetric::whereNotNull('roi')->avg('roi') ?? 0,
        ];

        return view('dashboard.index', compact(
            'currency', 'totalRevenue', 'todayRevenue', 'monthlyRevenue', 'totalOrders', 'totalCustomers',
            'totalProducts', 'todayOrders', 'newCustomersThisMonth', 'revenueChange', 'yesterdayRevenue',
            'totalProfit', 'paymentMethods',
            'recentSales', 'lowStockProducts', 'topProducts', 'salesChartData', 'channelData',
            'monthlyChartData', 'adsMetrics'
        ));
    }
}
