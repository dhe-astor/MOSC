<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunday_school_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('sunday_school_students')->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained('sunday_school_academic_years')->onDelete('cascade');
            $table->foreignId('class_id')->constrained('sunday_school_classes')->onDelete('cascade');
            $table->foreignId('certificate_id')->constrained('certificates')->onDelete('cascade');
            $table->string('certificate_type'); // completion, participation, merit, promotion
            $table->foreignId('issued_by')->constrained('users')->onDelete('cascade');
            $table->dateTime('issued_at');
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id', 'certificate_type'], 'ss_student_year_cert_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunday_school_certificates');
    }
};
