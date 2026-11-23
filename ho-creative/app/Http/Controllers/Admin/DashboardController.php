<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductionJob;
use App\Services\ReportService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, ReportService $reports)
    {
        $this->authorize('dashboard.view');
        [$from, $to] = $reports->period($request->query('from'), $request->query('to'));
        $k = $reports->kpis($from, $to);
        $done = ['ready_for_pickup', 'shipped', 'delivered', 'cancelled'];
        $dueJobs = ProductionJob::with('order', 'item')->whereNotIn('stage', $done)->whereDate('due_at', '<=', today())->orderBy('due_at')->limit(15)->get();
        $proofs = OrderItem::with('order')->where('proof_status', 'pending')->latest('updated_at')->limit(10)->get();
        $recent = Order::with('customer')->latest()->limit(10)->get();
        $lowStock = Material::where('is_active', true)->get()->filter->isLow()->take(10);
        $hasDemo = Order::where('is_demo', true)->exists();

        return view('admin.dashboard', compact('k', 'from', 'to', 'dueJobs', 'proofs', 'recent', 'lowStock', 'hasDemo'));
    }
}
