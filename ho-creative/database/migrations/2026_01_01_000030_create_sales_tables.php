<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM, carts, quotes, orders, artwork, proofs, payments, documents, reviews.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('type', 12)->default('individual'); // individual|company
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 40)->nullable()->index();
            $table->string('whatsapp', 40)->nullable();
            $table->string('ice', 30)->nullable();      // Identifiant Commun de l'Entreprise
            $table->string('rc', 30)->nullable();       // Registre de commerce
            $table->string('if_number', 30)->nullable();// Identifiant fiscal
            $table->string('city')->nullable();
            $table->decimal('credit_limit', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 20)->default('web');
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('type', 10)->default('shipping'); // shipping|billing
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('postal_code', 20)->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('designs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_key', 100)->nullable()->index();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->decimal('width_mm', 8, 2);
            $table->decimal('height_mm', 8, 2);
            $table->decimal('bleed_mm', 5, 2)->default(3);
            $table->decimal('safe_mm', 5, 2)->default(4);
            $table->unsignedTinyInteger('sides')->default(1);
            $table->json('data')->nullable();
            $table->json('exports')->nullable(); // {front: path, back: path, pdf: path}
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coupon_code', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->json('selection'); // raw user input (option codes)
            $table->string('artwork_mode', 20)->default('later');
            $table->foreignId('design_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('email');
            $table->string('phone', 40);
            $table->string('product_type');
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->string('unit', 4)->default('mm');
            $table->unsignedInteger('quantity');
            $table->string('material')->nullable();
            $table->string('finishes')->nullable();
            $table->date('deadline')->nullable();
            $table->string('city')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('new'); // new|in_progress|quoted|closed
            $table->unsignedBigInteger('quote_id')->nullable();
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('quote_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20)->default('web'); // web|walk_in|phone|whatsapp|b2b|email
            $table->string('status', 20)->default('draft'); // draft|sent|accepted|rejected|expired|converted
            $table->unsignedInteger('current_revision')->default(1);
            $table->string('token', 64)->unique();
            $table->date('expires_at')->nullable();
            $table->unsignedInteger('accepted_revision')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('response_name')->nullable();
            $table->string('response_note', 1000)->nullable();
            $table->string('locale', 5)->default('fr');
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('quote_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->string('discount_reason')->nullable();
            $table->decimal('shipping_total', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('shipping_method', 20)->default('pickup');
            $table->foreignId('shipping_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['quote_id', 'revision']);
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_revision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('rule_set_id')->nullable();
            $table->unsignedInteger('rule_version')->nullable();
            $table->string('description', 500);
            $table->json('selection')->nullable();   // raw selection
            $table->json('options_snapshot')->nullable(); // labelled options
            $table->json('breakdown')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('width_mm', 10, 2)->nullable();
            $table->decimal('height_mm', 10, 2)->nullable();
            $table->decimal('unit_price', 12, 4);
            $table->decimal('total', 12, 2);
            $table->string('artwork_mode', 20)->default('later');
            $table->boolean('is_custom')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->unsignedInteger('quote_revision')->nullable();
            $table->string('channel', 20)->default('web');
            $table->string('status', 20)->default('pending')->index(); // pending|confirmed|in_production|ready|completed|cancelled
            $table->string('payment_status', 20)->default('unpaid')->index(); // unpaid|partial|paid|refunded|partially_refunded
            $table->string('fulfillment_status', 20)->default('unfulfilled'); // unfulfilled|in_production|ready|shipped|delivered|picked_up
            $table->string('payment_method', 30)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->string('discount_reason')->nullable();
            $table->string('coupon_code', 40)->nullable();
            $table->decimal('shipping_total', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('amount_refunded', 12, 2)->default(0);
            $table->decimal('deposit_required', 12, 2)->default(0);
            $table->string('currency', 3)->default('MAD');
            $table->string('shipping_method', 20)->default('pickup'); // pickup|delivery
            $table->foreignId('shipping_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('guest_token', 64)->nullable()->unique();
            $table->string('locale', 5)->default('fr');
            $table->date('estimated_ready_at')->nullable();
            $table->date('estimated_delivery_at')->nullable();
            $table->boolean('estimate_depends_on_proof')->default(false);
            $table->string('checkout_key', 64)->nullable()->unique(); // idempotency for checkout submit
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('rule_set_id')->nullable();
            $table->unsignedInteger('rule_version')->nullable();
            $table->string('description', 500);
            $table->json('selection')->nullable();
            $table->json('options_snapshot')->nullable();
            $table->json('breakdown')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('width_mm', 10, 2)->nullable();
            $table->decimal('height_mm', 10, 2)->nullable();
            $table->decimal('unit_price', 12, 4);
            $table->decimal('subtotal', 12, 2); // before manual discount
            $table->decimal('manual_discount', 12, 2)->default(0);
            $table->string('manual_discount_reason')->nullable();
            $table->decimal('total', 12, 2);
            $table->string('artwork_mode', 20)->default('later');
            $table->foreignId('design_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('requires_proof')->default(true);
            $table->string('artwork_status', 20)->default('pending'); // pending|received|in_review|ok|issues
            $table->string('proof_status', 20)->default('none'); // none|pending|approved|changes_requested
            $table->unsignedSmallInteger('production_days')->nullable();
            $table->boolean('is_custom')->default(false);
            $table->foreignId('remake_of_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->string('remake_reason')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('artwork_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('cart_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('side', 10)->default('front'); // front|back|single
            $table->unsignedInteger('version')->default(1);
            $table->string('disk', 20)->default('private');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64)->nullable();
            $table->json('checks')->nullable(); // automated check results
            $table->string('review_status', 20)->default('pending'); // pending|ok|issues
            $table->string('review_note', 1000)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->string('status', 20)->default('pending'); // pending|approved|changes_requested|superseded
            $table->text('staff_message')->nullable();
            $table->text('customer_comment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('responded_ip', 45)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['order_item_id', 'version']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('type', 10)->default('payment'); // payment|refund
            $table->string('method', 30); // bank_transfer|cash|card_in_store|cod|online|cheque
            $table->decimal('amount', 12, 2); // always positive
            $table->string('status', 20)->default('completed'); // pending|completed|failed
            $table->string('reference')->nullable();
            $table->string('provider', 30)->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('idempotency_key', 100)->unique();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('event_id', 150);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable();
            $table->string('result', 30)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // quote|proforma|invoice|credit_note|purchase_order|delivery_note|receipt
            $table->string('number', 40);
            $table->string('uniq_key', 120)->nullable()->unique(); // prevents duplicate issuance on retry
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('related_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('status', 20)->default('issued'); // issued|cancelled
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->json('snapshot'); // frozen content rendered into the PDF
            $table->string('locale', 5)->default('fr');
            $table->string('reason', 500)->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->unique(['type', 'number']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name');
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body');
            $table->string('status', 20)->default('pending'); // pending|approved|rejected
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->index(['product_id', 'status']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('disk', 20)->default('private');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['attachments', 'reviews', 'documents', 'payment_events', 'payments', 'proofs', 'artwork_files', 'order_items', 'orders', 'quote_items', 'quote_revisions', 'quotes', 'quote_requests', 'cart_items', 'carts', 'designs', 'addresses', 'customer_notes', 'customer_contacts'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('customer_id'));
        Schema::dropIfExists('customers');
    }
};
