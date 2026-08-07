<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('restrict');
            $table->string('name', 155);
            $table->string('slug', 155)->unique();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->boolean('weekend_mode')->default(false);
            $table->timestamps();

            $table->index(['owner_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
        });

        Schema::dropIfExists('restaurants');
    }
};
