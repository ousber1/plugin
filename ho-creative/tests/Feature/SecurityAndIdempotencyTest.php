<?php

namespace Tests\Feature;

use App\Models\ArtworkFile;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Material;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\ArtworkService;
use App\Services\DocumentService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\Payments\SandboxGateway;
use App\Services\Pricing\PricingEngine;
use App\Services\QuoteService;
use App\Services\StockService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAndIdempotencyTest extends TestCase
{
    protected function orderFor(Customer $c, ?int $userId = null): Order
    {
        $p = Product::where('slug', 'flyers-publicitaires')->first();
        $r = app(PricingEngine::class)->forProduct($p, ['quantity' => 500, 'size' => 'a6']);

        return app(OrderService::class)->create($c, [OrderService::lineFromPrice($p, $r)], ['user_id' => $userId]);
    }

    public function test_flow6_customer_cannot_access_another_customers_data_even_with_direct_urls(): void
    {
        Storage::fake('private');
        $alice = $this->customerUser('Alice');
        $bob = $this->customerUser('Bob');
        $order = $this->orderFor($alice->customer, $alice->id);
        $file = app(ArtworkService::class)->store($this->png(), $order->items->first());
        $invoice = app(DocumentService::class)->invoice($order);
        $quote = app(QuoteService::class)->create($alice->customer, app(QuoteService::class)->priceLines([['description' => 'X', 'quantity' => 1, 'unit_price' => '10']]), []);
        app(QuoteService::class)->send($quote);

        $this->actingAs($alice)->get(route('account.orders.show', $order))->assertOk();
        $this->actingAs($alice)->get(route('files.artwork', $file))->assertOk();
        $this->actingAs($alice)->get(route('files.document', $invoice))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($bob)->get(route('account.orders.show', $order))->assertNotFound();
        $this->actingAs($bob)->get(route('files.artwork', $file))->assertNotFound();
        $this->actingAs($bob)->get(route('files.document', $invoice))->assertNotFound();
        $this->actingAs($bob)->post(route('account.orders.upload', [$order, $order->items->first()->id]), ['file' => $this->png(), 'side' => 'front'])->assertNotFound();
        $this->actingAs($bob)->get(route('account.invoices'))->assertOk()->assertDontSee($invoice->number);
        $this->actingAs($bob)->get(route('account.quotes'))->assertOk()->assertDontSee($quote->number);
        $this->actingAs($bob)->post(route('account.orders.reorder', $order))->assertNotFound();
        $this->post('/deconnexion');
        auth()->logout();
        // anonymous visitor with the raw URLs
        $this->get(route('files.artwork', $file))->assertNotFound();
        $this->get(route('files.document', $invoice))->assertNotFound();
        $this->get(route('guest.order', str_repeat('x', 48)))->assertNotFound();
        $this->get(route('quote.public', str_repeat('y', 48)))->assertNotFound();
        $this->assertFalse(is_file(public_path('storage/'.$file->path)));
    }

    public function test_flow7_replayed_payment_callback_is_processed_once(): void
    {
        $order = $this->orderFor(Customer::create(['name' => 'Pay']));
        $p = ['event_id' => 'EVT-1', 'oid' => $order->number, 'amount' => $order->balance(), 'status' => 'paid'];
        $p['signature'] = SandboxGateway::sign($p);
        $this->post(route('payment.callback', 'sandbox'), $p)->assertOk()->assertSee('OK');
        $this->post(route('payment.callback', 'sandbox'), $p)->assertOk();
        $this->post(route('payment.callback', 'sandbox'), $p)->assertOk();
        $this->assertSame(1, Payment::count());
        $this->assertSame(1, PaymentEvent::count());
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('confirmed', $order->fresh()->status);

        $forged = $p;
        $forged['event_id'] = 'EVT-2';
        $this->post(route('payment.callback', 'sandbox'), $forged)->assertStatus(400); // bad signature
        $this->assertSame(1, Payment::count());
    }

    public function test_flow7_replayed_staff_actions_do_not_duplicate_documents_stock_or_payments(): void
    {
        $owner = $this->staff('owner');
        $order = $this->orderFor(Customer::create(['name' => 'Idem']));
        $key = '11111111-1111-4111-8111-111111111111';
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($owner)->post(route('admin.orders.payment', $order), ['type' => 'payment', 'method' => 'cash', 'amount' => '100', 'key' => $key]);
            $this->actingAs($owner)->post(route('admin.orders.document', $order), ['type' => 'invoice']);
        }
        $this->assertSame(1, Payment::count());
        $this->assertSame('100.00', $order->fresh()->amount_paid);
        $this->assertSame(1, Document::where('type', 'invoice')->count());

        $mat = Material::create(['sku' => 'X', 'name' => 'X', 'unit' => 'sheet', 'unit_cost' => '1']);
        $svc = app(StockService::class);
        $svc->move($mat, 'receipt', '100', 'rcpt-1');
        $job = $order->items->first()->job;
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($owner)->post(route('admin.production.material', $job), ['material_id' => $mat->id, 'action' => 'consume', 'quantity' => '10', 'key' => $key]);
            $svc->move($mat, 'receipt', '100', 'rcpt-1');
        }
        $this->assertSame('90.000', $mat->fresh()->stock_qty); // 100 received once, 10 consumed once
        $this->assertSame(1, StockMovement::where('type', 'consumption')->count());
        $this->assertSame(1, $job->costs()->count());

        // production transition replay is a no-op
        $before = $job->events()->count();
        app(\App\Services\ProductionService::class)->transition($job, $job->stage);
        $this->assertSame($before, $job->events()->count());

        // refund cannot exceed what was collected
        $this->actingAs($owner)->post(route('admin.orders.payment', $order), ['type' => 'refund', 'method' => 'cash', 'amount' => '150', 'notes' => 'x', 'key' => '22222222-2222-4222-8222-222222222222'])->assertSessionHasErrors('amount');
    }

    public function test_flow8_price_changes_preserve_history_and_settings_are_editable(): void
    {
        $owner = $this->staff('owner');
        $order = $this->orderFor(Customer::create(['name' => 'Hist']));
        $invoice = app(DocumentService::class)->invoice($order);
        $snapshotBefore = $invoice->snapshot;
        $p = Product::where('slug', 'flyers-publicitaires')->first();

        $this->actingAs($owner)->get(route('admin.pricing.edit', $p))->assertOk();
        $draft = $p->ruleSets()->where('status', 'draft')->first();
        $def = $draft->definition;
        foreach ($def['tiers'] as &$t) {
            $t['price'] = number_format((float) $t['price'] * 2, 2, '.', '');
        }
        unset($t);
        // preview before publishing
        $pv = $this->actingAs($owner)->postJson(route('admin.pricing.preview', $p), ['definition' => $def, 'input' => ['quantity' => 500, 'size' => 'a6']])->json();
        $this->assertSame('341.00', $pv['total']); // 110 × 1.55 × 2
        $this->assertSame('170,50 DH', str_replace("\u{00A0}", ' ', $pv['published_total']));
        $this->actingAs($owner)->post(route('admin.pricing.publish', $p), ['definition' => json_encode($def), 'notes' => 'x2'])->assertRedirect();
        $this->assertSame(2, $p->fresh()->publishedRuleSet->version);
        $this->assertSame('archived', $p->ruleSets()->where('version', 1)->value('status'));

        $order->refresh();
        $this->assertSame('170.50', $order->subtotal);
        $this->assertSame(1, $order->items->first()->rule_version);
        $this->assertEquals($snapshotBefore, $invoice->fresh()->snapshot);
        $this->assertSame('341.00', app(PricingEngine::class)->forProduct($p->fresh(), ['quantity' => 500, 'size' => 'a6'])->total);

        // branding / company / delivery settings without code changes
        $this->actingAs($owner)->post(route('admin.settings.update'), ['brand__name' => 'HO CREATIVE Maroc', 'company__legal_name' => 'HO CREATIVE SARL', 'tax__enabled' => '1', 'tax__rate' => '20',
            'brand__primary_color' => '#112233', 'hours__cutoff' => '12:00', 'hours__working_days' => [1, 2, 3, 4, 5]])->assertRedirect();
        $this->get('/')->assertSee('HO CREATIVE Maroc')->assertSee('--ink:#112233', false);
        $this->assertSame([1, 2, 3, 4, 5], setting('hours.working_days'));
        $zone = \App\Models\ShippingZone::first();
        $this->actingAs($owner)->put(route('admin.r.update', ['zones', $zone->id]), ['name' => ['fr' => 'Zone A', 'ar' => ''], 'price' => '25', 'delivery_days' => 1, 'is_active' => '1', 'sort' => 1])->assertRedirect();
        $this->assertSame('25.00', $zone->fresh()->price);
        // old invoice still shows the original company name
        $this->assertNotSame('HO CREATIVE SARL', $invoice->fresh()->snapshot['company']['name']);
    }

    public function test_csrf_is_enforced_on_web_forms(): void
    {
        $this->app['env'] = 'production';
        $res = $this->withMiddleware()->call('POST', '/devis', ['name' => 'x']);
        $this->assertContains($res->getStatusCode(), [419, 302, 422]);
    }

    public function test_login_is_rate_limited(): void
    {
        $u = $this->customerUser('Rate');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/connexion', ['email' => $u->email, 'password' => 'wrong']);
        }
        $this->post('/connexion', ['email' => $u->email, 'password' => 'wrong'])->assertStatus(429);
    }
}
