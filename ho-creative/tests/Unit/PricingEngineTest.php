<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\Pricing\DefinitionValidator;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\Totals;
use App\Models\ShippingZone;
use Tests\TestCase;

class PricingEngineTest extends TestCase
{
    protected function engine(): PricingEngine
    {
        return app(PricingEngine::class);
    }

    public function test_business_card_tier_options_and_percent_surcharge(): void
    {
        $p = Product::where('slug', 'cartes-de-visite-standard')->first();
        // 500 rv tier = 270.00; lamination mat 500×0.05 + 20 = 45.00; express +35 % of 315.00 = 110.25
        $r = $this->engine()->forProduct($p, ['quantity' => 500, 'size' => 'std', 'options' => ['paper' => 'couche350', 'sides' => 'rv', 'lamination' => 'mat', 'turnaround' => 'express']]);
        $this->assertTrue($r->valid, implode(' ', $r->errors));
        $amounts = array_column($r->components, 'amount', 'key');
        $this->assertSame('270.00', $amounts['base']);
        $this->assertSame('25.00', $amounts['lamination.mat.per_unit']);
        $this->assertSame('20.00', $amounts['lamination.mat.fixed']);
        $this->assertSame('110.25', $amounts['turnaround.express.percent']);
        $this->assertSame('425.25', $r->total);
        $this->assertSame('0.8505', $r->unitPrice);
        $this->assertSame(1, $r->productionDays);
        $this->assertTrue($r->meta['two_sided']);
    }

    public function test_dependencies_hide_options_and_reject_invalid_combinations(): void
    {
        $p = Product::where('slug', 'cartes-de-visite-standard')->first();
        $r = $this->engine()->forProduct($p, ['quantity' => 100, 'options' => ['paper' => 'kraft', 'lamination' => 'mat']]);
        $this->assertFalse($r->valid);
        $this->assertStringContainsString('compatible', implode(' ', $r->errors));
        // spot UV hidden unless lamination mat/soft
        $r2 = $this->engine()->forProduct($p, ['quantity' => 100, 'options' => ['paper' => 'couche350', 'lamination' => 'none']]);
        $spot = collect($r2->ui['options'])->firstWhere('code', 'spot_uv');
        $this->assertFalse($spot['visible']);
        $this->assertArrayNotHasKey('spot_uv', $r2->options);
    }

    public function test_custom_quantity_uses_unit_price_of_largest_lower_tier(): void
    {
        $p = Product::where('slug', 'cartes-de-visite-standard')->first();
        $r = $this->engine()->forProduct($p, ['quantity' => 750, 'options' => ['paper' => 'couche350', 'sides' => 'recto', 'lamination' => 'none']]);
        $this->assertTrue($r->valid);
        $this->assertSame('315.00', $r->components[0]['amount']); // 210/500 × 750
        $bad = $this->engine()->forProduct($p, ['quantity' => 777]);
        $this->assertFalse($bad->valid); // step 50
    }

    public function test_area_pricing_rounding_and_minimum_charge(): void
    {
        $p = Product::where('slug', 'bache-pvc-sur-mesure')->first();
        // 123.4 cm × 45.6 cm → rounded up to 10 mm: 1240 × 460 mm → 0.5704 → 0.58 m² (step 0.01); min 0.5 m²
        $r = $this->engine()->forProduct($p, ['quantity' => 1, 'size' => 'custom', 'width' => 123.4, 'height' => 45.6, 'unit' => 'cm', 'options' => ['material' => 'frontlit', 'finishing' => 'raw', 'turnaround' => 'standard']]);
        $this->assertTrue($r->valid, implode(' ', $r->errors));
        $this->assertSame('1240.0', $r->widthMm);
        $this->assertSame('460.0', $r->heightMm);
        $this->assertSame('0.5800', $r->areaM2);
        $amounts = array_column($r->components, 'amount', 'key');
        $this->assertSame('55.10', $amounts['base']);      // 0.58 × 95
        $this->assertSame('94.90', $amounts['min_charge']); // up to 150.00
        $this->assertSame('150.00', $r->total);

        // Large job: 3 × (4 m × 2 m) = 24 m² → band ≥ 10 m² at 80/m², eyelets 12/m²
        $big = $this->engine()->forProduct($p, ['quantity' => 3, 'size' => '400x200', 'options' => ['material' => 'frontlit', 'finishing' => 'eyelets']]);
        $this->assertSame('24.0000', $big->areaM2);
        $this->assertSame('2208.00', $big->total); // 1920 + 288
        $tooSmall = $this->engine()->forProduct($p, ['quantity' => 1, 'size' => 'custom', 'width' => 10, 'height' => 10, 'unit' => 'cm']);
        $this->assertFalse($tooSmall->valid);
    }

    public function test_totals_coupon_shipping_and_tax_are_decimal_exact(): void
    {
        $zone = ShippingZone::create(['name' => ['fr' => 'Z'], 'price' => '30.00', 'free_over' => '1000.00', 'delivery_days' => 1]);
        $coupon = \App\Models\Coupon::create(['code' => 'TEN', 'type' => 'percent', 'value' => '10', 'is_active' => true]);
        $t = app(Totals::class)->compute([['total' => '0.10', 'product_id' => 1], ['total' => '0.20', 'product_id' => 2], ['total' => '99.99', 'product_id' => 3]], $coupon, $zone, 'delivery');
        $this->assertSame('100.29', $t['subtotal']);
        $this->assertSame('10.03', $t['discount_total']); // 10.029 → 10.03
        $this->assertSame('30.00', $t['shipping_total']);
        $this->assertSame('24.05', $t['tax_total']);      // 20 % × 120.26 = 24.052
        $this->assertSame('144.31', $t['total']);
    }

    public function test_validator_rejects_forward_references_and_bad_modifiers(): void
    {
        $def = ['mode' => 'tiers', 'quantity' => ['presets' => [10]], 'tiers' => [['qty' => 10, 'price' => '1']], 'options' => [
            ['code' => 'a', 'label' => ['fr' => 'A'], 'visible_if' => ['b' => ['x']], 'choices' => [['code' => 'x', 'label' => ['fr' => 'x'], 'modifiers' => [['type' => 'bogus', 'amount' => '1']]]]],
            ['code' => 'b', 'label' => ['fr' => 'B'], 'choices' => [['code' => 'x', 'label' => ['fr' => 'x']]]],
        ]];
        $errors = implode(' | ', (new DefinitionValidator)->errors($def));
        $this->assertStringContainsString('AVANT', $errors);
        $this->assertStringContainsString('modificateur invalide', $errors);
    }

    public function test_seeded_from_prices_are_real_minimums(): void
    {
        foreach (Product::where('sale_mode', 'instant')->get() as $p) {
            $this->assertNotNull($p->from_price, $p->slug);
            $def = $p->publishedRuleSet->definition;
            $r = $this->engine()->forProduct($p, []);
            if ($r->valid) {
                $this->assertGreaterThanOrEqual((float) $p->from_price, (float) $r->total, $p->slug);
            }
        }
    }
}
