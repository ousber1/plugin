<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Document;
use App\Models\Material;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\StockMovement;
use App\Services\DocumentService;
use App\Services\OrderService;
use App\Services\Pricing\PricingEngine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackOfficeFlowTest extends TestCase
{
    protected function webOrder(string $slug = 'cartes-de-visite-standard', array $sel = ['quantity' => 250, 'options' => ['paper' => 'couche350', 'sides' => 'recto', 'lamination' => 'none']]): Order
    {
        $p = Product::where('slug', $slug)->first();
        $c = Customer::create(['name' => 'Client '.uniqid(), 'email' => uniqid().'@t.local']);

        return app(OrderService::class)->create($c, [OrderService::lineFromPrice($p, app(PricingEngine::class)->forProduct($p, $sel))], ['channel' => 'web']);
    }

    public function test_flow3_whatsapp_quote_acceptance_conversion_and_deposit(): void
    {
        $sales = $this->staff('sales');
        $customer = Customer::create(['name' => 'Hôtel Atlas', 'type' => 'company', 'company_name' => 'Hôtel Atlas SARL', 'phone' => '0661000000', 'email' => 'atlas@t.local']);
        $poster = Product::where('slug', 'affiches-grand-format')->first();
        $this->actingAs($sales)->post(route('admin.quotes.store'), [
            'customer_id' => $customer->id, 'channel' => 'whatsapp', 'shipping_method' => 'pickup', 'locale' => 'fr',
            'manual_discount' => '50', 'discount_reason' => 'Client fidèle',
            'lines' => [
                ['product_id' => $poster->id, 'selection' => ['quantity' => 10, 'size' => 'a1', 'options' => ['paper' => 'satin']]],
                ['description' => 'Pose murale', 'quantity' => 1, 'unit_price' => '300'],
            ],
        ])->assertRedirect();
        $quote = Quote::first();
        $rev = $quote->revisionNo(1);
        // 10 × A1 (0.5 m² each after step) = 5.0 m² → band ≥5 at 115 = 575 ; + 300 ; − 50 = 825 ; TVA 165 ; TTC 990
        $this->assertSame('875.00', $rev->subtotal);
        $this->assertSame('990.00', $rev->total);

        $this->actingAs($sales)->post(route('admin.quotes.send', $quote))->assertRedirect();
        $this->get(route('quote.public', $quote->token))->assertOk()->assertSee($quote->number);
        $this->actingAs($sales)->post(route('admin.quotes.respond', $quote), ['decision' => 'accept', 'name' => 'M. Atlas', 'note' => 'OK WhatsApp 10:42'])->assertRedirect();
        $this->assertSame('accepted', $quote->fresh()->status);

        $key = (string) Str::uuid();
        $this->actingAs($sales)->post(route('admin.quotes.convert', $quote), ['deposit' => '500', 'deposit_method' => 'cash', 'key' => $key])->assertRedirect();
        $this->actingAs($sales)->post(route('admin.quotes.convert', $quote), ['deposit' => '500', 'deposit_method' => 'cash', 'key' => $key]); // replay
        $this->assertSame(1, Order::count());
        $order = Order::first();
        $this->assertSame($quote->id, $order->quote_id);
        $this->assertSame('990.00', $order->total);
        $this->assertSame('whatsapp', $order->channel);
        $this->assertSame('500.00', $order->amount_paid);
        $this->assertSame('partial', $order->payment_status);
        $this->assertSame('490.00', $order->balance());
        $this->assertSame(1, Payment::count());
        $this->assertSame('converted', $quote->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quote.manual_discount', 'reason' => 'Client fidèle']);
    }

    public function test_walk_in_staff_order_with_custom_line_and_authorized_discount(): void
    {
        $sales = $this->staff('sales');
        $c = Customer::create(['name' => 'Passant']);
        $card = Product::where('slug', 'cartes-de-visite-standard')->first();
        $this->actingAs($sales)->post(route('admin.orders.store'), ['customer_id' => $c->id, 'channel' => 'walk_in', 'shipping_method' => 'pickup', 'checkout_key' => (string) Str::uuid(),
            'lines' => [['product_id' => $card->id, 'selection' => ['quantity' => 100], 'line_discount' => '10', 'line_discount_reason' => 'Geste commercial'],
                ['description' => 'Plastification documents', 'quantity' => 4, 'unit_price' => '5']],
            'deposit' => '50', 'deposit_method' => 'cash'])->assertRedirect();
        $o = Order::first();
        $this->assertSame('walk_in', $o->channel);
        $this->assertSame('100.00', $o->subtotal); // (90 − 10) + 20
        $this->assertSame('50.00', $o->amount_paid);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.line_discount', 'reason' => 'Geste commercial']);

        $designer = $this->staff('designer'); // has no orders.discount / orders.manage
        $this->actingAs($designer)->post(route('admin.orders.store'), ['customer_id' => $c->id, 'channel' => 'walk_in', 'shipping_method' => 'pickup', 'checkout_key' => (string) Str::uuid(),
            'lines' => [['description' => 'x', 'quantity' => 1, 'unit_price' => '1']]])->assertForbidden();
    }

    public function test_flow4_proof_blocks_production_until_current_version_is_approved(): void
    {
        Storage::fake('private');
        $owner = $this->staff('owner');
        $order = $this->webOrder();
        app(OrderService::class)->confirm($order);
        $item = $order->items->first();
        $job = $item->job;

        $this->actingAs($owner)->post(route('admin.items.proof', $item), ['file' => UploadedFile::fake()->create('bat.pdf', 100, 'application/pdf')])->assertRedirect();
        $this->assertSame('proof_pending', $job->fresh()->stage);
        $this->actingAs($owner)->postJson(route('admin.production.transition', $job), ['stage' => 'printing'])->assertStatus(422);

        // customer approves v1 through the guest link
        $p1 = $item->proofs()->first();
        $this->get(route('guest.order', $order->guest_token))->assertOk();
        $this->post(route('guest.proof', [$order->guest_token, $p1]), ['decision' => 'approved', 'confirm' => '1'])->assertRedirect();
        $this->assertSame('approved', $item->fresh()->proof_status);
        $this->assertSame('approved', $job->fresh()->stage);

        // staff replaces the proof → approval resets, production blocked again
        $this->actingAs($owner)->post(route('admin.items.proof', $item), ['file' => UploadedFile::fake()->create('bat-v2.pdf', 100, 'application/pdf')]);
        $this->assertSame('superseded', $p1->fresh()->status);
        $this->assertSame('pending', $item->fresh()->proof_status);
        $this->assertSame('proof_pending', $job->fresh()->stage);
        $this->actingAs($owner)->postJson(route('admin.production.transition', $job), ['stage' => 'printing'])->assertStatus(422);
        // approving the stale v1 is refused
        $this->post(route('guest.proof', [$order->guest_token, $p1]), ['decision' => 'approved', 'confirm' => '1'])->assertSessionHasErrors('proof');
        $p2 = $item->proofs()->where('version', 2)->first();
        $this->post(route('guest.proof', [$order->guest_token, $p2]), ['decision' => 'approved', 'confirm' => '1']);
        $this->actingAs($owner)->postJson(route('admin.production.transition', $job), ['stage' => 'printing'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'proof.approved']);
        $this->assertSame(2, $p2->fresh()->version);
        $this->assertNotNull($p2->fresh()->responded_at);
    }

    public function test_flow5_production_materials_invoice_and_dispatch(): void
    {
        Storage::fake('private');
        $owner = $this->staff('owner');
        $order = $this->webOrder('bache-pvc-sur-mesure', ['quantity' => 1, 'size' => '300x100', 'options' => ['material' => 'frontlit', 'finishing' => 'eyelets']]);
        $item = $order->items->first();
        $item->update(['requires_proof' => false]);
        $this->actingAs($owner)->post(route('admin.items.artwork', $item), ['file' => $this->png(), 'side' => 'front']);
        $this->actingAs($owner)->post(route('admin.orders.confirm', $order));
        $mat = Material::create(['sku' => 'BAN', 'name' => 'Bâche 510', 'unit' => 'm2', 'unit_cost' => '20', 'low_stock_threshold' => '5']);
        $this->actingAs($owner)->post(route('admin.materials.adjust', $mat), ['mode' => 'receipt', 'quantity' => '50', 'unit_cost' => '20', 'reason' => 'Stock initial', 'key' => (string) Str::uuid()]);
        $job = $item->job;
        foreach (['approved', 'printing'] as $s) {
            $this->actingAs($owner)->postJson(route('admin.production.transition', $job), ['stage' => $s])->assertOk();
        }
        $this->actingAs($owner)->post(route('admin.production.material', $job), ['material_id' => $mat->id, 'action' => 'reserve', 'quantity' => '4', 'key' => (string) Str::uuid()]);
        $this->assertSame('4.000', $mat->fresh()->reserved_qty);
        $this->actingAs($owner)->post(route('admin.production.material', $job), ['material_id' => $mat->id, 'action' => 'consume', 'quantity' => '3.2', 'key' => (string) Str::uuid()]);
        $this->actingAs($owner)->post(route('admin.production.material', $job), ['material_id' => $mat->id, 'action' => 'waste', 'quantity' => '0.3', 'key' => (string) Str::uuid()]);
        $m = $mat->fresh();
        $this->assertSame('46.500', $m->stock_qty);
        $this->assertSame('0.500', $m->reserved_qty); // consumption and waste both draw from the reservation first
        $this->assertEquals(70.0, (float) $job->fresh()->costs()->where('is_estimate', false)->sum('total')); // 3.5 m² × 20

        foreach (['finishing', 'quality_control', 'packing'] as $s) {
            $this->actingAs($owner)->postJson(route('admin.production.transition', $job), ['stage' => $s])->assertOk();
        }
        $this->actingAs($owner)->post(route('admin.orders.document', $order), ['type' => 'invoice'])->assertRedirect();
        $this->actingAs($owner)->post(route('admin.orders.shipment', $order), ['method' => 'pickup', 'status' => 'picked_up', 'recipient_name' => 'Client'])->assertRedirect();
        $order->refresh();
        $this->assertSame('delivered', $job->fresh()->stage);
        $this->assertSame('completed', $order->status);
        $this->assertSame('picked_up', $order->fulfillment_status);
        $inv = Document::where('type', 'invoice')->first();
        $this->assertStringStartsWith('FA-'.date('Y').'-', $inv->number);
        $this->assertSame($order->total, $inv->total);
        $pdf = app(DocumentService::class)->renderPdf($inv);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->actingAs($owner)->get(route('admin.production.sheet', $job))->assertOk()->assertSee('3000 × 1000', false)->assertSee('Bâche frontlit 510 g');
    }

    public function test_cancellation_releases_unused_reservations(): void
    {
        $order = $this->webOrder();
        $job = $order->items->first()->job;
        $mat = Material::create(['sku' => 'P', 'name' => 'Papier', 'unit' => 'sheet', 'unit_cost' => '1']);
        $svc = app(\App\Services\StockService::class);
        $svc->move($mat, 'receipt', '100', 'r1');
        $svc->reserve($mat, '30', $job, 'res1');
        app(OrderService::class)->cancel($order, 'Client annule');
        $this->assertSame('0.000', $mat->fresh()->reserved_qty);
        $this->assertSame('100.000', $mat->fresh()->stock_qty);
        $this->assertSame('cancelled', $job->fresh()->stage);
    }

    public function test_permissions_restrict_finance_and_settings(): void
    {
        $prod = $this->staff('production');
        $this->actingAs($prod)->get(route('admin.production.board'))->assertOk();
        $this->actingAs($prod)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($prod)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($prod)->get(route('admin.payments.index'))->assertForbidden();
        $this->actingAs($prod)->get(route('admin.dashboard'))->assertOk()->assertDontSee('CA HT');
        $acc = $this->staff('accounting');
        $this->actingAs($acc)->get(route('admin.reports.index', ['report' => 'margins']))->assertOk();
        $this->actingAs($acc)->get(route('admin.staff.index'))->assertForbidden();
        $customer = $this->customerUser();
        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();

        $sales = $this->staff('sales');
        $owner = $this->staff('owner');
        $this->actingAs($owner)->put(route('admin.staff.update', $sales), ['name' => $sales->name, 'email' => $sales->email, 'roles' => ['sales', 'dispatch'], 'is_active' => '1'])->assertRedirect();
        $this->assertTrue($sales->fresh()->hasRole('dispatch'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.roles_changed']);
    }
}
