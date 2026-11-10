<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| KYC for landlord companies and property owners (docs/ONBOARDING.md): one review record per company, and the documents
| behind it (ID, KRA PIN, business registration, title deeds), each read automatically when document reading is on.
|   php artisan migrate --path=database/migrations/2026_11_11_000400_create_kyc_tables.php --force
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_kyc')) {
            Schema::create('company_kyc', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->unique();
                $table->string('status', 20)->default('draft'); // draft | submitted | verified | rejected
                $table->string('owner_name', 160)->nullable();
                $table->string('owner_id_number', 40)->nullable();
                $table->string('kra_pin', 20)->nullable();
                $table->string('business_name', 190)->nullable();
                $table->string('registration_number', 60)->nullable();
                $table->json('checks')->nullable(); // [{level, message}]
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->string('review_note', 500)->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('kyc_documents')) {
            Schema::create('kyc_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('property_id')->nullable(); // title deeds
                $table->string('kind', 30); // national_id | passport | kra_pin | business_registration | cr12 | title_deed | other
                $table->string('path'); // private disk
                $table->string('original_name', 190)->nullable();
                $table->string('mime', 60);
                $table->unsignedInteger('size');
                $table->string('read_status', 20)->default('pending'); // pending | read | failed | off
                $table->json('extracted')->nullable();
                $table->string('read_error', 255)->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
        Schema::dropIfExists('company_kyc');
    }
};
