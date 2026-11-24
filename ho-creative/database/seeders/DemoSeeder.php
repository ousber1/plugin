<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Machine;
use App\Models\Material;
use App\Models\Product;
use App\Models\Review;
use App\Models\Supplier;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\Pricing\PricingEngine;
use App\Services\QuoteService;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Optional, clearly labelled demo data (is_demo = true, "DÉMO" badges in the back office).
 * Creates NO login accounts. Remove with: php artisan hocreative:purge-demo
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Customer::where('is_demo', true)->exists()) {
            return;
        }
        $engine = app(PricingEngine::class);
        $orders = app(OrderService::class);
        $payments = app(PaymentService::class);
        $stock = app(StockService::class);

        $sup1 = Supplier::create(['name' => 'Papeterie Exemple SARL (démo)', 'contact_name' => 'M. Exemple', 'phone' => '+212 5 22 00 00 01', 'city' => 'Casablanca', 'is_demo' => true]);
        $sup2 = Supplier::create(['name' => 'Grand Format Fournitures (démo)', 'phone' => '+212 5 22 00 00 02', 'city' => 'Casablanca', 'is_demo' => true]);
        $mats = [
            ['PAP-350', 'Couché mat 350 g SRA3', 'paper', 'sheet', 2500, 300, '1.20', $sup1],
            ['PAP-135', 'Couché brillant 135 g SRA3', 'paper', 'sheet', 4000, 500, '0.45', $sup1],
            ['BAN-510', 'Bâche frontlit 510 g – rouleau 3,2 m', 'banner', 'm2', 180, 40, '22.00', $sup2],
            ['VIN-WHT', 'Vinyle blanc monomère 1,37 m', 'vinyl', 'm2', 25, 30, '35.00', $sup2],
            ['LAM-MAT', 'Film de pelliculage mat', 'film', 'm', 800, 100, '0.90', $sup1],
            ['INK-ECO', 'Encre éco-solvant (cartouche)', 'ink', 'piece', 6, 4, '650.00', $sup2],
        ];
        $m = [];
        foreach ($mats as [$sku, $name, $cat, $unit, $qty, $low, $cost, $sup]) {
            $m[$sku] = Material::create(['sku' => $sku, 'name' => $name, 'category' => $cat, 'unit' => $unit, 'low_stock_threshold' => $low, 'unit_cost' => $cost, 'supplier_id' => $sup->id, 'is_demo' => true]);
            $stock->move($m[$sku], 'receipt', (string) $qty, 'demo:init:'.$sku, ['unit_cost' => $cost, 'notes' => 'Stock initial (démo)']);
        }
        Machine::firstOrCreate(['name' => 'Presse numérique A3+'], ['department' => 'digital', 'hourly_cost' => '180']);
        Machine::firstOrCreate(['name' => 'Traceur éco-solvant 3,2 m'], ['department' => 'large_format', 'hourly_cost' => '220']);
        Machine::firstOrCreate(['name' => 'Massicot / pelliculeuse'], ['department' => 'finishing', 'hourly_cost' => '90']);

        $c1 = Customer::create(['type' => 'individual', 'name' => 'Client Démo Particulier', 'email' => 'particulier@demo.hocreative.test', 'phone' => '+212 6 00 00 00 11', 'city' => 'Casablanca', 'source' => 'web', 'is_demo' => true]);
        $c2 = Customer::create(['type' => 'company', 'name' => 'Responsable Achats', 'company_name' => 'Société Démo SARL', 'email' => 'achats@demo.hocreative.test', 'phone' => '+212 5 22 00 00 12', 'whatsapp' => '+212 6 00 00 00 12', 'ice' => '000000000000000', 'city' => 'Rabat', 'source' => 'b2b', 'is_demo' => true]);
        $c3 = Customer::create(['type' => 'individual', 'name' => 'Restaurant Démo', 'phone' => '+212 6 00 00 00 13', 'whatsapp' => '+212 6 00 00 00 13', 'city' => 'Marrakech', 'source' => 'whatsapp', 'is_demo' => true]);
        $c2->contacts()->create(['name' => 'Comptabilité Démo', 'position' => 'Comptable', 'email' => 'compta@demo.hocreative.test']);
        $c2->addresses()->create(['type' => 'billing', 'name' => 'Société Démo SARL', 'line1' => '1 avenue Exemple', 'city' => 'Rabat', 'is_default' => true]);

        $line = function (string $slug, array $sel, string $mode = 'later') use ($engine) {
            $p = Product::where('slug', $slug)->firstOrFail();
            $r = $engine->forProduct($p, $sel + ['artwork_mode' => $mode]);
            if (! $r->valid) {
                throw new \RuntimeException($slug.': '.implode(' ', $r->errors));
            }

            return OrderService::lineFromPrice($p, $r, ['artwork_mode' => $mode]);
        };

        $o1 = $orders->create($c1, [$line('cartes-de-visite-standard', ['quantity' => 500, 'size' => 'std', 'options' => ['paper' => 'couche350', 'sides' => 'rv', 'lamination' => 'mat']])],
            ['channel' => 'web', 'payment_method' => 'bank_transfer', 'contact_email' => $c1->email, 'is_demo' => true, 'checkout_key' => (string) Str::uuid()]);
        $o2 = $orders->create($c2, [
            $line('bache-pvc-sur-mesure', ['quantity' => 2, 'size' => 'custom', 'width' => 300, 'height' => 120, 'unit' => 'cm', 'options' => ['material' => 'frontlit', 'finishing' => 'eyelets']]),
            $line('roll-up-enrouleur', ['quantity' => 2, 'size' => '85', 'options' => ['frame' => 'std']]),
        ], ['channel' => 'b2b', 'shipping_method' => 'delivery', 'shipping_zone_id' => \App\Models\ShippingZone::where('is_default', false)->orderBy('sort')->skip(1)->value('id'), 'shipping_address' => ['name' => 'Société Démo SARL', 'line1' => '1 avenue Exemple', 'city' => 'Rabat'], 'is_demo' => true, 'status' => 'confirmed', 'checkout_key' => (string) Str::uuid()]);
        $payments->record($o2, 'payment', 'bank_transfer', \App\Support\Money::str(\App\Support\Money::of($o2->total)->multipliedBy('0.5')), 'demo:dep:'.$o2->id, ['notes' => 'Acompte 50 % (démo)']);
        $o3 = $orders->create($c3, [$line('menus-restaurant-plastifies', ['quantity' => 25, 'size' => 'a4', 'options' => ['lamination' => 'thick']], 'design_service')],
            ['channel' => 'whatsapp', 'is_demo' => true, 'checkout_key' => (string) Str::uuid()]);
        $job = $o2->items->first()->job;
        $stock->reserve($m['BAN-510'], '7.5', $job, 'demo:res:'.$job->id);

        $q = app(QuoteService::class)->create($c2, app(QuoteService::class)->priceLines([
            ['product_id' => Product::where('slug', 'affiches-grand-format')->value('id'), 'selection' => ['quantity' => 50, 'size' => 'a2', 'options' => ['paper' => 'satin']]],
            ['description' => 'Pose sur site – forfait (démo)', 'quantity' => 1, 'unit_price' => '400'],
        ]), ['channel' => 'whatsapp', 'is_demo' => true, 'notes' => 'Devis de démonstration.']);
        app(QuoteService::class)->send($q);

        foreach ([['cartes-de-visite-standard', 5, 'Qualité impeccable', 'Papier épais et couleurs fidèles, livrées en 3 jours.'], ['bache-pvc-sur-mesure', 4, 'Très bonne bâche', 'Œillets solides, rendu net à distance.'], ['flyers-publicitaires', 5, 'Rapide', 'BAT reçu le jour même, très pro.']] as [$slug, $rating, $title, $body]) {
            Review::create(['product_id' => Product::where('slug', $slug)->value('id'), 'author_name' => 'Client démo', 'rating' => $rating, 'title' => $title, 'body' => $body.' (avis de démonstration)', 'status' => 'approved', 'is_demo' => true]);
        }
        $this->retail($sup1, $c2);

        foreach ([['rent', 'Loyer atelier (démo)', '6000'], ['utilities', 'Électricité (démo)', '1450'], ['maintenance', 'Entretien presse (démo)', '900']] as [$cat, $desc, $amt]) {
            Expense::create(['date' => now()->startOfMonth()->toDateString(), 'category' => $cat, 'description' => $desc, 'amount' => $amt, 'is_demo' => true]);
        }
    }

    /** Demo store: rayons, articles with barcodes, a closed and an open till session. */
    protected function retail(Supplier $supplier, Customer $company): void
    {
        $retail = app(\App\Services\RetailService::class);
        $cats = [];
        foreach ([['Papeterie', '#E4007C'], ['Fournitures de bureau', '#0096D6'], ['Encre & toner', '#16193A'], ['Cadeaux & déco', '#D9A700'], ['Informatique', '#0B875B']] as $i => [$name, $color]) {
            $cats[$name] = \App\Models\RetailCategory::create(['name' => $name, 'color' => $color, 'sort' => $i]);
        }
        $items = [
            ['Papeterie', 'Ramette papier A4 80 g (500 f.)', 'RAM-A480', '6111000000017', 'ream', '45.00', 31, '29.00', 8],
            ['Papeterie', 'Cahier 96 pages grands carreaux', 'CAH-96', '6111000000024', 'piece', '8.50', 120, '4.20', 20],
            ['Papeterie', 'Bloc-notes A5 spiralé', 'BLO-A5', '6111000000031', 'piece', '15.00', 40, '7.50', 10],
            ['Papeterie', 'Enveloppes C5 (boîte de 50)', 'ENV-C5', '6111000000048', 'pack', '35.00', 18, '19.00', 5],
            ['Fournitures de bureau', 'Stylo bille bleu', 'STY-BL', '6111000000055', 'piece', '3.00', 300, '1.10', 50],
            ['Fournitures de bureau', 'Classeur à levier A4', 'CLA-A4', '6111000000062', 'piece', '22.00', 35, '11.00', 10],
            ['Fournitures de bureau', 'Agrafeuse de bureau', 'AGR-01', '6111000000079', 'piece', '49.00', 12, '24.00', 3],
            ['Fournitures de bureau', 'Surligneurs (pochette de 4)', 'SUR-4', '6111000000086', 'pack', '28.00', 3, '13.00', 6],
            ['Encre & toner', 'Cartouche jet d\'encre noire', 'CAR-NR', '6111000000093', 'piece', '120.00', 14, '72.00', 4],
            ['Encre & toner', 'Toner laser noir', 'TON-NR', '6111000000109', 'piece', '450.00', 6, '290.00', 2],
            ['Cadeaux & déco', 'Cadre photo 13 × 18', 'CAD-1318', '6111000000116', 'piece', '59.00', 22, '27.00', 5],
            ['Cadeaux & déco', 'Mug blanc personnalisable', 'MUG-W', '6111000000123', 'piece', '39.00', 48, '14.00', 12],
            ['Informatique', 'Clé USB 32 Go', 'USB-32', '6111000000130', 'piece', '79.00', 25, '41.00', 6],
            ['Informatique', 'Souris sans fil', 'SOU-SF', '6111000000147', 'piece', '99.00', 9, '52.00', 3],
            ['Informatique', 'Câble USB-C 1 m', 'CAB-UC', '6111000000154', 'piece', '45.00', 30, '16.00', 8],
            ['Papeterie', 'Papier kraft au mètre', 'KRA-M', '6111000000161', 'm', '6.00', 80, '2.10', 15],
        ];
        $p = [];
        foreach ($items as [$cat, $name, $sku, $ean, $unit, $price, $stock, $cost, $low]) {
            $p[$sku] = \App\Models\RetailProduct::create(['retail_category_id' => $cats[$cat]->id, 'name' => $name, 'sku' => $sku, 'barcode' => $ean, 'unit' => $unit,
                'price_ttc' => $price, 'tax_rate' => '20.00', 'low_stock_threshold' => $low, 'supplier_id' => $supplier->id, 'is_demo' => true]);
            $retail->move($p[$sku], 'initial', (string) $stock, 'demo:initial:'.$sku, ['unit_cost' => $cost, 'notes' => 'Stock initial (démo)']);
        }
        $owner = \App\Models\User::role('owner')->first();
        $prev = auth()->user();
        // sales need an authenticated cashier for permissions/audit; use a throw-away in-memory staff identity if no owner yet
        $cashier = $owner ?? tap(new \App\Models\User(['name' => 'Démo']), fn ($u) => $u->forceFill(['is_staff' => true]));
        auth()->setUser($cashier);
        $yesterday = $retail->openSession('Caisse 1', '500.00', $owner);
        $yesterday->update(['opened_at' => now()->subDay()->setTime(9, 0)]);
        $sales = [
            [[['RAM-A480', 2], ['STY-BL', 10]], [['cash', '200']], null],
            [[['CAR-NR', 1], ['TON-NR', 1]], [['card_in_store', '570']], $company],
            [[['MUG-W', 3], ['CAD-1318', 1]], [['cash', '200']], null],
            [[['USB-32', 2], ['CAB-UC', 1]], [['card_in_store', '100'], ['cash', '110']], null],
        ];
        foreach ($sales as $i => [$lines, $pays, $cust]) {
            $sale = $retail->sell($yesterday, array_map(fn ($l) => ['product_id' => $p[$l[0]]->id, 'quantity' => $l[1]], $lines),
                array_map(fn ($x) => ['method' => $x[0], 'amount' => $x[1]], $pays), 'demo-sale-'.$i, $cust, null, null, true);
            $sale->update(['created_at' => now()->subDay()->setTime(10 + $i, 15)]);
        }
        $retail->cashMovement($yesterday, 'out', '50.00', 'Achat fournitures ménage (démo)', 'demo-cash-out');
        $summary = $retail->sessionSummary($yesterday);
        $retail->closeSession($yesterday, $summary['expected'], 'Clôture démo', $owner);
        $yesterday->update(['closed_at' => now()->subDay()->setTime(19, 0)]);
        auth()->setUser($prev ?? new \App\Models\User);
        if (! $prev) {
            auth()->forgetUser();
        }
        \App\Models\SupplierPayment::create(['supplier_id' => $supplier->id, 'amount' => '1500.00', 'method' => 'bank_transfer', 'reference' => 'VIR-DEMO', 'paid_at' => now()->subDays(3)->toDateString(), 'notes' => 'Règlement démo', 'idempotency_key' => 'demo-sp-1']);
    }
}
