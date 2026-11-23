<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Document;
use App\Models\Product;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\OrderService;
use App\Services\Pricing\PricingEngine;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InstallAndRenderingTest extends TestCase
{
    public function test_pdf_documents_render_in_french_and_arabic(): void
    {
        $p = Product::where('slug', 'cartes-de-visite-standard')->first();
        $c = Customer::create(['name' => 'شركة المثال', 'company_name' => 'شركة المثال', 'ice' => '001']);
        foreach (['fr', 'ar'] as $locale) {
            $order = app(OrderService::class)->create($c, [OrderService::lineFromPrice($p, app(PricingEngine::class)->forProduct($p, ['quantity' => 100], $locale))], ['locale' => $locale]);
            $doc = app(DocumentService::class)->proforma($order);
            $pdf = app(DocumentService::class)->renderPdf($doc);
            $this->assertStringStartsWith('%PDF', $pdf);
            $this->assertGreaterThan(5000, strlen($pdf));
            file_put_contents(storage_path("app/test-proforma-{$locale}.pdf"), $pdf);
        }
        $html = view('pdf.document', ['doc' => Document::latest('id')->first(), 's' => Document::latest('id')->first()->snapshot, 'rtl' => true])->render();
        $this->assertStringContainsString('dir="rtl"', $html);
    }

    public function test_admin_screens_render_for_owner(): void
    {
        $this->seed(\Database\Seeders\DemoSeeder::class);
        $owner = $this->staff('owner');
        $order = \App\Models\Order::first();
        $quote = \App\Models\Quote::first();
        $job = \App\Models\ProductionJob::first();
        $product = Product::first();
        $urls = [
            route('admin.dashboard'), route('admin.orders.index'), route('admin.orders.create'), route('admin.orders.show', $order), route('admin.quotes.index'),
            route('admin.quotes.create'), route('admin.quotes.show', $quote), route('admin.quotes.revise', $quote), route('admin.quote-requests.index'),
            route('admin.customers.index'), route('admin.customers.create'), route('admin.customers.show', $order->customer), route('admin.customers.edit', $order->customer),
            route('admin.payments.index'), route('admin.documents.index'), route('admin.production.board'), route('admin.production.list'),
            route('admin.production.show', $job), route('admin.production.sheet', $job), route('admin.deliveries.index'), route('admin.stock.index'),
            route('admin.materials.show', \App\Models\Material::first()), route('admin.po.index'), route('admin.po.create'), route('admin.products.index'),
            route('admin.products.create'), route('admin.products.edit', $product), route('admin.pricing.edit', $product), route('admin.templates.index'),
            route('admin.templates.create'), route('admin.templates.edit', \App\Models\Template::first()), route('admin.reviews.index'), route('admin.staff.index'),
            route('admin.staff.create'), route('admin.settings.edit'), route('admin.audit.index'),
        ];
        foreach (array_keys(\App\Admin\ResourceRegistry::all()) as $r) {
            $urls[] = route('admin.r.index', $r);
            $urls[] = route('admin.r.create', $r);
            $urls[] = route('admin.r.export', $r);
        }
        foreach (array_keys(\App\Services\ReportService::REPORTS) as $r) {
            $urls[] = route('admin.reports.index', ['report' => $r]);
            $urls[] = route('admin.reports.csv', $r);
        }
        foreach ($urls as $u) {
            $this->actingAs($owner)->get($u)->assertOk();
        }
    }

    public function test_public_and_account_screens_render(): void
    {
        $this->seed(\Database\Seeders\DemoSeeder::class);
        $user = $this->customerUser('Sara');
        $urls = ['/', '/?lang=ar', route('search', ['q' => 'carte']), route('templates'), route('quote.request'), route('cart'), route('page', 'aide'), route('sitemap'), '/robots.txt', route('login'), route('register'), route('password.request')];
        foreach (\App\Models\Category::all() as $c) {
            $urls[] = route('category', $c->slug);
        }
        foreach (Product::visible()->get() as $p) {
            $urls[] = route('product', $p->slug);
        }
        foreach ($urls as $u) {
            $this->get($u)->assertOk();
        }
        foreach (['account.dashboard', 'account.orders', 'account.quotes', 'account.invoices', 'account.designs', 'account.addresses.index', 'account.addresses.create', 'account.profile'] as $r) {
            $this->actingAs($user)->get(route($r))->assertOk();
        }
        $this->get(route('quote.public', \App\Models\Quote::first()->token))->assertOk();
    }
}
