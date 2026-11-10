<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Hosted company websites (docs/COMPANY_WEBSITE.md): pages a white-label company edits, scharge approves, shown on the
| company's own address; and agreements a company accepts (the data-processing agreement).
|   php artisan migrate --path=database/migrations/2026_11_11_000500_create_company_pages_and_agreements.php --force
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_pages')) {
            Schema::create('company_pages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('slug', 80)->default(''); // '' = home page
                $table->string('template', 20)->default('custom'); // home | about | rentals | contact | custom
                // What visitors see (copied from the draft when scharge approves).
                $table->string('title', 160)->nullable();
                $table->string('hero_title', 190)->nullable();
                $table->string('hero_text', 500)->nullable();
                $table->string('hero_image')->nullable();
                $table->text('body')->nullable(); // Markdown
                $table->json('draft')->nullable(); // changes waiting: same fields
                $table->string('status', 20)->default('draft'); // draft | pending | published | changes_pending
                $table->boolean('show_in_menu')->default(true);
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->string('review_note', 500)->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'slug']);
            });
        }
        if (! Schema::hasTable('company_agreements')) {
            Schema::create('company_agreements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('agreement', 40); // dpa
                $table->string('version', 20);
                $table->unsignedBigInteger('accepted_by');
                $table->string('accepted_name', 160);
                $table->string('accepted_title', 120)->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamp('accepted_at');
                $table->timestamps();
                $table->unique(['company_id', 'agreement', 'version']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_pages');
        Schema::dropIfExists('company_agreements');
    }
};
