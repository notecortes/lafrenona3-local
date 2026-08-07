<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('restaurant_id')->nullable()->after('owner_id')->constrained('restaurants')->onDelete('cascade');
            $table->index(['restaurant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
            $table->dropIndex(['restaurant_id', 'status']);
            $table->dropColumn('restaurant_id');
        });
    }
};
