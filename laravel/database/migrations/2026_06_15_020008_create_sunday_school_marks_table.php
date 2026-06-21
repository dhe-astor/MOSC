<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunday_school_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('sunday_school_exams')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('sunday_school_students')->onDelete('cascade');
            $table->decimal('marks_obtained', 5, 2);
            $table->string('grade')->nullable();
            $table->string('result_status')->default('pending'); // pass, fail, absent, pending
            $table->string('remarks')->nullable();
            $table->foreignId('entered_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunday_school_marks');
    }
};
