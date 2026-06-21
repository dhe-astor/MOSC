<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunday_school_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('church_id')->nullable()->constrained('churches')->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained('sunday_school_academic_years')->onDelete('cascade');
            $table->foreignId('class_id')->nullable()->constrained('sunday_school_classes')->onDelete('cascade');
            $table->foreignId('level_id')->nullable()->constrained('sunday_school_levels')->onDelete('cascade');
            $table->string('exam_name');
            $table->string('exam_type'); // weekly_test, midterm, final, oral, written, assignment, project, other
            $table->date('exam_date');
            $table->integer('max_marks');
            $table->integer('pass_marks')->nullable();
            $table->string('status')->default('draft'); // draft, published, completed, archived
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunday_school_exams');
    }
};
