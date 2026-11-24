<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Physical retail store (point of sale): goods catalogue, stock ledger, cash sessions,
 * sales with payments/refunds, supplier payments, and links to purchasing and documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retail_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 9)->default('#1B1F3B');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('retail_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retail_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 60)->unique();
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit', 12)->default('piece');
            $table->decimal('price_ttc', 12, 2);                 // shelf price paid by the customer
            $table->decimal('tax_rate', 5, 2)->default(20);
            $table->decimal('cost_price', 12, 4)->default(0);    // weighted average purchase cost (HT)
            $table->decimal('stock_qty', 14, 3)->default(0);
            $table->decimal('low_stock_threshold', 14, 3)->default(0);
            $table->boolean('track_stock')->default(true);
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->index(['is_active', 'name']);
        });

        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('register', 40)->default('Caisse 1');
            $table->string('status', 10)->default('open'); // open|closed
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_float', 12, 2)->default(0);
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['register', 'status']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->string('type', 5); // in|out
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->string('idempotency_key', 120)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('retail_sales', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('completed'); // completed|partially_refunded|refunded
            $table->decimal('subtotal_ht', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->string('discount_reason')->nullable();
            $table->decimal('total_ttc', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('change_given', 12, 2)->default(0);
            $table->decimal('refunded_total', 12, 2)->default(0);
            $table->decimal('cost_total', 12, 2)->default(0);
            $table->string('idempotency_key', 100)->unique();
            $table->text('notes')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('retail_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retail_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('retail_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 60);
            $table->string('name');
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price_ttc', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('total_ttc', 12, 2);
            $table->decimal('total_ht', 12, 2);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('returned_qty', 14, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('retail_sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retail_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10)->default('payment'); // payment|refund
            $table->string('method', 30);
            $table->decimal('amount', 12, 2);
            $table->string('reference')->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('retail_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retail_product_id')->constrained()->restrictOnDelete();
            $table->string('type', 20); // initial|purchase|sale|return|adjustment|loss
            $table->decimal('quantity', 14, 3); // signed effect on stock
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->foreignId('retail_sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key', 120)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 30);
            $table->string('reference')->nullable();
            $table->date('paid_at');
            $table->string('notes', 500)->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('retail_product_id')->nullable()->after('material_id')->constrained()->nullOnDelete();
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('supplier_reference')->nullable()->after('status'); // supplier's invoice / delivery ref
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('retail_sale_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', fn (Blueprint $t) => $t->dropConstrainedForeignId('retail_sale_id'));
        Schema::table('purchase_orders', fn (Blueprint $t) => $t->dropColumn('supplier_reference'));
        Schema::table('purchase_order_items', fn (Blueprint $t) => $t->dropConstrainedForeignId('retail_product_id'));
        foreach (['supplier_payments', 'retail_stock_movements', 'retail_sale_payments', 'retail_sale_items', 'retail_sales', 'cash_movements', 'cash_sessions', 'retail_products', 'retail_categories'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
