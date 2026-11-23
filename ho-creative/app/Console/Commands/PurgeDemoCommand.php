<?php

namespace App\Console\Commands;

use App\Models;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeDemoCommand extends Command
{
    protected $signature = 'hocreative:purge-demo {--force}';

    protected $description = 'Delete every record flagged is_demo (orders, quotes, customers, catalog samples, stock…)';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Supprimer toutes les données de démonstration ?')) {
            return self::SUCCESS;
        }
        DB::transaction(function () {
            $orders = Models\Order::where('is_demo', true)->pluck('id');
            Models\Document::whereIn('order_id', $orders)->delete();
            Models\Payment::whereIn('order_id', $orders)->delete();
            $jobs = Models\ProductionJob::whereIn('order_id', $orders)->pluck('id');
            Models\StockMovement::whereIn('production_job_id', $jobs)->delete();
            Models\Order::whereIn('id', $orders)->delete();
            $quotes = Models\Quote::where('is_demo', true)->pluck('id');
            Models\Document::whereIn('quote_id', $quotes)->delete();
            Models\Quote::whereIn('id', $quotes)->delete();
            Models\Review::where('is_demo', true)->delete();
            Models\Expense::where('is_demo', true)->delete();
            $mats = Models\Material::where('is_demo', true)->pluck('id');
            Models\StockMovement::whereIn('material_id', $mats)->delete();
            Models\PurchaseOrderItem::whereIn('material_id', $mats)->delete();
            Models\Material::whereIn('id', $mats)->delete();
            Models\Customer::where('is_demo', true)->whereDoesntHave('orders')->whereDoesntHave('quotes')->each(fn ($c) => $c->delete());
            Models\User::where('email', 'like', '%@demo.hocreative.test')->delete();
        });
        $this->info('Données de démonstration transactionnelles supprimées. Les produits/modèles d\'exemple (is_demo) restent : dépubliez-les ou supprimez-les depuis le back-office.');

        return self::SUCCESS;
    }
}
