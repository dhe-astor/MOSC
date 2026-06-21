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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('church_id')->constrained('churches')->onDelete('cascade');
            $table->foreignId('family_id')->constrained('families')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('member_code')->nullable()->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('full_name');
            $table->string('baptism_name')->nullable();
            $table->enum('gender', ['male', 'female', 'other', 'prefer_not_to_say'])->default('prefer_not_to_say');
            $table->date('date_of_birth')->nullable();
            $table->enum('relationship_to_head', ['head', 'spouse', 'son', 'daughter', 'father', 'mother', 'brother', 'sister', 'relative', 'other']);
            $table->string('phone')->nullable();
            $table->string('whatsapp_phone')->nullable();
            $table->string('email')->nullable();
            $table->string('occupation')->nullable();
            $table->string('employer_or_school')->nullable();
            $table->boolean('student_status')->default(false);
            $table->enum('marital_status', ['single', 'married', 'widowed', 'divorced', 'separated', 'not_applicable'])->default('single');
            $table->boolean('address_same_as_family')->default(true);
            $table->jsonb('individual_address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->enum('membership_status', ['pending', 'active', 'inactive', 'transferred', 'deceased', 'suspended', 'archived', 'anonymized', 'restricted'])->default('pending');
            $table->boolean('gdpr_consent')->default(false);
            $table->boolean('communication_consent')->default(false);
            $table->boolean('show_in_directory')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('diocese_id');
            $table->index('church_id');
            $table->index('family_id');
            $table->index('membership_status');
            $table->index('date_of_birth');
            $table->index('email');
            $table->index('phone');
        });

        // Resolve circular ref by adding constraint back to families
        Schema::table('families', function (Blueprint $table) {
            $table->foreign('head_member_id')->references('id')->on('members')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop constraint from families first
        Schema::table('families', function (Blueprint $table) {
            $table->dropForeign(['head_member_id']);
        });

        Schema::dropIfExists('members');
    }
};
