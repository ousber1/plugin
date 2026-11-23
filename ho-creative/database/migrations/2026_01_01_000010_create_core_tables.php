<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core configuration, content and audit tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80)->index();
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('title');
            $table->json('body')->nullable();
            $table->json('meta_description')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('location', 20)->default('header'); // header|footer
            $table->json('label');
            $table->string('url');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30); // hero|promotions|categories|popular|how_it_works|quote_cta|reviews|support|rich_text
            $table->json('title')->nullable();
            $table->json('content')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('subject');
            $table->json('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('cities')->nullable(); // list of city names (lowercase match)
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('free_over', 12, 2)->nullable();
            $table->unsignedSmallInteger('delivery_days')->default(2);
            $table->string('carrier')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('next_number')->default(1);
            $table->unique(['type', 'year']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['document_sequences', 'shipping_zones', 'holidays', 'notification_templates', 'home_sections', 'menu_items', 'pages', 'audit_logs', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
