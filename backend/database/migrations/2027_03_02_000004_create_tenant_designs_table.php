<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->unique()->constrained('restaurants')->onDelete('cascade');
            $table->string('primary_color', 7)->default('#FF5733');
            $table->string('secondary_color', 7)->default('#333333');
            $table->string('background_color', 7)->default('#FAFAFA');
            $table->string('font_family', 50)->default('Roboto');
            $table->enum('menu_layout', ['grid', 'list'])->default('list');
            $table->text('logo_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_designs');
    }
};
