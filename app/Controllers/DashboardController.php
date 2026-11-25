<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\DashboardService;

/**
 * Tableau de bord : indicateurs calculés en direct depuis la base.
 * Les montants financiers ne sont visibles qu'avec la permission dashboard.financial.
 */
final class DashboardController extends Controller
{
    public function index(): void
    {
        if (!can('dashboard.view')) {
            $this->redirect('profile');
        }
        $service = new DashboardService();
        $financial = can('dashboard.financial');

        $data = [
            'title'      => 'Tableau de bord',
            'financial'  => $financial,
            'counters'   => $service->counters(),
            'lowStock'   => can('stock.view') || can('products.view') ? $service->lowStock() : [],
            'urgent'     => can('production.view') ? $service->urgentProduction() : [],
            'machines'   => can('machines.view') ? $service->machinePerformance() : [],
            'activity'   => can('logs.view') ? $service->recentActivity() : [],
        ];
        if ($financial) {
            $data += [
                'kpis'         => $service->financialKpis(),
                'monthly'      => $service->monthlySeries(),
                'daily'        => $service->dailySeries(30),
                'byCategory'   => $service->salesByCategory(),
                'byMachine'    => $service->salesByMachine(),
                'topProducts'  => $service->topItems(false),
                'topServices'  => $service->topItems(true),
                'topCustomers' => $service->topCustomers(),
                'payments'     => can('payments.view') ? $service->recentPayments() : [],
            ];
        }
        $this->view('dashboard/index', $data);
    }
}
