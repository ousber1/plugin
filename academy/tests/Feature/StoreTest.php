<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Settings;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaseSeeder::class);
        Settings::set([
            'features' => array_merge(Settings::get('features'), ['store' => true]),
            'whatsapp_number' => '212611223344', 'store_usd_rate' => 10,
            'bank_accounts' => [['bank' => 'CIH Bank', 'holder' => 'Academy SARL', 'rib' => '230780000000000000000011']],
            'pay_paypal_enabled' => true, 'paypal_link' => 'https://paypal.me/academy',
        ]);
    }

    private function product(array $attrs = []): Product
    {
        $cat = Category::firstOrCreate(['type' => 'product', 'slug' => 'wp-plugins'], ['name' => ['ar' => 'إضافات ووردبريس'], 'is_active' => true]);
        $p = new Product($attrs + [
            'title' => ['ar' => 'Medel Try-On', 'en' => 'Try-On'], 'subtitle' => ['ar' => 'التجربة الافتراضية للمنتجات'],
            'description' => ['ar' => '<p>جرّب قبل أن تشتري</p>'], 'features' => ['ar' => ['تحديثات ودعم', 'مفتاح ترخيص']],
            'price_cents' => 49900, 'compare_price_cents' => 69900, 'billing' => 'year', 'version' => '1.2.0', 'file_format' => 'ZIP',
            'changelog' => [['version' => '1.2.0', 'date' => '2026-11-10', 'notes' => 'تحسينات']], 'status' => 'published', 'category_id' => $cat->id,
        ]);
        $p->slug = $attrs['slug'] ?? 'try-on';
        Storage::disk('local')->put('products/'.$p->slug.'.zip', 'PK');
        $p->forceFill(['disk' => 'local', 'file_path' => 'products/'.$p->slug.'.zip', 'original_name' => $p->slug.'.zip'])->save();

        return $p;
    }

    public function test_store_pages_show_categories_prices_currencies_and_product_details(): void
    {
        $p = $this->product();
        $this->product(['slug' => 'cod', 'title' => ['ar' => 'Medel COD'], 'price_cents' => 15000, 'compare_price_cents' => null, 'billing' => 'once']);

        $this->get(route('store.index'))->assertOk()->assertSee('المتجر الرقمي')->assertSee('إضافات ووردبريس')
            ->assertSee('499 درهم')->assertSee('خصم 29%')->assertSee('اشترِ الآن')->assertSee('150 درهم');
        $this->get(route('store.index', ['category' => 'wp-plugins', 'sort' => 'price_asc']))->assertOk()->assertSeeInOrder(['Medel COD', 'Medel Try-On']);
        $this->get(route('store.index', ['q' => 'COD']))->assertSee('Medel COD')->assertDontSee('Medel Try-On');

        $this->get(route('store.show', $p))->assertOk()->assertSee('التجربة الافتراضية للمنتجات')->assertSee('سجل التحديثات')
            ->assertSee('ما ستحصل عليه')->assertSee('/ سنة')->assertSee('"@type":"Product"', false);

        $this->get(route('store.currency', 'USD'))->assertRedirect();
        $this->get(route('store.show', $p))->assertSee('$50')->assertSee('$70');
        $this->get(route('store.currency', 'EUR'))->assertNotFound();
    }

    public function test_full_purchase_flow_with_coupon_receipt_licence_and_renewal(): void
    {
        Storage::fake('local');
        $p = $this->product();
        Coupon::create(['code' => 'WELCOME10', 'type' => 'percent', 'value' => 10, 'is_active' => true]);
        $buyer = $this->student();

        // Guest can fill the cart; checkout requires login.
        $this->post(route('store.cart.add', $p))->assertRedirect();
        $this->get(route('store.cart'))->assertOk()->assertSee('Medel Try-On')->assertSee('499 درهم');
        $this->get(route('store.checkout'))->assertRedirect(route('login'));

        $this->actingAs($buyer);
        $this->post(route('store.cart.add', $p));
        $this->post(route('store.coupon'), ['code' => 'nope'])->assertSessionHasErrors('code');
        $this->post(route('store.coupon'), ['code' => 'welcome10'])->assertSessionHas('success');
        $this->get(route('store.checkout'))->assertOk()->assertSee('تحويل بنكي')->assertSee('وكالات تحويل الأموال')->assertDontSee('PayPal</strong>', false)
            ->assertSee('449.10 درهم');
        $this->post(route('store.place'), ['name' => 'Oussama', 'phone' => '0612345678', 'email' => 'o@example.com', 'payment_method' => 'paypal', 'accept' => 1])
            ->assertSessionHasErrors('payment_method'); // PayPal is USD only
        $this->post(route('store.place'), ['name' => 'Oussama', 'phone' => '0612345678', 'email' => 'o@example.com', 'payment_method' => 'bank', 'accept' => 1])->assertRedirect();

        $order = Order::with('items')->firstOrFail();
        $this->assertSame(44910, $order->amount_cents);
        $this->assertSame(4990, $order->discount_cents);
        $this->assertSame('WELCOME10', $order->coupon_code);
        $this->assertSame(1, Coupon::first()->used);
        $this->assertCount(1, $order->items);
        $this->assertSame([], session('cart', []));

        $this->get(route('store.order', $order))->assertOk()->assertSee('230780000000000000000011')->assertSee('ارفع وصل الدفع');
        $this->get(route('store.download', [$order, $order->items[0]]))->assertForbidden();
        $this->post(route('store.receipt', $order), ['receipt' => UploadedFile::fake()->image('r.jpg')])->assertSessionHas('success');
        $this->assertSame('review', $order->fresh()->status);

        // Another user cannot see the order.
        $this->actingAs($this->student())->get(route('store.order', $order))->assertNotFound();

        // Admin reviews the receipt and confirms payment → licence key valid one year.
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.orders.index'))->assertOk()->assertSee($order->reference)->assertSee('وصل مرفوع');
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Oussama');
        $this->get(route('admin.orders.receipt', $order))->assertOk();
        $this->put(route('admin.orders.update', $order), ['status' => 'paid'])->assertSessionHas('success');
        $item = OrderItem::firstOrFail();
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{5}(-[A-Z0-9]{5}){3}$/', $item->license_key);
        $this->assertTrue($item->expires_at->between(now()->addDays(364), now()->addDays(366)));

        $this->actingAs($buyer)->get(route('store.account'))->assertOk()->assertSee($item->license_key)->assertSee('تحميل');
        $this->get(route('store.download', [$order, $item]))->assertOk();

        // Buyer can review; review waits for approval.
        $this->post(route('store.review', $p), ['rating' => 5, 'body' => 'ممتاز'])->assertSessionHas('success');
        $this->actingAs($this->student())->get(route('store.show', $p))->assertDontSee('ممتاز');
        $this->post(route('store.review', $p), ['rating' => 1])->assertForbidden();

        // Renewal: buying again extends the SAME key by one more year.
        $this->actingAs($buyer)->post(route('store.cart.add', $p));
        $this->post(route('store.place'), ['name' => 'Oussama', 'phone' => '0612345678', 'email' => 'o@example.com', 'payment_method' => 'whatsapp', 'accept' => 1])->assertRedirect();
        $renewal = Order::latest('id')->firstOrFail();
        $this->get(route('store.order', $renewal))->assertSee('wa.me/212611223344', false);
        $this->actingAs($admin)->put(route('admin.orders.update', $renewal), ['status' => 'paid']);
        $renewed = $renewal->items()->first();
        $this->assertSame($item->license_key, $renewed->license_key);
        $this->assertTrue($renewed->expires_at->gt(now()->addDays(729)));

        // Expired licences block downloads until renewed.
        OrderItem::query()->update(['expires_at' => now()->subDay()]);
        $this->actingAs($buyer)->get(route('store.download', [$order, $item]))->assertRedirect(route('store.show', $p));
    }

    public function test_usd_checkout_offers_paypal_and_admin_manages_products_and_coupons(): void
    {
        $p = $this->product(['price_usd_cents' => 4900]);
        $this->actingAs($this->student());
        $this->get(route('store.currency', 'USD'));
        $this->post(route('store.cart.add', $p));
        $this->get(route('store.checkout'))->assertSee('PayPal')->assertDontSee('وكالات تحويل الأموال');
        $this->post(route('store.place'), ['name' => 'Sam', 'phone' => '+1 555 123 4567', 'email' => 's@example.com', 'payment_method' => 'paypal', 'accept' => 1]);
        $order = Order::firstOrFail();
        $this->assertSame('USD', $order->currency);
        $this->assertSame(4900, $order->amount_cents);
        $this->get(route('store.order', $order))->assertSee('https://paypal.me/academy', false);

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.products.create'))->assertOk()->assertSee('اشتراك سنوي');
        $this->post(route('admin.products.store'), [
            'title' => ['ar' => 'قالب بازار'], 'price' => 290, 'compare_price' => 390, 'billing' => 'once', 'license_sites' => 1,
            'status' => 'published', 'version' => '1.1.0', 'features' => ['ar' => "قالب كامل\nتحديثات"],
            'changelog' => [['version' => '1.1.0', 'date' => '2026-10-01', 'notes' => 'إطلاق']],
            'gallery_add' => [UploadedFile::fake()->image('g1.jpg', 1200, 700)],
        ])->assertRedirect(route('admin.products.index'));
        $new = Product::where('version', '1.1.0')->firstOrFail();
        $this->assertSame(['قالب كامل', 'تحديثات'], $new->trList('features'));
        $this->assertCount(1, $new->gallery);
        $this->get(route('admin.products.index'))->assertOk()->assertSee('قالب بازار');

        $this->post(route('admin.coupons.store'), ['code' => 'bad code!', 'type' => 'percent', 'value' => 10])->assertSessionHasErrors('code');
        $this->post(route('admin.coupons.store'), ['code' => 'summer', 'type' => 'percent', 'value' => 20, 'is_active' => 1])->assertRedirect();
        $this->assertDatabaseHas('coupons', ['code' => 'SUMMER']);
        $this->put(route('admin.settings.update', 'store'), [
            'store_default_currency' => 'MAD', 'store_usd_rate' => 10.5, 'store_usd_enabled' => 1, 'pay_bank_enabled' => 1,
            'bank_accounts' => [['bank' => 'BMCE', 'holder' => 'X', 'rib' => '011780000000000000000099'], ['rib' => '']],
            'agency_receiver' => 'Ahmed', 'paypal_link' => 'http://insecure.example',
        ])->assertSessionHasErrors('paypal_link');
        $this->actingAs($this->student())->get(route('admin.orders.index'))->assertForbidden();
    }
}
