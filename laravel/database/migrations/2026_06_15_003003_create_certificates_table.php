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
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_request_id')->nullable()->constrained('certificate_requests')->onDelete('set null');
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('church_id')->constrained('churches')->onDelete('cascade');
            $table->foreignId('member_id')->nullable()->constrained('members')->onDelete('set null');
            $table->foreignId('family_id')->nullable()->constrained('families')->onDelete('set null');
            $table->foreignId('sacrament_id')->nullable()->constrained('sacraments')->onDelete('set null');
            $table->foreignId('certificate_template_id')->constrained('certificate_templates')->onDelete('cascade');
            $table->string('certificate_number')->unique();
            $table->string('certificate_type'); // e.g. membership, baptism, etc.
            $table->date('issued_date');
            $table->foreignId('issued_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('pdf_path');
            $table->string('verification_code')->unique();
            $table->boolean('public_verification_enabled')->default(true);
            $table->enum('status', ['active', 'cancelled', 'expired', 'replaced'])->default('active');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('diocese_id');
            $table->index('church_id');
            $table->index('verification_code');
            $table->index('status');
        });

        // Add foreign key constraint to requests
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->foreign('certificate_id')->references('id')->on('certificates')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->dropForeign(['certificate_id']);
        });

        Schema::dropIfExists('certificates');
    }
};
