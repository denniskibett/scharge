<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Visitors table - stores all people who visit (deduplicated)
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('id_number')->nullable(); // National ID/Passport
            $table->string('id_type')->nullable(); // National ID, Passport, Driver's License
            
            // Classification
            $table->enum('visitor_type', [
                'family', 'employee', 'contractor', 'regular_guest', 
                'delivery', 'maintenance', 'one_time'
            ])->default('one_time');
            $table->string('relationship')->nullable(); // For family/employee: "Brother", "Housekeeper", "Driver"
            $table->string('company')->nullable(); // Company name if contractor/employee
            
            // Vehicle information (JSON for multiple vehicles)
            $table->json('vehicles')->nullable(); // [{registration, make, color, is_primary}]
            
            // Registered vs One-time
            $table->boolean('is_registered')->default(false);
            $table->foreignId('registered_by_tenant_id')->nullable()->constrained('tenants')->onDelete('set null');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->json('access_schedule')->nullable(); // For recurring access: {days: [mon,tue], times: [09:00-17:00]}
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_blacklisted')->default(false);
            $table->text('blacklist_reason')->nullable();
            
            // Statistics
            $table->integer('visit_count')->default(0);
            $table->timestamp('last_visit_at')->nullable();
            
            // Metadata
            $table->string('photo_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('phone');
            $table->index('id_number');
            $table->index('visitor_type');
            $table->index('is_registered');
            $table->index('is_blacklisted');
            $table->index('registered_by_tenant_id');
            $table->index(['valid_from', 'valid_until']);
        });
        
        // Security/Access logs table (lean - only references visitor_id)
        Schema::create('security', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('visitor_id')->constrained()->onDelete('cascade');
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Snapshot of key visitor info at time of entry (for historical accuracy)
            $table->string('visitor_name_snapshot')->nullable();
            $table->string('visitor_phone_snapshot')->nullable();
            $table->string('visitor_id_number_snapshot')->nullable();
            $table->string('visitor_company_snapshot')->nullable();
            $table->string('vehicle_registration_snapshot')->nullable();
            
            // Access details
            $table->enum('access_type', [
                'entry', 'exit', 'delivery', 'guest', 'maintenance', 
                'emergency', 'contractor', 'moving', 'inspection'
            ]);
            $table->enum('status', ['pending', 'approved', 'denied', 'completed', 'expired'])->default('pending');
            $table->timestamp('access_time');
            $table->timestamp('exit_time')->nullable();
            $table->integer('duration_hours')->nullable();
            
            // Additional info
            $table->string('purpose')->nullable();
            $table->text('notes')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->json('images')->nullable();
            
            // Tracking
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            // Indexes for fast queries
            $table->index(['unit_id', 'access_time']);
            $table->index(['tenant_id', 'status']);
            $table->index(['visitor_id', 'access_time']);
            $table->index(['access_type', 'status']);
            $table->index('access_time');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('security');
        Schema::dropIfExists('visitors');
    }
};