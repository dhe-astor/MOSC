<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunday_school_class_teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('sunday_school_classes')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('sunday_school_teachers')->onDelete('cascade');
            $table->string('role')->default('primary'); // primary, assistant, substitute
            $table->date('assigned_from');
            $table->date('assigned_to')->nullable();
            $table->string('status')->default('active'); // active, ended
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunday_school_class_teacher_assignments');
    }
};
