<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\JobCost;
use App\Models\Material;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductionJob;
use App\Models\Quote;
use App\Models\StockMovement;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Reports. Every metric definition is shown next to the report (see `definition`).
 * Period filters use orders.placed_at (business timezone Africa/Casablanca). Cancelled orders are excluded
 * from revenue metrics. Amounts HT = excluding VAT; "CA HT" = subtotal − discounts + shipping.
 */
class ReportService
{
    public const REPORTS = [
        'sales_product' => 'Ventes par produit', 'sales_channel' => 'Ventes par canal', 'sales_customer' => 'Ventes par client',
        'margins' => 'Marges par travail', 'receivables' => 'Créances clients', 'expenses' => 'Dépenses', 'turnaround' => 'Délais de production',
        'stock_consumption' => 'Consommation de matières', 'waste' => 'Gâche',
    ];

    public function period(?string $from, ?string $to): array
    {
        $f = $from ? CarbonImmutable::parse($from)->startOfDay() : CarbonImmutable::now()->startOfMonth();
        $t = $to ? CarbonImmutable::parse($to)->endOfDay() : CarbonImmutable::now()->endOfDay();

        return [$f, $t];
    }

    protected function orders($f, $t)
    {
        return Order::query()->where('status', '!=', 'cancelled')->whereBetween('placed_at', [$f, $t]);
    }

    public function kpis($f, $t): array
    {
        $orders = $this->orders($f, $t)->get(['id', 'subtotal', 'discount_total', 'shipping_total', 'total']);
        $revenue = Money::sum($orders->map(fn ($o) => Money::of($o->subtotal)->minus($o->discount_total)->plus($o->shipping_total)));
        $itemRevenue = Money::sum(OrderItem::whereIn('order_id', $orders->pluck('id'))->pluck('total'));
        $costs = Money::sum(JobCost::where('is_estimate', false)->whereHas('job', fn ($q) => $q->whereIn('order_id', $orders->pluck('id')))->pluck('total'));
        $accepted = Quote::whereIn('status', ['accepted', 'converted'])->whereBetween('responded_at', [$f, $t])->with('revisions')->get();
        $open = Order::where('status', '!=', 'cancelled')->get(['total', 'amount_paid', 'amount_refunded']);
        $receivable = Money::sum($open->map(fn ($o) => Money::max(Money::of($o->total)->minus($o->amount_paid)->plus($o->amount_refunded), 0)));
        $done = ['ready_for_pickup', 'shipped', 'delivered', 'cancelled'];

        return [
            'orders' => $orders->count(),
            'revenue' => Money::str($revenue),
            'gross_margin' => Money::str($itemRevenue->minus($costs)),
            'costs' => Money::str($costs),
            'accepted_quotes' => $accepted->count(),
            'accepted_value' => Money::str(Money::sum($accepted->map(fn ($q) => $q->revisions->firstWhere('revision', $q->accepted_revision)?->total ?? 0))),
            'pending_proofs' => OrderItem::where('proof_status', 'pending')->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))->count(),
            'jobs_today' => ProductionJob::whereDate('due_at', today())->whereNotIn('stage', $done)->count(),
            'jobs_overdue' => ProductionJob::whereDate('due_at', '<', today())->whereNotIn('stage', $done)->count(),
            'receivables' => Money::str($receivable),
            'stock_alerts' => Material::where('is_active', true)->get()->filter->isLow()->count(),
            'artwork_waiting' => OrderItem::where('artwork_status', 'pending')->where('artwork_mode', 'later')->whereHas('order', fn ($q) => $q->whereNotIn('status', ['cancelled', 'completed']))->count(),
        ];
    }

    /** @return array{columns: array, rows: array, definition: string} */
    public function run(string $report, $f, $t): array
    {
        return match ($report) {
            'sales_product' => $this->salesBy($f, $t, 'product'),
            'sales_channel' => $this->salesBy($f, $t, 'channel'),
            'sales_customer' => $this->salesBy($f, $t, 'customer'),
            'margins' => $this->margins($f, $t),
            'receivables' => $this->receivables(),
            'expenses' => $this->expenses($f, $t),
            'turnaround' => $this->turnaround($f, $t),
            'stock_consumption' => $this->stock($f, $t, ['consumption']),
            'waste' => $this->stock($f, $t, ['waste']),
            default => abort(404),
        };
    }

    protected function salesBy($f, $t, string $by): array
    {
        $ids = $this->orders($f, $t)->pluck('id');
        $rows = [];
        if ($by === 'product') {
            foreach (OrderItem::whereIn('order_id', $ids)->with('product')->get()->groupBy(fn ($i) => $i->product_id ?: 'custom') as $k => $items) {
                $rows[] = [$k === 'custom' ? 'Travaux personnalisés' : ($items->first()->product?->t('name', 'fr') ?? '#'.$k), $items->count(), $items->sum('quantity'), Money::str(Money::sum($items->pluck('total')))];
            }
            usort($rows, fn ($a, $b) => (float) $b[3] <=> (float) $a[3]);

            return ['columns' => ['Produit', 'Lignes', 'Quantité', 'CA lignes HT'], 'rows' => $rows,
                'definition' => 'Somme des totaux HT des lignes (après remises de ligne, avant remise globale et livraison) des commandes non annulées passées dans la période.'];
        }
        $orders = $this->orders($f, $t)->with('customer')->get();
        $group = $by === 'channel' ? $orders->groupBy('channel') : $orders->groupBy('customer_id');
        foreach ($group as $k => $os) {
            $ht = Money::sum($os->map(fn ($o) => Money::of($o->subtotal)->minus($o->discount_total)->plus($o->shipping_total)));
            $rows[] = [$by === 'channel' ? __('channel.'.$k) : $os->first()->customer?->displayName(), $os->count(), Money::str($ht), Money::str(Money::sum($os->pluck('total'))), Money::str($os->count() ? $ht->dividedBy($os->count(), 2, \Brick\Math\RoundingMode::HalfUp) : 0)];
        }
        usort($rows, fn ($a, $b) => (float) $b[2] <=> (float) $a[2]);

        return ['columns' => [$by === 'channel' ? 'Canal' : 'Client', 'Commandes', 'CA HT', 'CA TTC', 'Panier moyen HT'], 'rows' => $rows,
            'definition' => 'CA HT = sous-total − remises + livraison, par commande non annulée passée dans la période. CA TTC = total facturable de la commande.'];
    }

    protected function margins($f, $t): array
    {
        $rows = [];
        $jobs = ProductionJob::whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled')->whereBetween('placed_at', [$f, $t]))->with('item', 'costs', 'order')->get();
        foreach ($jobs as $j) {
            $rev = Money::of($j->item->total);
            $est = Money::sum($j->costs->where('is_estimate', true)->pluck('total'));
            $act = Money::sum($j->costs->where('is_estimate', false)->pluck('total'));
            $rows[] = [$j->number, $j->order->number, mb_substr($j->item->description, 0, 60), Money::str($rev), Money::str($est), Money::str($act), Money::str($rev->minus($act)), $rev->isPositive() ? Money::str($rev->minus($act)->multipliedBy(100)->dividedBy($rev, 1, \Brick\Math\RoundingMode::HalfUp), 1).' %' : '—'];
        }

        return ['columns' => ['Travail', 'Commande', 'Ligne', 'Prix HT', 'Coût estimé', 'Coût réel', 'Marge brute', 'Taux'], 'rows' => $rows,
            'definition' => 'Marge brute = prix de vente HT de la ligne − coûts réels saisis sur le travail (main-d\'œuvre, calage, machine, matières consommées au coût moyen pondéré, sous-traitance, gâche). Les coûts « estimés » ne sont pas déduits.'];
    }

    protected function receivables(): array
    {
        $rows = [];
        foreach (Order::where('status', '!=', 'cancelled')->with('customer')->orderBy('placed_at')->get() as $o) {
            $bal = Money::of($o->balance());
            if ($bal->isPositive()) {
                $rows[] = [$o->number, $o->customer?->displayName(), d($o->placed_at), Money::str($o->total), $o->netPaid(), Money::str($bal), (int) $o->placed_at?->diffInDays(now())];
            }
        }

        return ['columns' => ['Commande', 'Client', 'Date', 'Total TTC', 'Encaissé net', 'Solde dû', 'Âge (jours)'], 'rows' => $rows,
            'definition' => 'Solde dû = total TTC − paiements + remboursements, pour toutes les commandes non annulées (indépendant de la période).'];
    }

    protected function expenses($f, $t): array
    {
        $rows = [];
        foreach (Expense::whereBetween('date', [$f->toDateString(), $t->toDateString()])->get()->groupBy('category') as $cat => $es) {
            $rows[] = [$cat, $es->count(), Money::str(Money::sum($es->pluck('amount')))];
        }

        return ['columns' => ['Catégorie', 'Nombre', 'Montant'], 'rows' => $rows,
            'definition' => 'Somme des dépenses saisies (montants TTC tels qu\'enregistrés) dont la date est dans la période. Les achats de matières passent par les bons de commande.'];
    }

    protected function turnaround($f, $t): array
    {
        $rows = [];
        $jobs = ProductionJob::whereNotNull('completed_at')->whereBetween('completed_at', [$f, $t])->with('order', 'item')->get();
        foreach ($jobs as $j) {
            $hours = $j->order->placed_at ? round($j->order->placed_at->diffInHours($j->completed_at), 1) : null;
            $rows[] = [$j->number, $j->order->number, d($j->order->placed_at, true), d($j->completed_at, true), $hours !== null ? round($hours / 24, 1) : '—', $j->due_at && $j->completed_at->toDateString() > $j->due_at->toDateString() ? 'En retard' : 'À l\'heure'];
        }

        return ['columns' => ['Travail', 'Commande', 'Commandé le', 'Terminé le', 'Jours calendaires', 'Échéance'], 'rows' => $rows,
            'definition' => 'Délai = de la passation de commande au passage du travail en « prêt / expédié / livré » (completed_at), en jours calendaires. Les attentes de fichier et de BAT sont incluses.'];
    }

    protected function stock($f, $t, array $types): array
    {
        $rows = [];
        $mv = StockMovement::whereIn('type', $types)->whereBetween('created_at', [$f, $t])->with('material')->get()->groupBy('material_id');
        foreach ($mv as $mid => $ms) {
            $qty = Money::sum($ms->pluck('quantity'))->abs();
            $value = Money::sum($ms->map(fn ($m) => Money::of($m->quantity)->abs()->multipliedBy($m->unit_cost)));
            $rows[] = [$ms->first()->material->sku, $ms->first()->material->name, $ms->first()->material->unit, Money::str($qty, 3), Money::str($value)];
        }

        return ['columns' => ['Réf.', 'Matière', 'Unité', 'Quantité', 'Valeur (coût moyen)'], 'rows' => $rows,
            'definition' => in_array('waste', $types) ? 'Quantités déclarées en gâche sur les travaux, valorisées au coût unitaire au moment du mouvement.' : 'Quantités consommées sur les travaux (hors gâche), valorisées au coût unitaire au moment du mouvement.'];
    }
}
