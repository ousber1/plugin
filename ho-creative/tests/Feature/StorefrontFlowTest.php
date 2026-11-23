<?php

namespace Tests\Feature;

use App\Models\ArtworkFile;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Flow 1 & 2: configure, price, upload, order (guest and account). */
class StorefrontFlowTest extends TestCase
{
    public function test_flow1_customer_configures_business_cards_uploads_artwork_and_orders(): void
    {
        Storage::fake('private');
        $p = Product::where('slug', 'cartes-de-visite-standard')->first();
        $sel = ['quantity' => 500, 'size' => 'std', 'options' => ['paper' => 'couche350', 'sides' => 'rv', 'lamination' => 'mat', 'corners' => 'square', 'turnaround' => 'standard']];

        // live price endpoint
        $this->postJson(route('price', $p), $sel)->assertOk()->assertJson(['valid' => true, 'total' => '315.00', 'two_sided' => true]);

        // add to cart with front + back files; a forged "total" field must be ignored
        $res = $this->post(route('cart.add'), $sel + ['product_id' => $p->id, 'artwork_mode' => 'upload', 'total' => '1.00',
            'artwork_front' => $this->png(1075, 720, 'recto.png'), 'artwork_back' => $this->png(1075, 720, 'verso.png')]);
        $res->assertRedirect(route('cart'));
        $cart = $this->cartCookie($res);
        $this->assertNotNull($cart);
        $this->assertSame(2, ArtworkFile::count());
        $front = ArtworkFile::where('side', 'front')->first();
        $res = collect($front->checks['items'])->firstWhere('key', 'resolution');
        $this->assertSame('ok', $res['status']); // 1075 px over 91 mm ≈ 300 dpi
        $this->assertSame('manual', collect($front->checks['items'])->firstWhere('key', 'color')['status']); // RGB flagged, never "print-ready"

        $this->withCookie('hoc_cart', $cart)->get(route('cart'))->assertOk()->assertSee('315,00', false);

        $zone = ShippingZone::where('is_default', false)->orderBy('sort')->first(); // Casablanca 30 DH
        $key = (string) Str::uuid();
        $payload = ['checkout_key' => $key, 'name' => 'Amina Test', 'email' => 'amina@test.local', 'phone' => '0612345678', 'shipping_method' => 'delivery',
            'zone_id' => $zone->id, 'line1' => '10 rue Test', 'city' => 'Casablanca', 'payment_method' => 'bank_transfer', 'terms' => '1', 'subtotal' => '0.01'];
        $this->withCookie('hoc_cart', $cart)->post(route('checkout.place'), $payload)->assertRedirect();
        // double submit with the same key → same order, no duplicate
        $this->withCookie('hoc_cart', $cart)->post(route('checkout.place'), $payload)->assertRedirect();
        $this->assertSame(1, Order::count());

        $order = Order::first();
        $this->assertSame('315.00', $order->subtotal);
        $this->assertSame('30.00', $order->shipping_total);
        $this->assertSame('69.00', $order->tax_total); // 20 % × 345
        $this->assertSame('414.00', $order->total);
        $this->assertSame('unpaid', $order->payment_status);
        $item = $order->items->first();
        $this->assertSame(1, $item->rule_version);
        $this->assertSame('Couché mat 350 g', $item->options_snapshot['options']['paper']['value_label']);
        $this->assertSame(2, $item->artworkFiles()->count(), 'cart files moved to the order line');
        $this->assertSame('prepress_review', $item->job->stage);
        $this->assertNotNull($order->guest_token);
        $this->assertTrue($order->estimated_delivery_at->gte($order->estimated_ready_at));

        $this->withSession(['guest_orders' => [$order->id]])->get(route('checkout.thanks', $order->number))->assertOk()->assertSee($order->number);
        $this->get(route('guest.order', $order->guest_token))->assertOk()->assertSee('recto.png');
    }

    public function test_flow2_large_format_custom_dimensions_area_and_minimum_charge(): void
    {
        $p = Product::where('slug', 'bache-pvc-sur-mesure')->first();
        $r = $this->postJson(route('price', $p), ['quantity' => 1, 'size' => 'custom', 'width' => '123.4', 'height' => '45.6', 'unit' => 'cm',
            'options' => ['material' => 'frontlit', 'finishing' => 'raw']])->assertOk()->json();
        $this->assertTrue($r['valid']);
        $this->assertSame('0.5800', $r['area_m2']);
        $this->assertSame('150.00', $r['total']);
        $this->assertContains('min_charge', array_column($r['components'], 'key'));

        $r2 = $this->postJson(route('price', $p), ['quantity' => 2, 'size' => 'custom', 'width' => 500, 'height' => 300, 'unit' => 'cm',
            'options' => ['material' => 'mesh', 'finishing' => 'eyelets', 'turnaround' => 'express']])->assertOk()->json();
        // 2 × 15 m² = 30 m², mesh band ≥10 → 105/m² = 3150 ; eyelets 12 × 30 = 360 ; express +40 % of 3510 = 1404
        $this->assertSame('30.0000', $r2['area_m2']);
        $this->assertSame('4914.00', $r2['total']);
        $this->assertFalse($this->postJson(route('price', $p), ['quantity' => 1, 'size' => 'custom', 'width' => 600, 'height' => 100, 'unit' => 'cm'])->json('valid'));
    }

    public function test_registered_customer_order_and_reorder_uses_current_prices(): void
    {
        $user = $this->customerUser('Karim');
        $p = Product::where('slug', 'flyers-publicitaires')->first();
        $res = $this->actingAs($user)->post(route('cart.add'), ['product_id' => $p->id, 'quantity' => 1000, 'size' => 'a5', 'options' => ['paper' => 'c135', 'sides' => 'recto'], 'artwork_mode' => 'later']);
        $res->assertRedirect(route('cart'));
        $this->actingAs($user)->post(route('checkout.place'), ['checkout_key' => (string) Str::uuid(), 'name' => 'Karim', 'email' => $user->email, 'phone' => '06',
            'shipping_method' => 'pickup', 'payment_method' => 'in_store', 'terms' => '1'])->assertRedirect();
        $order = Order::first();
        $this->assertSame('384.00', $order->subtotal); // 160 × 2.4
        $this->assertSame('artwork_pending', $order->items->first()->job->stage);

        // price increase on a new published version
        $rs = $p->publishedRuleSet;
        $def = $rs->definition;
        foreach ($def['tiers'] as &$t) {
            $t['price'] = number_format((float) $t['price'] * 1.1, 2, '.', '');
        }
        $rs->update(['status' => 'archived']);
        $p->ruleSets()->create(['version' => 2, 'status' => 'published', 'definition' => $def]);

        $this->actingAs($user)->post(route('account.orders.reorder', $order))->assertRedirect(route('cart'));
        $this->actingAs($user)->get(route('cart'))->assertSee('422,40', false); // new price
        $this->assertSame('384.00', $order->fresh()->subtotal); // history untouched
    }

    public function test_quote_only_products_cannot_be_bought_and_unpublished_are_hidden(): void
    {
        $quoteOnly = Product::where('sale_mode', 'quote')->first();
        $this->post(route('cart.add'), ['product_id' => $quoteOnly->id, 'quantity' => 1])->assertNotFound();
        $p = Product::where('slug', 'mugs-personnalises')->first();
        $p->update(['sale_mode' => 'unpublished']);
        $this->get(route('product', $p->slug))->assertNotFound();
        $this->postJson(route('price', $p), [])->assertNotFound();
        $this->get(route('sitemap'))->assertOk()->assertDontSee('mugs-personnalises')->assertSee('cartes-de-visite-standard');
    }

    public function test_arabic_rtl_and_localized_seo(): void
    {
        $this->get('/?lang=ar')->assertOk()->assertSee('dir="rtl"', false)->assertSee('lang="ar"', false);
        $this->get(route('product', 'cartes-de-visite-standard'))->assertOk()->assertSee('بطاقات الأعمال')->assertSee('<link rel="canonical" href="'.route('product', 'cartes-de-visite-standard').'">', false)
            ->assertSee('hreflang="fr"', false);
        $this->get('/?lang=fr')->assertSee('dir="ltr"', false);
    }
}
