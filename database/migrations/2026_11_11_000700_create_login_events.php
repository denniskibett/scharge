<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Where people sign in from (docs/SIGN_IN_LOCATIONS.md): every sign-in and failed attempt on the website, the mobile
| app and the reader app, with the IP's approximate city and country. Kept 180 days.
|   php artisan migrate --path=database/migrations/2026_11_11_000700_create_login_events.php --force
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('login_events')) {
            return;
        }
        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('role', 40)->nullable();
            $table->boolean('success')->default(true);
            $table->string('channel', 20); // web | app | reader_app
            $table->string('method', 20)->nullable(); // password | otp | remember
            $table->string('ip', 45)->nullable();
            $table->string('country_code', 2)->nullable()->index();
            $table->string('country', 80)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('device', 20)->nullable(); // mobile | tablet | desktop
            $table->string('browser', 40)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
    }
};
