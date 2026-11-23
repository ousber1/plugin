<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Full digital store: categories, MAD/USD prices, subscriptions, cart orders, licences, coupons, reviews. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('slug')->constrained()->nullOnDelete();
            $table->longText('subtitle')->nullable()->after('title');
            $table->longText('features')->nullable();       // "what you get" list (translatable)
            $table->longText('requirements')->nullable();   // list (translatable)
            $table->longText('changelog')->nullable();      // [{version,date,notes}]
            $table->longText('gallery')->nullable();        // public image paths
            $table->unsignedInteger('compare_price_cents')->nullable();
            $table->unsignedInteger('price_usd_cents')->nullable();
            $table->unsignedInteger('compare_price_usd_cents')->nullable();
            $table->string('billing', 10)->default('once'); // once | year | month
            $table->unsignedSmallInteger('license_sites')->default(1);
            $table->string('version', 30)->nullable();
            $table->string('requires', 60)->nullable();
            $table->string('tested_up_to', 60)->nullable();
            $table->string('file_format', 20)->nullable();
            $table->unsignedSmallInteger('files_count')->nullable();
            $table->string('demo_url', 500)->nullable();
            $table->date('updated_on')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->longText('seo_title')->nullable();
            $table->longText('seo_description')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('name', 120)->nullable()->after('user_id');
            $table->string('phone', 40)->nullable()->after('name');
            $table->string('email', 190)->nullable()->after('phone');
            $table->string('payment_method', 20)->nullable()->after('currency');
            $table->unsignedInteger('subtotal_cents')->default(0)->after('amount_cents');
            $table->unsignedInteger('discount_cents')->default(0)->after('subtotal_cents');
            $table->string('coupon_code', 40)->nullable()->after('discount_cents');
            $table->string('receipt_path')->nullable();
            $table->text('customer_note')->nullable();
        });
        // Orders can hold several products now.
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 255);
            $table->unsignedInteger('price_cents');
            $table->string('billing', 10)->default('once');
            $table->string('license_key', 40)->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('type', 10)->default('percent'); // percent | fixed (MAD)
            $table->unsignedInteger('value');
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
            $table->unique(['product_id', 'user_id']);
        });

        // Existing single-product orders become one-line orders.
        foreach (DB::table('orders')->whereNotNull('product_id')->get() as $o) {
            $title = DB::table('products')->where('id', $o->product_id)->value('title');
            $decoded = json_decode((string) $title, true);
            DB::table('order_items')->insert([
                'order_id' => $o->id, 'product_id' => $o->product_id,
                'title' => is_array($decoded) ? ($decoded['ar'] ?? reset($decoded)) : (string) $title,
                'price_cents' => $o->amount_cents, 'billing' => 'once', 'created_at' => $o->created_at, 'updated_at' => $o->updated_at,
            ]);
            DB::table('orders')->where('id', $o->id)->update(['subtotal_cents' => $o->amount_cents, 'payment_method' => 'bank']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('order_items');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['name', 'phone', 'email', 'payment_method', 'subtotal_cents', 'discount_cents', 'coupon_code', 'receipt_path', 'customer_note']));
        Schema::table('products', function (Blueprint $t) {
            $t->dropConstrainedForeignId('category_id');
            $t->dropColumn(['subtitle', 'features', 'requirements', 'changelog', 'gallery', 'compare_price_cents', 'price_usd_cents', 'compare_price_usd_cents', 'billing', 'license_sites', 'version', 'requires', 'tested_up_to', 'file_format', 'files_count', 'demo_url', 'updated_on', 'is_featured', 'sort_order', 'seo_title', 'seo_description']);
        });
    }
};
