<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Short-stay bookings per unit, and each unit's channel calendars (Airbnb, Booking.com: iCal links) for channel sync.
| docs/BOOKINGS.md. Self-contained: php artisan migrate --path=database/migrations/2026_11_11_000200_create_bookings_and_unit_channels.php --force
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            Schema::create('bookings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('unit_id')->index();
                $table->string('source', 20)->default('direct'); // direct | airbnb | booking_com | vrbo | other
                $table->string('external_uid', 191)->nullable(); // the channel's event id, for imported bookings
                $table->string('guest_name', 120)->nullable();
                $table->string('guest_phone', 40)->nullable();
                $table->unsignedSmallInteger('guests')->nullable();
                $table->date('check_in');
                $table->date('check_out'); // the morning they leave: a booking covers the nights check_in .. check_out - 1
                $table->decimal('amount', 12, 2)->nullable();
                $table->string('status', 20)->default('confirmed'); // confirmed | cancelled
                $table->string('notes', 500)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['unit_id', 'source', 'external_uid']);
                $table->index(['unit_id', 'check_in', 'check_out']);
            });
        }

        if (! Schema::hasTable('unit_channels')) {
            Schema::create('unit_channels', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('unit_id');
                $table->string('channel', 20); // airbnb | booking_com | vrbo | other
                $table->text('import_url')->nullable(); // the channel's "export calendar" link (encrypted)
                $table->timestamp('last_synced_at')->nullable();
                $table->string('last_status', 20)->nullable(); // ok | failed
                $table->string('last_error', 255)->nullable();
                $table->timestamps();
                $table->unique(['unit_id', 'channel']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('unit_channels');
    }
};
