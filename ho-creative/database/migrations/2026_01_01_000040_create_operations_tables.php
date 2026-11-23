<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production, stock, suppliers, purchasing, expenses, shipments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('department', 40)->nullable(); // offset|digital|large_format|finishing|packaging
            $table->decimal('hourly_cost', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('production_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 30)->default('artwork_pending')->index();
            $table->string('priority', 10)->default('normal'); // low|normal|high|urgent
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('department', 40)->nullable();
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_at')->nullable()->index();
            $table->text('internal_notes')->nullable();
            $table->decimal('estimated_cost', 12, 2)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('production_job_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_job_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage', 30)->nullable();
            $table->string('to_stage', 30);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('city')->nullable();
            $table->string('ice', 30)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 60)->unique();
            $table->string('name');
            $table->string('category', 40)->nullable(); // paper|vinyl|ink|board|film|packaging|other
            $table->string('unit', 10); // sheet|roll|m|m2|piece|l|kg
            $table->decimal('stock_qty', 14, 3)->default(0);
            $table->decimal('reserved_qty', 14, 3)->default(0);
            $table->decimal('low_stock_threshold', 14, 3)->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft'); // draft|sent|partial|received|cancelled
            $table->date('expected_at')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_job_id')->nullable()->constrained()->nullOnDelete(); // outsourced work
            $table->string('description');
            $table->decimal('quantity', 14, 3);
            $table->decimal('quantity_received', 14, 3)->default(0);
            $table->decimal('unit_cost', 12, 4);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('type', 20); // receipt|reservation|release|consumption|waste|adjustment
            $table->decimal('quantity', 14, 3); // signed effect on stock_qty (reservation/release affect reserved_qty)
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->foreignId('production_job_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key', 120)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('job_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_job_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // labor|setup|machine|material|outsourced|waste|other
            $table->boolean('is_estimate')->default(false);
            $table->string('description');
            $table->decimal('quantity', 14, 3)->default(1);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('total', 12, 2);
            $table->foreignId('stock_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('category', 40);
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20); // pickup|local|carrier
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('status', 20)->default('pending'); // pending|ready|shipped|delivered|picked_up|returned
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['shipments', 'expenses', 'job_costs', 'stock_movements', 'purchase_order_items', 'purchase_orders', 'materials', 'suppliers', 'production_job_events', 'production_jobs', 'machines'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
