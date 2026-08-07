<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('category_id');
            $table->index(['restaurant_id', 'is_available']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('table_id');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index('order_id');
            $table->index('product_id');
            $table->index(['restaurant_id', 'order_id']);
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->index('provider_payment_id');
            $table->index('order_id');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->index('table_id');
            $table->index(['restaurant_id', 'status']);
        });

        Schema::table('fiscal_records', function (Blueprint $table) {
            $table->index('order_id');
        });

        Schema::table('offline_operations', function (Blueprint $table) {
            $table->index('type');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('user_id');
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::table('tables', function (Blueprint $table) {
            $table->unique('secret_token');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['restaurant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropIndex(['restaurant_id', 'is_available']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['table_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['restaurant_id', 'order_id']);
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex(['provider_payment_id']);
            $table->dropIndex(['order_id']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['table_id']);
            $table->dropIndex(['restaurant_id', 'status']);
        });

        Schema::table('fiscal_records', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
        });

        Schema::table('offline_operations', function (Blueprint $table) {
            $table->dropIndex(['type']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['subject_type', 'subject_id']);
        });

        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->dropIndex(['reference_type', 'reference_id']);
        });

        Schema::table('tables', function (Blueprint $table) {
            $table->dropUnique(['secret_token']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['restaurant_id', 'role']);
        });
    }
};
