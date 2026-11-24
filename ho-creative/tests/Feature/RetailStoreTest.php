<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Document;
use App\Models\PurchaseOrder;
use App\Models\RetailProduct;
use App\Models\RetailSale;
use App\Models\RetailStockMovement;
use App\Models\Supplier;
use App\Services\DocumentService;
use App\Services\RetailService;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Physical store: till sessions, sales, stock, refunds, invoices, suppliers, permissions. */
class RetailStoreTest extends TestCase
{
    protected function product(array $a = []): RetailProduct
    {
        $p = RetailProduct::create($a + ['name' => 'Ramette A4', 'sku' => 'RAM-'.Str::random(4), 'barcode' => (string) random_int(1000000000, 9999999999), 'price_ttc' => '45.00', 'tax_rate' => '20.00', 'low_stock_threshold' => 2]);
        app(RetailService::class)->move($p, 'initial', '10', 'init:'.$p->id, ['unit_cost' => '30.00']);

        return $p->fresh();
    }

    protected function openTill($user, string $float = '200'): CashSession
    {
        $this->actingAs($user)->post(route('admin.pos.open'), ['register' => 'Caisse 1', 'opening_float' => $float])->assertRedirect(route('admin.pos'));

        return CashSession::where('status', 'open')->firstOrFail();
    }

    public function test_cashier_full_day_sale_change_stock_and_closing(): void
    {
        $cashier = $this->staff('cashier');
        $paper = $this->product();
        $pen = $this->product(['name' => 'Stylo', 'price_ttc' => '3.00', 'tax_rate' => '20.00']);
        $session = $this->openTill($cashier);
        $this->actingAs($cashier)->get(route('admin.pos'))->assertOk()->assertSee('Ramette A4');

        $key = (string) Str::uuid();
        $payload = ['key' => $key, 'register' => 'Caisse 1',
            'lines' => [['product_id' => $paper->id, 'quantity' => 2], ['product_id' => $pen->id, 'quantity' => 5]],
            'payments' => [['method' => 'cash', 'amount' => '200']],
            'total' => '1.00'];                                                   // forged total is ignored
        $r = $this->actingAs($cashier)->postJson(route('admin.pos.sell'), $payload)->assertOk()->json();
        $this->assertStringContainsString('105,00', $r['total']);          // 2 × 45 + 5 × 3
        $this->assertStringContainsString('95,00', $r['change']);
        $this->actingAs($cashier)->postJson(route('admin.pos.sell'), $payload)->assertOk(); // replay
        $this->assertSame(1, RetailSale::count());
        $sale = RetailSale::first();
        $this->assertSame('105.00', $sale->total_ttc);
        $this->assertSame('95.00', $sale->change_given);
        $this->assertSame('17.50', $sale->tax_total);                    // 90 → 15.00 ; 15 → 2.50
        $this->assertSame('87.50', $sale->subtotal_ht);
        $this->assertSame('8.000', $paper->fresh()->stock_qty);
        $this->assertSame('105.00', $sale->payments->first()->amount);   // stored net of change
        $this->assertSame('210.00', $sale->cost_total);                  // both test items were stocked at 30.00 cost

        // ticket and close: expected = 200 float + 105 cash
        $this->actingAs($cashier)->get(route('admin.retail.ticket', $sale))->assertOk()->assertSee($sale->number)->assertSee('105,00');
        $this->actingAs($cashier)->post(route('admin.retail.sessions.close', $session), ['counted_cash' => '300'])->assertRedirect();
        $s = $session->fresh();
        $this->assertSame('closed', $s->status);
        $this->assertSame('305.00', $s->expected_cash);
        $this->assertSame('-5.00', $s->difference);
        $this->actingAs($cashier)->postJson(route('admin.pos.sell'), ['key' => (string) Str::uuid(), 'register' => 'Caisse 1', 'lines' => [['product_id' => $paper->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => '45']]])->assertStatus(422);

        // cashier cannot see finance, reports or settings
        $this->actingAs($cashier)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($cashier)->get(route('admin.dashboard'))->assertRedirect(route('admin.pos'));
    }

    public function test_sale_rules_stock_payment_and_discount_permissions(): void
    {
        $cashier = $this->staff('cashier');
        $p = $this->product();
        $session = $this->openTill($cashier);
        $sell = fn ($lines, $pays, $extra = []) => $this->actingAs($cashier)->postJson(route('admin.pos.sell'), ['key' => (string) Str::uuid(), 'register' => 'Caisse 1', 'lines' => $lines, 'payments' => $pays] + $extra);

        $sell([['product_id' => $p->id, 'quantity' => 11]], [['method' => 'cash', 'amount' => '1000']])->assertStatus(422)->assertJsonFragment(['Stock insuffisant pour Ramette A4 (disponible : 10)']);
        $sell([['product_id' => $p->id, 'quantity' => 1]], [['method' => 'cash', 'amount' => '40']])->assertStatus(422);       // underpaid
        $sell([['product_id' => $p->id, 'quantity' => 1]], [['method' => 'card_in_store', 'amount' => '50']])->assertStatus(422); // card overpays
        $sell([['product_id' => $p->id, 'quantity' => 1.5]], [['method' => 'cash', 'amount' => '100']])->assertStatus(422);     // pieces are whole
        $sell([['product_id' => $p->id, 'quantity' => 1, 'discount' => '5']], [['method' => 'cash', 'amount' => '45']], ['discount_reason' => 'x'])->assertStatus(422); // cashier: no discount right
        // split payment card + cash
        $sell([['product_id' => $p->id, 'quantity' => 2]], [['method' => 'card_in_store', 'amount' => '50'], ['method' => 'cash', 'amount' => '50']])->assertOk();
        $sale = RetailSale::latest('id')->first();
        $this->assertSame('10.00', $sale->change_given);   // 2 × 45 = 90 ; paid 50 card + 50 cash
        $this->assertEqualsCanonicalizing(['50.00', '40.00'], $sale->payments->pluck('amount')->all());

        $sales = $this->staff('sales'); // has retail.discount
        $this->actingAs($sales)->postJson(route('admin.pos.sell'), ['key' => (string) Str::uuid(), 'register' => 'Caisse 1', 'lines' => [['product_id' => $p->id, 'quantity' => 1, 'discount' => '5']], 'payments' => [['method' => 'cash', 'amount' => '40']]])->assertStatus(422); // reason required
        $this->actingAs($sales)->postJson(route('admin.pos.sell'), ['key' => (string) Str::uuid(), 'register' => 'Caisse 1', 'discount_reason' => 'Client fidèle', 'lines' => [['product_id' => $p->id, 'quantity' => 1, 'discount' => '5']], 'payments' => [['method' => 'cash', 'amount' => '40']]])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'retail.discount', 'reason' => 'Client fidèle']);
        $this->assertSame('7.000', $p->fresh()->stock_qty);
    }

    public function test_refund_restocks_refunds_cash_and_is_idempotent(): void
    {
        $owner = $this->staff('owner');
        $p = $this->product();
        $session = $this->openTill($owner, '100');
        $this->actingAs($owner)->postJson(route('admin.pos.sell'), ['key' => (string) Str::uuid(), 'register' => 'Caisse 1', 'lines' => [['product_id' => $p->id, 'quantity' => 3]], 'payments' => [['method' => 'cash', 'amount' => '135']]])->assertOk();
        $sale = RetailSale::first();
        $item = $sale->items->first();
        $key = (string) Str::uuid();
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($owner)->post(route('admin.retail.sales.refund', $sale), ['qty' => [$item->id => 1], 'method' => 'cash', 'restock' => '1', 'reason' => 'Défaut', 'key' => $key])->assertRedirect();
        }
        $sale->refresh();
        $this->assertSame('45.00', $sale->refunded_total);
        $this->assertSame('partially_refunded', $sale->status);
        $this->assertSame('8.000', $p->fresh()->stock_qty);            // 10 − 3 + 1
        $this->actingAs($owner)->post(route('admin.retail.sales.refund', $sale), ['qty' => [$item->id => 3], 'method' => 'cash', 'restock' => '1', 'reason' => 'x', 'key' => (string) Str::uuid()])->assertSessionHasErrors('lines');
        $summary = app(RetailService::class)->sessionSummary($session);
        $this->assertSame('190.00', $summary['expected']);              // 100 + 135 − 45
        $this->assertSame(1, RetailStockMovement::where('type', 'return')->count());
    }

    public function test_invoice_pdf_for_store_sale_and_customer_access(): void
    {
        $owner = $this->staff('owner');
        $p = $this->product(['tax_rate' => '20.00']);
        $book = $this->product(['name' => 'Livre', 'price_ttc' => '107.00', 'tax_rate' => '7.00']);
        $this->openTill($owner);
        $customer = $this->customerUser('Atlas');
        $this->actingAs($owner)->postJson(route('admin.pos.sell'), ['key' => (string) Str::uuid(), 'register' => 'Caisse 1', 'customer_id' => $customer->customer_id,
            'lines' => [['product_id' => $p->id, 'quantity' => 1], ['product_id' => $book->id, 'quantity' => 1]], 'payments' => [['method' => 'card_in_store', 'amount' => '152']]])->assertOk();
        $sale = RetailSale::first();
        $this->assertSame('14.50', $sale->tax_total);                   // 7.50 + 7.00 (mixed VAT)
        $this->actingAs($owner)->post(route('admin.retail.sales.invoice', $sale))->assertRedirect();
        $this->actingAs($owner)->post(route('admin.retail.sales.invoice', $sale))->assertRedirect();
        $this->assertSame(1, Document::where('type', 'invoice')->count());
        $inv = Document::first();
        $this->assertSame('152.00', $inv->total);
        $this->assertStringStartsWith('%PDF', app(DocumentService::class)->renderPdf($inv));
        $credit = app(DocumentService::class)->creditNote($inv, '107.00', 'Retour livre', '7');
        $this->assertSame('100.00', $credit->subtotal);                 // VAT of the returned item (7 %)
        $this->assertSame('7.00', $credit->tax_total);
        $this->actingAs($customer)->get(route('files.document', $inv))->assertOk();
        $this->actingAs($customer)->get(route('account.invoices'))->assertSee($inv->number);
        $other = $this->customerUser('Other');
        $this->actingAs($other)->get(route('files.document', $inv))->assertNotFound();
    }

    public function test_supplier_purchase_receipt_updates_store_stock_cost_and_balance(): void
    {
        $owner = $this->staff('owner');
        $supplier = Supplier::create(['name' => 'Fournisseur Papier']);
        $p = $this->product();                                           // 10 @ 30.00
        $this->actingAs($owner)->post(route('admin.po.store'), ['supplier_id' => $supplier->id, 'supplier_reference' => 'FAC-778',
            'items' => [['retail_product_id' => $p->id, 'quantity' => 10, 'unit_cost' => '34.00']]])->assertRedirect();
        $po = PurchaseOrder::first();
        $item = $po->items->first();
        $key = (string) Str::uuid();
        $this->actingAs($owner)->post(route('admin.po.receive', $po), ['qty' => [$item->id => 4], 'key' => $key])->assertRedirect();
        $this->actingAs($owner)->post(route('admin.po.receive', $po), ['qty' => [$item->id => 4], 'key' => $key]); // replay
        $p->refresh();
        $this->assertSame('14.000', $p->stock_qty);
        $this->assertSame('31.1429', $p->cost_price);                    // (10×30 + 4×34) / 14
        $this->assertSame('partial', $po->fresh()->status);
        $this->assertSame(['received' => '136.00', 'paid' => '0.00', 'due' => '136.00'], $supplier->balance());
        $pk = (string) Str::uuid();
        $this->actingAs($owner)->post(route('admin.suppliers.payment', $supplier), ['amount' => '100', 'method' => 'cheque', 'paid_at' => today()->toDateString(), 'key' => $pk]);
        $this->actingAs($owner)->post(route('admin.suppliers.payment', $supplier), ['amount' => '100', 'method' => 'cheque', 'paid_at' => today()->toDateString(), 'key' => $pk]);
        $this->assertSame('36.00', $supplier->balance()['due']);
        $this->actingAs($owner)->get(route('admin.suppliers.show', $supplier))->assertOk()->assertSee('FAC-778');
        foreach (['retail_daily', 'retail_products', 'retail_payments', 'retail_stock', 'supplier_balances'] as $r) {
            $this->actingAs($owner)->get(route('admin.reports.index', ['report' => $r]))->assertOk();
        }
    }

    public function test_store_screens_render(): void
    {
        $this->seed(\Database\Seeders\DemoSeeder::class);
        $owner = $this->staff('owner');
        $sale = RetailSale::first();
        $p = RetailProduct::first();
        foreach ([route('admin.pos'), route('admin.retail.sales.index'), route('admin.retail.sales.show', $sale), route('admin.retail.ticket', $sale),
            route('admin.retail.sessions.index'), route('admin.retail.sessions.show', CashSession::first()), route('admin.retail.products.index'),
            route('admin.retail.products.index', ['low' => 1]), route('admin.retail.products.create'), route('admin.retail.products.show', $p), route('admin.retail.products.edit', $p),
            route('admin.suppliers.index'), route('admin.suppliers.show', Supplier::first()), route('admin.r.index', 'retail_categories'), route('admin.dashboard')] as $u) {
            $this->actingAs($owner)->get($u)->assertOk();
        }
        $this->actingAs($owner)->post(route('admin.retail.products.store'), ['name' => 'Gomme', 'sku' => 'GOM-1', 'unit' => 'piece', 'price_ttc' => '2.50', 'tax_rate' => '20',
            'low_stock_threshold' => '5', 'track_stock' => '1', 'is_active' => '1', 'initial_stock' => '40', 'initial_cost' => '1.10'])->assertRedirect();
        $this->assertSame('40.000', RetailProduct::where('sku', 'GOM-1')->value('stock_qty'));
    }
}
