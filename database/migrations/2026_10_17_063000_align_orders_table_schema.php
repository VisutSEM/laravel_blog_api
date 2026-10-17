<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. If running on Postgres, remove NOT NULL constraint on legacy single-item columns
        if (DB::getDriverName() === 'pgsql') {
            if (Schema::hasColumn('orders', 'product_id')) {
                DB::statement('ALTER TABLE orders ALTER COLUMN product_id DROP NOT NULL;');
            }
            if (Schema::hasColumn('orders', 'quantity')) {
                DB::statement('ALTER TABLE orders ALTER COLUMN quantity DROP NOT NULL;');
            }
            if (Schema::hasColumn('orders', 'total_price')) {
                DB::statement('ALTER TABLE orders ALTER COLUMN total_price DROP NOT NULL;');
            }
        }

        // 2. Add missing order tracking, totals, payment, and shipping columns
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'order_number')) {
                $table->string('order_number')->nullable()->unique();
            }
            if (!Schema::hasColumn('orders', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->default(0.00);
            }
            if (!Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status')->default('unpaid');
            }
            if (!Schema::hasColumn('orders', 'payment_method')) {
                $table->string('payment_method')->default('cash_on_delivery');
            }
            if (!Schema::hasColumn('orders', 'shipping_address')) {
                $table->text('shipping_address')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'order_number')) {
                $table->dropColumn('order_number');
            }
            if (Schema::hasColumn('orders', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
            if (Schema::hasColumn('orders', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
            if (Schema::hasColumn('orders', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
            if (Schema::hasColumn('orders', 'shipping_address')) {
                $table->dropColumn('shipping_address');
            }
        });
    }
};
