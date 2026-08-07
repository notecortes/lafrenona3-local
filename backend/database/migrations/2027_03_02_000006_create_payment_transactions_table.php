<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
            $table->foreignId('order_id')->constrained('orders')->onDelete('restrict');
            $table->string('provider', 20);
            $table->string('provider_payment_id', 255)->nullable();
            $table->string('webhook_event_id', 255)->nullable()->unique();
            $table->string('idempotency_key', 64)->unique();
            $table->bigInteger('amount_cents');
            $table->bigInteger('tip_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->enum('status', ['pending', 'confirmed', 'failed', 'cancelled', 'refunded'])->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->json('metadata_reference')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'order_id']);
            $table->index(['restaurant_id', 'status']);
            $table->index(['restaurant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
