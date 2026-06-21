<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunday_school_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('church_id')->nullable()->constrained('churches')->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained('sunday_school_academic_years')->onDelete('cascade');
            $table->foreignId('level_id')->constrained('sunday_school_levels')->onDelete('cascade');
            $table->string('class_name');
            $table->string('mode')->default('offline'); // online, offline, hybrid
            $table->string('meeting_link')->nullable();
            $table->string('recording_folder_link')->nullable();
            $table->string('class_day')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('timezone')->default('Europe/Vienna');
            $table->foreignId('primary_teacher_id')->nullable()->constrained('sunday_school_teachers')->onDelete('set null');
            $table->foreignId('assistant_teacher_id')->nullable()->constrained('sunday_school_teachers')->onDelete('set null');
            $table->integer('max_students')->nullable();
            $table->string('status')->default('active'); // draft, active, completed, cancelled, archived
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunday_school_classes');
    }
};
