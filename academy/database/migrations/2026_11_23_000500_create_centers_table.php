<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centers', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('slug')->unique();
            $table->json('city');
            $table->json('address')->nullable();
            $table->json('description')->nullable();
            $table->json('hours')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('map_url', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('center_course', function (Blueprint $table) {
            $table->foreignId('center_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->primary(['center_id', 'course_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('center_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->string('delivery_method', 20)->default('shipping')->after('status');
            $table->foreignId('center_id')->nullable()->after('delivery_method')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('center_id');
            $table->dropColumn('delivery_method');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('center_id'));
        Schema::dropIfExists('center_course');
        Schema::dropIfExists('centers');
    }
};
