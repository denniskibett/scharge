<?php
// database/migrations/2026_07_02_000002_add_columns_to_pending_mpesa_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('pending_mpesa_payments', function (Blueprint $table) {
            $table->string('checkout_request_id')->unique()->after('id');
            $table->unsignedBigInteger('invoice_id')->after('checkout_request_id');
            $table->unsignedBigInteger('tenant_id')->after('invoice_id');
            $table->decimal('amount', 10, 2)->after('tenant_id');
            $table->string('phone', 20)->after('amount');
            $table->string('status')->default('pending')->after('phone');
            
            // Add indexes
            $table->index('checkout_request_id');
            $table->index('invoice_id');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::table('pending_mpesa_payments', function (Blueprint $table) {
            $table->dropColumn([
                'checkout_request_id',
                'invoice_id',
                'tenant_id',
                'amount',
                'phone',
                'status'
            ]);
        });
    }
};