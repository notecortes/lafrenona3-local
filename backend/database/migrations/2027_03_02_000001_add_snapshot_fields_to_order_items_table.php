<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('price_snapshot', 10, 2)->nullable()->after('unit_price');
            $table->decimal('tax_rate', 5, 2)->default(10.00)->after('price_snapshot');
            $table->decimal('discount_amount', 10, 2)->default(0.00)->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['price_snapshot', 'tax_rate', 'discount_amount']);
        });
    }
};
