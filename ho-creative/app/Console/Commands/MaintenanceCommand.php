<?php

namespace App\Console\Commands;

use App\Models\ArtworkFile;
use App\Models\Cart;
use App\Models\Material;
use App\Models\Quote;
use App\Notifications\SimpleNotification;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/** Daily housekeeping, bounded so it fits in a shared-hosting cron run. */
class MaintenanceCommand extends Command
{
    protected $signature = 'hocreative:daily';

    protected $description = 'Expire quotes, purge abandoned carts, apply file retention, send low-stock alert';

    public function handle(Settings $settings): int
    {
        $expired = Quote::where('status', 'sent')->whereDate('expires_at', '<', today())->update(['status' => 'expired']);
        $carts = 0;
        foreach (Cart::where('updated_at', '<', now()->subDays((int) $settings->get('retention.cart_days', 30)))->limit(500)->get() as $cart) {
            foreach ($cart->items as $it) {
                foreach ($it->artworkFiles as $f) {
                    Storage::disk('private')->delete($f->path);
                    $f->delete();
                }
            }
            $cart->delete();
            $carts++;
        }
        $purged = 0;
        $days = (int) $settings->get('retention.artwork_days', 365);
        if ($days > 0) {
            $files = ArtworkFile::whereHas('orderItem.order', fn ($q) => $q->whereIn('status', ['completed', 'cancelled'])->where('updated_at', '<', now()->subDays($days)))
                ->where('path', 'not like', 'purged:%')->limit(500)->get();
            foreach ($files as $f) {
                Storage::disk('private')->delete($f->path);
                $f->update(['path' => 'purged:'.$f->path]);
                $purged++;
            }
        }
        $low = Material::where('is_active', true)->get()->filter->isLow();
        if ($low->isNotEmpty() && ($to = $settings->get('contact.email'))) {
            try {
                Notification::route('mail', $to)->notify(new SimpleNotification('order_status', ['order' => 'STOCK', 'status' => 'Stock bas : '.$low->map(fn ($m) => $m->name.' ('.$m->available().' '.$m->unit.')')->implode(', '), 'url' => route('admin.stock.index')], 'fr'));
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $this->info("Devis expirés : $expired · paniers purgés : $carts · fichiers purgés : $purged · alertes stock : {$low->count()}");

        return self::SUCCESS;
    }
}
