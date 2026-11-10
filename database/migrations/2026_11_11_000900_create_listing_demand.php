<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Renter demand (docs/RENTER_DEMAND.md): what people look for on Open listings (kind, area, budget, how many listings
| matched), SMS alerts and viewing requests. Anonymous: no IP or phone, a daily-changing visitor code, the searcher's
| city from the offline IP database.
|   php artisan migrate --path=database/migrations/2026_11_11_000900_create_listing_demand.php --force
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('listing_searches')) {
            return;
        }
        Schema::create('listing_searches', function (Blueprint $table) {
            $table->id();
            $table->string('source', 10); // search | alert | viewing
            $table->string('kind', 40)->nullable(); // Homes to rent | Short stays
            $table->string('area', 120)->nullable()->index(); // null = all areas
            $table->unsignedInteger('max_price')->nullable();
            $table->unsignedInteger('results')->nullable(); // listings that matched (0 = demand nobody serves)
            $table->char('visitor', 16)->nullable();
            $table->string('city', 120)->nullable(); // where the searcher is (approximate)
            $table->string('country_code', 2)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_searches');
    }
};
