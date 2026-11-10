<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Paybill payments that tenants make straight to a company's paybill (Safaricom C2B confirmations), and reminder pauses
| per tenancy. docs/RENT_AUTOMATION.md. Self-contained: run it alone with
|   php artisan migrate --path=database/migrations/2026_11_11_000100_create_paybill_payments_and_rent_holds.php --force
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('paybill_payments')) {
            Schema::create('paybill_payments', function (Blueprint $table) {
                $table->id();
                $table->string('trans_id', 40)->unique(); // M-Pesa receipt, e.g. SKL2ABC123
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('shortcode', 20)->nullable();
                $table->string('bill_ref', 60)->nullable(); // the account number the tenant typed
                $table->decimal('amount', 12, 2);
                $table->string('msisdn', 80)->nullable(); // Safaricom may send it hashed
                $table->string('payer_name', 120)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->string('status', 20)->index(); // applied | credited | unmatched | ignored
                $table->unsignedBigInteger('tenancy_id')->nullable()->index();
                $table->string('matched_by', 20)->nullable(); // account | invoice | phone | manual
                $table->json('payment_ids')->nullable();
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('handled_by')->nullable();
                $table->json('raw')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rent_holds')) {
            Schema::create('rent_holds', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('tenancy_id')->unique();
                $table->date('until')->nullable(); // null = until switched back on
                $table->string('reason', 255)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paybill_payments');
        Schema::dropIfExists('rent_holds');
    }
};
