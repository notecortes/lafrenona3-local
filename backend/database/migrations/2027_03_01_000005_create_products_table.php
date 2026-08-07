<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('restrict');
            $table->json('name');
            $table->json('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('weekend_price', 10, 2)->nullable();
            $table->text('image_url')->nullable();
            $table->enum('stock_status', ['available', 'out_of_stock'])->default('available');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_vegan')->default(false);
            $table->boolean('is_vegetarian')->default(false);
            $table->json('allergens')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
