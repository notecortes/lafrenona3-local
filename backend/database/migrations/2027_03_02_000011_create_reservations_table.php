<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
            $table->string('customer_name', 100);
            $table->string('customer_email', 100);
            $table->string('customer_phone', 20);
            $table->integer('party_size');
            $table->date('reservation_date');
            $table->time('reservation_time');
            $table->enum('status', ['pending', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show'])->default('pending');
            $table->foreignId('table_id')->nullable()->constrained('tables')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'reservation_date', 'reservation_time'], 'res_date_idx');
            $table->index(['restaurant_id', 'status'], 'res_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
