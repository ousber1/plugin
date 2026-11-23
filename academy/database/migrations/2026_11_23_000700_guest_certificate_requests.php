<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Visitors without an account may request a certificate on WhatsApp. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Guest requests would violate NOT NULL; keep the column nullable.
    }
};
