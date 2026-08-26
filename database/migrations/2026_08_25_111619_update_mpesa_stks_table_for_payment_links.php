<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mpesa_stks', function (Blueprint $table) {
            // ✅ Fix 1: Change response_code to integer (0 = success, 1+ = failure)
            // First, convert existing data - set NULL for invalid values
            DB::statement("UPDATE mpesa_stks SET response_code = NULL WHERE response_code = '' OR response_code IS NULL");
            DB::statement("UPDATE mpesa_stks SET response_code = 0 WHERE response_code = '0'");
            DB::statement("UPDATE mpesa_stks SET response_code = 1 WHERE response_code = '1' OR response_code = 'failed'");
            
            // Change column type from varchar to integer
            $table->integer('response_code')->nullable()->change();

            // ✅ Fix 2: Add payment_id to link to payments table
            if (!Schema::hasColumn('mpesa_stks', 'payment_id')) {
                $table->foreignId('payment_id')
                    ->nullable()
                    ->after('mpesa_receipt_number')
                    ->constrained('payments')
                    ->nullOnDelete();
            }

            // ✅ Fix 3: Add status field to track STK lifecycle
            if (!Schema::hasColumn('mpesa_stks', 'status')) {
                $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])
                    ->default('pending')
                    ->after('response_code')
                    ->index();
            }

            // ✅ Fix 4: Add result_code from callback
            if (!Schema::hasColumn('mpesa_stks', 'result_code')) {
                $table->integer('result_code')
                    ->nullable()
                    ->after('response_code')
                    ->comment('ResultCode from Safaricom callback (0=success, 1+=failure)');
            }

            // ✅ Fix 5: Add indexes for better query performance
            $table->index(['payment_id'], 'idx_mpesa_stks_payment_id');
            $table->index(['checkout_request_id', 'status'], 'idx_mpesa_stks_checkout_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mpesa_stks', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex('idx_mpesa_stks_payment_id');
            $table->dropIndex('idx_mpesa_stks_checkout_status');
            
            // Drop columns
            $table->dropForeign(['payment_id']);
            $table->dropColumn('payment_id');
            $table->dropColumn('status');
            $table->dropColumn('result_code');
            
            // Revert response_code back to varchar
            $table->string('response_code', 255)->nullable()->change();
        });
    }
};