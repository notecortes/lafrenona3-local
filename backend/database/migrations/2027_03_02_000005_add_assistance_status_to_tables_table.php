<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->enum('assistance_status', ['none', 'waiter_called', 'bill_requested'])->default('none')->after('status');
            $table->timestamp('assistance_requested_at')->nullable()->after('assistance_status');
        });
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn(['assistance_status', 'assistance_requested_at']);
        });
    }
};
