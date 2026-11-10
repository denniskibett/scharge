<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| App data that matters (docs/APP_DATA.md): push addresses and app/Android versions per phone, every Pay press and its
| M-Pesa outcome, GPS proof on meter readings and repair visits, and app crash reports.
|   php artisan migrate --path=database/migrations/2026_11_11_000800_app_push_payments_proof_errors.php --force
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobile_sessions')) {
            Schema::table('mobile_sessions', function (Blueprint $table) {
                foreach (['push_token' => fn () => $table->string('push_token', 255)->nullable(), 'push_token_at' => fn () => $table->timestamp('push_token_at')->nullable(),
                    'app_version' => fn () => $table->string('app_version', 20)->nullable(), 'os_version' => fn () => $table->string('os_version', 40)->nullable(),
                    'device_model' => fn () => $table->string('device_model', 80)->nullable()] as $col => $add) {
                    if (! Schema::hasColumn('mobile_sessions', $col)) {
                        $add();
                    }
                }
            });
        }
        if (Schema::hasTable('meter_readings')) {
            Schema::table('meter_readings', function (Blueprint $table) {
                foreach (['lat' => fn () => $table->decimal('lat', 10, 7)->nullable(), 'lng' => fn () => $table->decimal('lng', 10, 7)->nullable(),
                    'accuracy_m' => fn () => $table->unsignedInteger('accuracy_m')->nullable(), 'captured_at' => fn () => $table->timestamp('captured_at')->nullable()] as $col => $add) {
                    if (! Schema::hasColumn('meter_readings', $col)) {
                        $add();
                    }
                }
            });
        }
        if (! Schema::hasTable('payment_attempts')) {
            Schema::create('payment_attempts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('invoice_id')->index();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('channel', 10)->default('web'); // web | app
                $table->string('method', 10)->nullable(); // stk | sharet
                $table->decimal('amount', 12, 2);
                $table->string('status', 20)->index(); // started | prompt_sent | redirected | paid | cancelled | wrong_pin | no_funds | timeout | failed
                $table->string('result_code', 10)->nullable();
                $table->string('reason', 255)->nullable();
                $table->string('checkout_request_id', 80)->nullable()->index();
                $table->unsignedBigInteger('sharet_request_id')->nullable()->index();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('maintenance_visits')) {
            Schema::create('maintenance_visits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('maintenance_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('user_id');
                $table->string('kind', 10); // checkin | checkout
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->unsignedInteger('accuracy_m')->nullable();
                $table->string('note', 255)->nullable();
                $table->timestamp('at')->nullable(); // set by the app; nullable so MariaDB adds no ON UPDATE default
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('app_errors')) {
            Schema::create('app_errors', function (Blueprint $table) {
                $table->id();
                $table->char('fingerprint', 40)->unique();
                $table->string('message', 500);
                $table->text('stack')->nullable();
                $table->string('screen', 80)->nullable();
                $table->string('app_version', 20)->nullable();
                $table->string('os_version', 40)->nullable();
                $table->string('device_model', 80)->nullable();
                $table->unsignedBigInteger('last_user_id')->nullable();
                $table->unsignedInteger('count')->default(1);
                $table->unsignedInteger('users')->default(1);
                $table->timestamp('first_seen_at')->nullable(); // nullable: MariaDB refuses a second NOT NULL timestamp without a default
                $table->timestamp('last_seen_at')->nullable()->index();
                $table->boolean('resolved')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['payment_attempts', 'maintenance_visits', 'app_errors'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
