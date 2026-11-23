<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Courses can be free (price 0) or paid; paid courses are sold through the store checkout. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedInteger('price_cents')->default(0)->after('requires_quiz_pass');
            $table->unsignedInteger('compare_price_cents')->nullable()->after('price_cents');
            $table->unsignedInteger('price_usd_cents')->nullable()->after('compare_price_cents');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->dropConstrainedForeignId('course_id'));
        Schema::table('courses', fn (Blueprint $t) => $t->dropColumn(['price_cents', 'compare_price_cents', 'price_usd_cents']));
    }
};
