<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
            $table->json('name');
            $table->string('name_en', 100)->virtualAs('json_extract(name, "$.en")')->stored();
            $table->string('unit', 20);
            $table->decimal('stock_quantity', 10, 3)->default(0);
            $table->decimal('min_stock', 10, 3)->default(0);
            $table->timestamps();

            $table->unique(['restaurant_id', 'name_en']);
            $table->index(['restaurant_id', 'stock_quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
