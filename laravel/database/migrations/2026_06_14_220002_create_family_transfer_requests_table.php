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
        Schema::create('family_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->onDelete('cascade');
            $table->foreignId('from_church_id')->constrained('churches')->onDelete('cascade');
            $table->foreignId('to_church_id')->constrained('churches')->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('source_approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('source_approved_at')->nullable();
            $table->foreignId('diocese_approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('diocese_approved_at')->nullable();
            $table->foreignId('target_accepted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('target_accepted_at')->nullable();
            $table->enum('status', ['requested', 'source_approved', 'diocese_approved', 'target_accepted', 'completed', 'rejected', 'cancelled'])->default('requested');
            $table->text('reason')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('family_id');
            $table->index('from_church_id');
            $table->index('to_church_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_transfer_requests');
    }
};
