<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncAdsDataCommand extends Command
{
    protected $signature = 'ads:sync';
    protected $description = 'Sync advertising data from Meta and Google APIs';

    public function handle(): int
    {
        $this->syncMeta();
        $this->info('Ads data sync completed.');
        return Command::SUCCESS;
    }

    private function syncMeta(): void
    {
        $token = config('services.meta_ads.access_token');
        $accountId = config('services.meta_ads.account_id');

        if (!$token || !$accountId) {
            $this->warn('Meta Ads API not configured. Skipping.');
            return;
        }

        try {
            $response = Http::get("https://graph.facebook.com/v18.0/act_{$accountId}/campaigns", [
                'access_token' => $token,
                'fields' => 'id,name,status,daily_budget',
            ]);

            if ($response->successful()) {
                $count = 0;
                foreach ($response->json('data', []) as $item) {
                    Campaign::updateOrCreate(
                        ['external_id' => $item['id'], 'platform' => 'meta'],
                        [
                            'name' => $item['name'],
                            'status' => strtolower($item['status'] ?? 'active') === 'active' ? 'active' : 'paused',
                            'budget' => ($item['daily_budget'] ?? 0) / 100,
                        ]
                    );
                    $count++;
                }
                $this->info("Synced {$count} Meta campaigns.");
            } else {
                $this->error('Meta API error: ' . $response->status());
            }
        } catch (\Exception $e) {
            $this->error('Meta sync failed: ' . $e->getMessage());
        }
    }
}
