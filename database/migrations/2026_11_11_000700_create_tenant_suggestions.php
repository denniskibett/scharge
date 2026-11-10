<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tenant suggestion box (scharge app): anonymous on purpose. Only the landlord company and the text are kept, never
| the tenant, unit or phone, so admins can read honest suggestions.
|   php artisan migrate --path=database/migrations/2026_11_11_000700_create_tenant_suggestions.php --force
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_suggestions')) {
            return;
        }
        Schema::create('tenant_suggestions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('topic', 30)->default('general');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->unsignedBigInteger('read_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_suggestions');
    }
};
