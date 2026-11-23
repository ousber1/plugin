<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog: categories, products, versioned pricing rule sets, templates, reviews, coupons.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('slug')->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('image')->nullable();
            $table->string('icon', 40)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_published')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('slug')->unique();
            $table->string('sku', 60)->nullable()->unique();
            $table->json('name');
            $table->json('summary')->nullable();
            $table->json('description')->nullable();
            // instant = configure & buy, quote = quote request only, unpublished = hidden
            $table->string('sale_mode', 20)->default('instant')->index();
            $table->json('images')->nullable();         // list of public paths
            $table->json('specs')->nullable();          // [{label:{fr,ar}, value:{fr,ar}}]
            $table->json('finishes')->nullable();       // [{fr,ar}]
            $table->json('faq')->nullable();            // [{q:{fr,ar}, a:{fr,ar}}]
            $table->json('guides')->nullable();         // [{title:{fr,ar}, path}]
            $table->json('artwork_modes')->nullable();  // upload,later,template,editor,design_service
            $table->decimal('design_fee', 12, 2)->default(0);
            $table->boolean('requires_proof')->default(true);
            $table->decimal('from_price', 12, 2)->nullable(); // computed from published rule set
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_demo')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->text('search_text')->nullable(); // lowercase fr+ar names/summary/sku, maintained on save
            $table->timestamps();
        });

        Schema::create('pricing_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('draft'); // draft|published|archived
            $table->json('definition');
            $table->string('notes', 500)->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'version']);
            $table->index(['product_id', 'status']);
        });

        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->json('name');
            $table->string('thumbnail')->nullable();
            $table->decimal('width_mm', 8, 2);
            $table->decimal('height_mm', 8, 2);
            $table->decimal('bleed_mm', 5, 2)->default(3);
            $table->decimal('safe_mm', 5, 2)->default(4);
            $table->unsignedTinyInteger('sides')->default(1);
            $table->json('design'); // {front: fabricJSON, back: fabricJSON}
            $table->boolean('is_published')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('type', 10)->default('percent'); // percent|fixed
            $table->decimal('value', 12, 2);
            $table->decimal('min_subtotal', 12, 2)->nullable();
            $table->json('product_ids')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['coupons', 'templates', 'pricing_rule_sets', 'products', 'categories'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
