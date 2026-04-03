<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained('ads')->onDelete('cascade');
            $table->date('date');
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->decimal('cost', 12, 2)->default(0);
            $table->integer('conversions')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->decimal('cpc', 12, 2)->nullable();
            $table->decimal('cpa', 12, 2)->nullable();
            $table->decimal('ctr', 12, 2)->nullable();
            $table->decimal('roi', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['ad_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_metrics');
    }
};
