<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunday_school_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('sunday_school_classes')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('sunday_school_students')->onDelete('cascade');
            $table->date('attendance_date');
            $table->string('status'); // present, absent, late, excused
            $table->foreignId('marked_by')->constrained('users')->onDelete('cascade');
            $table->dateTime('marked_at');
            $table->string('remarks')->nullable();
            $table->timestamps();

            // Prevent multiple attendance records for the same student on the same day in the same class
            $table->unique(['class_id', 'student_id', 'attendance_date'], 'ss_attendance_class_student_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunday_school_attendance');
    }
};
