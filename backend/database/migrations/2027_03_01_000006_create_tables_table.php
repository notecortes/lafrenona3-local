<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
            $table->string('number', 20);
            $table->enum('status', ['free', 'occupied'])->default('free');
            $table->string('secret_token', 64);
            $table->string('current_session_token', 64)->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
            $table->unique(['restaurant_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
