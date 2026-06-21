<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_batch_id')->constrained('course_batches')->onDelete('cascade');
            $table->foreignId('course_session_id')->constrained('course_sessions')->onDelete('cascade');
            $table->foreignId('course_registration_id')->constrained('course_registrations')->onDelete('cascade');
            $table->foreignId('member_id')->nullable()->constrained('members')->onDelete('cascade');
            $table->date('attendance_date');
            $table->string('status'); // present, absent, late, excused
            $table->foreignId('marked_by')->constrained('users')->onDelete('cascade');
            $table->dateTime('marked_at');
            $table->string('remarks')->nullable();
            $table->timestamps();

            // Ensure unique record per session per registration
            $table->unique(['course_session_id', 'course_registration_id'], 'session_reg_attendance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_attendance');
    }
};
