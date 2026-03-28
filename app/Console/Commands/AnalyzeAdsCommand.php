<?php

namespace App\Console\Commands;

use App\Models\Ad;
use App\Models\AdAnalysis;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Console\Command;

class AnalyzeAdsCommand extends Command
{
    protected $signature = 'ads:analyze';
    protected $description = 'Analyze all active ads and generate alerts';

    public function handle(): int
    {
        $ads = Ad::with('metrics')->whereHas('metrics')->get();
        $bad = 0;
        $winners = 0;

        foreach ($ads as $ad) {
            $metrics = $ad->metrics;
            $totalCost = $metrics->sum('cost');
            $totalRevenue = $metrics->sum('revenue');
            $totalConversions = $metrics->sum('conversions');

            $roi = $totalCost > 0 ? (($totalRevenue - $totalCost) / $totalCost) * 100 : 0;

            if ($roi > 100) { $rating = 'winner'; $winners++; }
            elseif ($roi > 50) $rating = 'good';
            elseif ($roi < 0) { $rating = 'bad'; $bad++; }
            else $rating = 'average';

            $recommendations = [];
            if ($rating === 'bad') $recommendations[] = 'Stop this ad - losing money.';
            if ($rating === 'winner') $recommendations[] = 'Scale this ad - increase budget.';

            AdAnalysis::updateOrCreate(
                ['ad_id' => $ad->id],
                ['rating' => $rating, 'recommendations' => implode("\n", $recommendations), 'analyzed_at' => now()]
            );
        }

        // Notify admins
        if ($bad > 0 || $winners > 0) {
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                if ($bad > 0) {
                    AppNotification::create([
                        'user_id' => $admin->id, 'type' => 'bad_ad',
                        'title' => "Warning: {$bad} ads losing money",
                        'message' => "{$bad} ads have negative ROI. Review and pause them.",
                    ]);
                }
                if ($winners > 0) {
                    AppNotification::create([
                        'user_id' => $admin->id, 'type' => 'high_roi',
                        'title' => "{$winners} winning ads detected",
                        'message' => "Consider increasing budget for these high-performing ads.",
                    ]);
                }
            }
        }

        $this->info("Analyzed {$ads->count()} ads: {$winners} winners, {$bad} bad.");
        return Command::SUCCESS;
    }
}
