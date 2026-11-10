<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| White label: a company's own name, logo, colour and web address (docs/WHITE_LABEL.md).
|   php artisan migrate --path=database/migrations/2026_11_11_000300_create_company_brands.php --force
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_brands')) {
            return;
        }
        Schema::create('company_brands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->unique();
            $table->string('display_name', 120);
            $table->string('domain', 191)->nullable()->unique(); // e.g. portal.danaff.co.ke (set by scharge staff)
            $table->string('logo_path')->nullable();
            $table->string('logo_dark_path')->nullable();
            $table->string('icon_path')->nullable();
            $table->string('primary_color', 7)->default('#465FFF');
            $table->string('tagline', 160)->nullable();
            $table->string('support_phone', 40)->nullable();
            $table->string('support_email', 120)->nullable();
            $table->boolean('show_powered_by')->default(true); // "Powered by scharge" (scharge staff decide)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_brands');
    }
};
