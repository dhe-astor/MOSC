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
        Schema::create('certificate_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('church_id')->constrained('churches')->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('family_id')->nullable()->constrained('families')->onDelete('set null');
            $table->foreignId('member_id')->nullable()->constrained('members')->onDelete('set null');
            $table->foreignId('sacrament_id')->nullable()->constrained('sacraments')->onDelete('set null');
            $table->enum('certificate_type', ['membership', 'baptism', 'marriage', 'death', 'recommendation', 'no_objection', 'course_completion', 'custom']);
            $table->string('purpose');
            $table->jsonb('request_data')->nullable();
            $table->string('supporting_document_path')->nullable();
            $table->enum('status', ['submitted', 'parish_review', 'priest_review', 'diocese_review', 'approved', 'rejected', 'issued', 'cancelled'])->default('submitted');
            $table->foreignId('parish_reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('parish_reviewed_at')->nullable();
            $table->foreignId('priest_approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('priest_approved_at')->nullable();
            $table->foreignId('diocese_approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('diocese_approved_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->unsignedBigInteger('certificate_id')->nullable(); // Circular relationship reference
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('diocese_id');
            $table->index('church_id');
            $table->index('requested_by');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_requests');
    }
};
