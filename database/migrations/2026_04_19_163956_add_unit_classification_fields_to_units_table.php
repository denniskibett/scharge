<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('units', function (Blueprint $table) {
            // Property classification fields
            $table->enum('ownership_type', ['homeowner', 'tenant', 'company'])
                  ->default('tenant')
                  ->after('status')
                  ->comment('Who owns/occupies the property');
            
            $table->enum('furnishing_status', ['furnished', 'unfurnished', 'semi_furnished'])
                  ->default('unfurnished')
                  ->after('ownership_type');
            
            $table->enum('stay_type', ['long_stay', 'short_stay', 'bnb', 'mixed'])
                  ->default('long_stay')
                  ->after('furnishing_status');
            
            $table->enum('property_category', [
                'residential', 'commercial', 'showhouse', 'office', 'retail', 'industrial'
            ])->default('residential')->after('stay_type');
            
            $table->boolean('is_active')->default(true)->after('property_category');
            
            // Stay duration constraints
            $table->integer('min_stay_days')->nullable()->after('is_active');
            $table->integer('max_stay_days')->nullable()->after('min_stay_days');
            
            // BNB specific fields
            $table->decimal('bnb_cleaning_fee', 12, 2)->default(0)->after('max_stay_days');
            $table->decimal('bnb_nightly_rate', 12, 2)->nullable()->after('bnb_cleaning_fee');
            
            // Financial fields
            $table->decimal('security_deposit_amount', 12, 2)->nullable()->after('bnb_nightly_rate');
            $table->decimal('commission_rate', 5, 2)->nullable()->after('security_deposit_amount');
            
            // Indexes for performance
            $table->index('ownership_type');
            $table->index('stay_type');
            $table->index('property_category');
            $table->index('is_active');
        });
    }

    public function down()
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn([
                'ownership_type',
                'furnishing_status',
                'stay_type',
                'property_category',
                'is_active',
                'min_stay_days',
                'max_stay_days',
                'bnb_cleaning_fee',
                'bnb_nightly_rate',
                'security_deposit_amount',
                'commission_rate'
            ]);
        });
    }
};