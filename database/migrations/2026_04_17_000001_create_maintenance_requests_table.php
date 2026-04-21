<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->string('request_number')->unique();
            $table->string('duration')->nullable(); // how long issue has been happening
            $table->string('name');
            $table->text('description');
            $table->enum('category', [
                'plumbing', 'electrical', 'hvac', 'appliance', 
                'structural', 'pest_control', 'cleaning', 'other'
            ])->default('other');
            $table->enum('priority', ['low', 'medium', 'high', 'emergency'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'pending_parts', 'completed', 'cancelled'])->default('open');
            $table->text('admin_notes')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->json('images')->nullable();
            $table->timestamps();
            
            $table->index(['unit_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index('request_number');
        });
    }

    public function down()
    {
        Schema::dropIfExists('maintenance');
    }
};