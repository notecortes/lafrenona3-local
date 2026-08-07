<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
            $table->foreignId('table_id')->constrained('tables')->onDelete('restrict');
            $table->string('session_token', 64);
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->decimal('total_price', 10, 2)->default(0.00);
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
