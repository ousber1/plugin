<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Physical certificate requests handled through WhatsApp.
        Schema::create('certificate_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('pending_contact')->index();
            // Private: collected by staff in WhatsApp, never shown publicly.
            $table->string('printed_name')->nullable();
            $table->text('shipping_address')->nullable();
            $table->text('private_notes')->nullable();
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->boolean('show_tracking')->default(true);
            $table->date('shipped_on')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            // One request per student and course (repeat clicks reuse it).
            $table->unique(['user_id', 'course_id']);
        });

        // Registered physical certificates for public verification.
        Schema::create('issued_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('certificate_request_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('holder_name');
            $table->string('course_title');
            $table->date('issued_on');
            $table->string('public_note')->nullable();
            $table->string('status', 20)->default('valid')->index(); // valid | revoked
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable(); // internal only
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('slug')->unique();
            $table->longText('title');
            $table->longText('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->longText('seo_title')->nullable();
            $table->longText('seo_description')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->longText('title');
            $table->longText('description')->nullable();
            $table->string('access', 20)->default('public'); // public | enrolled
            $table->string('disk', 30);
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('downloads')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->longText('title');
            $table->longText('body')->nullable();
            $table->string('status', 20)->default('published');
            $table->boolean('show_in_footer')->default(false);
            $table->longText('seo_title')->nullable();
            $table->longText('seo_description')->nullable();
            $table->timestamps();
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role')->nullable();
            $table->longText('body');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->longText('name');
            $table->longText('description')->nullable();
            $table->string('badge', 30)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tool_suggestions', function (Blueprint $table) {
            $table->id();
            $table->text('idea');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | done
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        // Optional digital store (disabled by default, independent of learning).
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->longText('title');
            $table->longText('description')->nullable();
            $table->unsignedInteger('price_cents');
            $table->string('cover_path')->nullable();
            $table->string('disk', 30)->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('MAD');
            $table->string('status', 20)->default('pending'); // pending | paid | cancelled
            $table->string('transfer_reference')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'orders', 'products', 'tool_suggestions', 'tools', 'contact_messages', 'partners',
            'testimonials', 'pages', 'resources', 'posts', 'issued_certificates', 'certificate_requests'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
