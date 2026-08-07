<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->integer('sequence_number');
            $table->string('hash', 64);
            $table->string('prev_hash', 64);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->enum('status', ['issued', 'corrected', 'cancelled'])->default('issued');
            $table->timestamps();

            $table->unique(['restaurant_id', 'sequence_number']);
            $table->index(['restaurant_id', 'prev_hash']);
            $table->index(['restaurant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_records');
    }
};
