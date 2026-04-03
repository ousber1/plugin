<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\AdAnalysis;
use App\Models\AdMetric;
use App\Models\AdSet;
use App\Models\Campaign;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AdsController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::withCount('adSets')
            ->with(['adSets.ads.metrics' => fn($q) => $q->orderByDesc('date')->limit(1)])
            ->latest()
            ->get();

        $totalSpend = AdMetric::sum('cost');
        $totalRevenue = AdMetric::sum('revenue');
        $totalConversions = AdMetric::sum('conversions');
        $avgRoi = AdMetric::whereNotNull('roi')->avg('roi');
        $avgCpa = AdMetric::whereNotNull('cpa')->avg('cpa');
        $avgCtr = AdMetric::whereNotNull('ctr')->avg('ctr');

        $recentAnalysis = AdAnalysis::with('ad.adSet.campaign')
            ->latest('analyzed_at')
            ->limit(10)
            ->get();

        return view('ads.index', compact(
            'campaigns', 'totalSpend', 'totalRevenue', 'totalConversions',
            'avgRoi', 'avgCpa', 'avgCtr', 'recentAnalysis'
        ));
    }

    public function campaigns()
    {
        $campaigns = Campaign::withCount('adSets')->latest()->paginate(20);
        return view('ads.campaigns', compact('campaigns'));
    }

    public function storeCampaign(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'platform' => 'required|in:meta,google,tiktok,other',
            'external_id' => 'nullable|string',
            'status' => 'in:active,paused,completed,draft',
            'budget' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        Campaign::create($validated);
        return redirect()->route('ads.index')->with('success', 'Campaign created successfully.');
    }

    public function updateCampaign(Request $request, $id)
    {
        $campaign = Campaign::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'platform' => 'required|in:meta,google,tiktok,other',
            'status' => 'in:active,paused,completed,draft',
            'budget' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $campaign->update($validated);
        return redirect()->route('ads.index')->with('success', 'Campaign updated.');
    }

    public function adSets($campaignId)
    {
        $campaign = Campaign::with('adSets.ads')->findOrFail($campaignId);
        return view('ads.ad-sets', compact('campaign'));
    }

    public function storeAdSet(Request $request)
    {
        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'name' => 'required|string|max:255',
            'status' => 'in:active,paused,completed',
            'budget' => 'nullable|numeric|min:0',
            'targeting' => 'nullable|array',
        ]);

        AdSet::create($validated);
        return redirect()->route('ads.ad-sets', $validated['campaign_id'])->with('success', 'Ad Set created.');
    }

    public function ads($adSetId)
    {
        $adSet = AdSet::with(['ads.metrics', 'ads.analysis', 'campaign'])->findOrFail($adSetId);
        return view('ads.ads', compact('adSet'));
    }

    public function storeAd(Request $request)
    {
        $validated = $request->validate([
            'ad_set_id' => 'required|exists:ad_sets,id',
            'name' => 'required|string|max:255',
            'headline' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'cta' => 'nullable|string|max:100',
            'image_url' => 'nullable|url',
            'status' => 'in:active,paused,completed',
        ]);

        Ad::create($validated);
        $adSet = AdSet::findOrFail($validated['ad_set_id']);
        return redirect()->route('ads.ads', $adSet->id)->with('success', 'Ad created.');
    }

    public function updateAd(Request $request, $id)
    {
        $ad = Ad::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'headline' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'cta' => 'nullable|string|max:100',
            'status' => 'in:active,paused,completed',
        ]);

        $ad->update($validated);
        return redirect()->back()->with('success', 'Ad updated.');
    }

    public function storeMetrics(Request $request)
    {
        $validated = $request->validate([
            'ad_id' => 'required|exists:ads,id',
            'date' => 'required|date',
            'impressions' => 'required|integer|min:0',
            'clicks' => 'required|integer|min:0',
            'cost' => 'required|numeric|min:0',
            'conversions' => 'required|integer|min:0',
            'revenue' => 'required|numeric|min:0',
        ]);

        AdMetric::updateOrCreate(
            ['ad_id' => $validated['ad_id'], 'date' => $validated['date']],
            $validated
        );

        return redirect()->back()->with('success', 'Metrics saved.');
    }

    public function analyze($adId)
    {
        $ad = Ad::with('metrics')->findOrFail($adId);
        $metrics = $ad->metrics;

        if ($metrics->isEmpty()) {
            return redirect()->back()->with('error', 'No metrics data available for analysis.');
        }

        $totalCost = $metrics->sum('cost');
        $totalRevenue = $metrics->sum('revenue');
        $totalClicks = $metrics->sum('clicks');
        $totalImpressions = $metrics->sum('impressions');
        $totalConversions = $metrics->sum('conversions');

        $roi = $totalCost > 0 ? (($totalRevenue - $totalCost) / $totalCost) * 100 : 0;
        $cpa = $totalConversions > 0 ? $totalCost / $totalConversions : null;
        $ctr = $totalImpressions > 0 ? ($totalClicks / $totalImpressions) * 100 : 0;
        $cpc = $totalClicks > 0 ? $totalCost / $totalClicks : null;

        // Classify ad
        $rating = 'average';
        $recommendations = [];

        if ($roi > 100) {
            $rating = 'winner';
            $recommendations[] = 'This ad is performing excellently. Consider increasing budget.';
        } elseif ($roi > 50) {
            $rating = 'good';
            $recommendations[] = 'Solid performance. Monitor and consider scaling.';
        } elseif ($roi < 0) {
            $rating = 'bad';
            $recommendations[] = 'This ad is losing money. Consider pausing or revising.';
        }

        if ($cpa !== null && $cpa > 50) {
            $recommendations[] = 'CPA is high ($' . number_format($cpa, 2) . '). Review targeting and audience.';
        }

        if ($ctr < 1) {
            $recommendations[] = 'CTR is very low (' . number_format($ctr, 2) . '%). Improve ad creative and headline.';
        }

        if ($totalConversions === 0 && $totalClicks > 50) {
            $recommendations[] = 'High clicks but no conversions. Check landing page or offer.';
        }

        AdAnalysis::updateOrCreate(
            ['ad_id' => $adId],
            [
                'rating' => $rating,
                'recommendations' => implode("\n", $recommendations),
                'analyzed_at' => now(),
            ]
        );

        return redirect()->back()->with('success', "Ad analyzed: {$rating}");
    }

    public function analyzeAll()
    {
        $ads = Ad::with('metrics')->whereHas('metrics')->get();
        $results = ['winner' => 0, 'good' => 0, 'average' => 0, 'bad' => 0];

        foreach ($ads as $ad) {
            $metrics = $ad->metrics;
            $totalCost = $metrics->sum('cost');
            $totalRevenue = $metrics->sum('revenue');
            $totalConversions = $metrics->sum('conversions');

            $roi = $totalCost > 0 ? (($totalRevenue - $totalCost) / $totalCost) * 100 : 0;

            if ($roi > 100) $rating = 'winner';
            elseif ($roi > 50) $rating = 'good';
            elseif ($roi < 0) $rating = 'bad';
            else $rating = 'average';

            $recommendations = [];
            if ($rating === 'bad') $recommendations[] = 'Stop this ad - it is losing money.';
            if ($rating === 'winner') $recommendations[] = 'Scale this ad - increase budget.';

            AdAnalysis::updateOrCreate(
                ['ad_id' => $ad->id],
                [
                    'rating' => $rating,
                    'recommendations' => implode("\n", $recommendations),
                    'analyzed_at' => now(),
                ]
            );

            $results[$rating]++;
        }

        return redirect()->route('ads.index')->with('success',
            "Analysis complete: {$results['winner']} winners, {$results['good']} good, {$results['average']} average, {$results['bad']} bad."
        );
    }

    public function aiSuggestions(Request $request, $adId)
    {
        $ad = Ad::with(['metrics', 'analysis'])->findOrFail($adId);

        $apiKey = Setting::get('openai_api_key') ?: config('services.openai.api_key');
        if (!$apiKey) {
            return redirect()->back()->with('error', 'OpenAI API key not configured. Go to Settings > API Keys.');
        }

        $prompt = "Analyze this ad and suggest improvements:\n";
        $prompt .= "Headline: {$ad->headline}\n";
        $prompt .= "Body: {$ad->body}\n";
        $prompt .= "CTA: {$ad->cta}\n";

        $totalMetrics = $ad->getTotalMetrics();
        if ($totalMetrics) {
            $prompt .= "Performance - CTR: {$totalMetrics['ctr']}%, CPA: \${$totalMetrics['cpa']}, ROI: {$totalMetrics['roi']}%\n";
        }

        $prompt .= "\nProvide:\n1. Analysis of what's weak\n2. 3 improved headline variations\n3. Improved body copy\n4. Better CTA suggestions\n5. New marketing angles to try";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert digital marketing strategist and copywriter.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 1000,
            ]);

            $suggestions = $response->json('choices.0.message.content', 'No suggestions generated.');

            AdAnalysis::updateOrCreate(
                ['ad_id' => $adId],
                ['ai_suggestions' => $suggestions, 'analyzed_at' => now()]
            );

            return redirect()->back()->with('success', 'AI suggestions generated.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to generate AI suggestions: ' . $e->getMessage());
        }
    }

    public function report(Request $request)
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $metrics = AdMetric::with('ad.adSet.campaign')
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $byPlatform = $metrics->groupBy(fn($m) => $m->ad->adSet->campaign->platform ?? 'unknown');

        $platformStats = $byPlatform->map(function ($items) {
            return [
                'spend' => $items->sum('cost'),
                'revenue' => $items->sum('revenue'),
                'clicks' => $items->sum('clicks'),
                'impressions' => $items->sum('impressions'),
                'conversions' => $items->sum('conversions'),
            ];
        });

        return view('ads.report', compact('metrics', 'platformStats', 'startDate', 'endDate'));
    }

    public function syncMeta(Request $request)
    {
        $token = Setting::get('meta_ads_token') ?: config('services.meta_ads.access_token');
        $accountId = Setting::get('meta_ads_account_id') ?: config('services.meta_ads.account_id');

        if (!$token || !$accountId) {
            return redirect()->back()->with('error', 'Meta Ads API not configured. Add credentials in Settings > API Keys.');
        }

        try {
            $response = Http::get("https://graph.facebook.com/v18.0/act_{$accountId}/campaigns", [
                'access_token' => $token,
                'fields' => 'id,name,status,daily_budget,start_time,stop_time',
            ]);

            if ($response->successful()) {
                foreach ($response->json('data', []) as $item) {
                    Campaign::updateOrCreate(
                        ['external_id' => $item['id'], 'platform' => 'meta'],
                        [
                            'name' => $item['name'],
                            'status' => strtolower($item['status'] ?? 'active') === 'active' ? 'active' : 'paused',
                            'budget' => ($item['daily_budget'] ?? 0) / 100,
                        ]
                    );
                }
                return redirect()->back()->with('success', 'Meta campaigns synced.');
            }

            return redirect()->back()->with('error', 'Meta API error: ' . $response->body());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    public function syncGoogle(Request $request)
    {
        return redirect()->back()->with('info', 'Google Ads sync requires OAuth2 setup. Configure in Settings.');
    }
}
