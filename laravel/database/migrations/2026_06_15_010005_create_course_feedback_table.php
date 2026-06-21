<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_batch_id')->constrained('course_batches')->onDelete('cascade');
            $table->foreignId('course_registration_id')->constrained('course_registrations')->onDelete('cascade');
            $table->foreignId('member_id')->nullable()->constrained('members')->onDelete('set null');
            $table->integer('rating')->nullable();
            $table->text('feedback_text')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('submitted_at');
            $table->timestamps();

            $table->unique('course_registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_feedback');
    }
};
