<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_set_id')->constrained('ad_sets')->onDelete('cascade');
            $table->string('name');
            $table->string('external_id')->nullable();
            $table->string('headline')->nullable();
            $table->text('body')->nullable();
            $table->string('cta')->nullable();
            $table->string('image_url')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
