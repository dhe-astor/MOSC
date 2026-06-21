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
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->string('name');
            $table->enum('certificate_type', ['membership', 'baptism', 'marriage', 'death', 'recommendation', 'no_objection', 'course_completion', 'custom']);
            $table->enum('language', ['en', 'ml', 'de'])->default('en');
            $table->longText('html_template');
            $table->string('background_image_path')->nullable();
            $table->boolean('seal_required')->default(true);
            $table->boolean('signature_required')->default(true);
            $table->string('default_priest_signature_position')->nullable();
            $table->string('default_seal_position')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Indexes
            $table->index('diocese_id');
            $table->index('certificate_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_templates');
    }
};
