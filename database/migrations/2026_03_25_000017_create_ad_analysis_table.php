<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_analysis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained('ads')->onDelete('cascade');
            $table->string('rating');
            $table->text('recommendations')->nullable();
            $table->text('ai_suggestions')->nullable();
            $table->timestamp('analyzed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_analysis');
    }
};
