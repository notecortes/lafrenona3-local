<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'cooking', 'ready', 'delivered', 'cancelled'])->default('pending');
            $table->enum('target_area', ['kitchen', 'bar']);
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();

            $table->index(['target_area', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
