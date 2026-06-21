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
        Schema::create('sacraments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('church_id')->constrained('churches')->onDelete('cascade');
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('family_id')->nullable()->constrained('families')->onDelete('set null');
            $table->enum('sacrament_type', ['baptism', 'holy_communion', 'confirmation', 'marriage', 'funeral', 'other']);
            $table->date('sacrament_date');
            $table->string('place');
            $table->foreignId('officiated_by_priest_id')->nullable()->constrained('priests')->onDelete('set null');
            $table->string('certificate_number')->nullable();
            $table->string('register_book_number')->nullable();
            $table->string('register_page_number')->nullable();
            $table->string('witness_1_name')->nullable();
            $table->string('witness_2_name')->nullable();
            $table->foreignId('spouse_member_id')->nullable()->constrained('members')->onDelete('set null');
            $table->string('spouse_name')->nullable();
            $table->text('remarks')->nullable();
            $table->string('document_path')->nullable();
            $table->enum('status', ['draft', 'submitted', 'verified', 'approved', 'rejected', 'archived'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('diocese_id');
            $table->index('church_id');
            $table->index('member_id');
            $table->index('sacrament_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sacraments');
    }
};
