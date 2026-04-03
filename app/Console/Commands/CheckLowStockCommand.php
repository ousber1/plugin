<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;

class CheckLowStockCommand extends Command
{
    protected $signature = 'stock:check';
    protected $description = 'Check for low stock products and send alerts';

    public function handle(): int
    {
        $lowStock = Product::active()->lowStock()->get();

        if ($lowStock->isEmpty()) {
            $this->info('All stock levels are OK.');
            return Command::SUCCESS;
        }

        $admins = User::where('role', 'admin')->get();
        $productNames = $lowStock->pluck('name')->take(5)->implode(', ');
        $count = $lowStock->count();

        foreach ($admins as $admin) {
            AppNotification::create([
                'user_id' => $admin->id,
                'type' => 'low_stock',
                'title' => "{$count} products are low on stock",
                'message' => "Products running low: {$productNames}" . ($count > 5 ? " and " . ($count - 5) . " more" : ''),
                'data' => ['product_ids' => $lowStock->pluck('id')->toArray()],
            ]);
        }

        $this->info("Found {$count} low stock products. Notifications sent.");
        return Command::SUCCESS;
    }
}
