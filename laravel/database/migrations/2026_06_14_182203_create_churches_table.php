<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('churches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('country_id')->constrained('countries');
            $table->string('name');
            $table->string('short_name');
            $table->string('church_type'); // church, parish, congregation, service_centre, community
            $table->string('patron_saint')->nullable();
            $table->string('city');
            $table->string('state_region')->nullable();
            $table->string('country');
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('public_email')->nullable();
            $table->string('public_phone')->nullable();
            $table->string('website_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('google_map_url', 500)->nullable();
            $table->date('established_date')->nullable();
            $table->string('canonical_status')->default('active'); // active, inactive, upcoming, closed, draft, merged
            $table->string('membership_code_prefix')->nullable();
            $table->string('slug')->unique();
            $table->string('public_page_slug')->unique();
            $table->text('description')->nullable();
            $table->text('history')->nullable();
            $table->text('qurbana_timing')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('show_on_website')->default(true);

            $table->index('diocese_id');
            $table->index('country_id');
            $table->index('canonical_status');
            
            // Addendum fields
            $table->string('source_url')->nullable();
            $table->string('source_raw_name')->nullable();
            $table->timestamp('source_verified_at')->nullable();
            $table->text('source_notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Now link users default/active church to churches
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('default_church_id')->references('id')->on('churches')->nullOnDelete();
            $table->foreign('active_church_id')->references('id')->on('churches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_church_id']);
            $table->dropForeign(['active_church_id']);
        });

        Schema::dropIfExists('churches');
    }
};
