<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('church_id')->constrained('churches')->onDelete('cascade');
            $table->string('family_code')->nullable()->unique();
            $table->string('family_name');
            $table->unsignedBigInteger('head_member_id')->nullable(); // Circular ref: will not add constrained() to avoid migration blocker
            $table->string('primary_phone');
            $table->string('whatsapp_phone')->nullable();
            $table->string('primary_email')->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('state_region')->nullable();
            $table->string('postal_code')->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->onDelete('set null');
            $table->enum('preferred_language', ['en', 'ml', 'de'])->default('en');
            $table->enum('membership_status', ['pending', 'active', 'inactive', 'transferred', 'suspended', 'archived', 'anonymized', 'restricted'])->default('pending');
            $table->date('registered_date')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->boolean('gdpr_consent')->default(false);
            $table->timestamp('gdpr_consent_at')->nullable();
            $table->boolean('communication_consent')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('diocese_id');
            $table->index('church_id');
            $table->index('membership_status');
            $table->index('primary_phone');
            $table->index('primary_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('families');
    }
};
