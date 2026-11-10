<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| scharge app: one row per signed-in phone (docs/MOBILE_APP.md). The token is stored as a SHA-256 hash; the app PIN
| as a bcrypt hash. Wrong PINs are counted here, so five in a row sign that phone out (server-enforced).
|   php artisan migrate --path=database/migrations/2026_11_11_000600_create_mobile_sessions.php --force
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobile_sessions')) {
            return;
        }
        Schema::create('mobile_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('device_name', 80);
            $table->string('platform', 20)->default('android');
            $table->char('token_hash', 64)->unique();
            $table->string('pin_hash')->nullable();
            $table->unsignedTinyInteger('pin_failures')->default(0);
            $table->string('signed_in_with', 10)->default('otp'); // otp | password
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_sessions');
    }
};
