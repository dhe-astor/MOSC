<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunday_school_progress_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('sunday_school_students')->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained('sunday_school_academic_years')->onDelete('cascade');
            $table->foreignId('class_id')->constrained('sunday_school_classes')->onDelete('cascade');
            $table->decimal('attendance_percentage', 5, 2);
            $table->decimal('total_marks', 6, 2)->nullable();
            $table->string('grade')->nullable();
            $table->string('promotion_status')->default('pending'); // pending, promoted, retained, completed, discontinued
            $table->text('teacher_remarks')->nullable();
            $table->foreignId('generated_by')->constrained('users')->onDelete('cascade');
            $table->dateTime('generated_at');
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunday_school_progress_reports');
    }
};
