<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('church_id')->nullable()->constrained('churches')->onDelete('cascade');
            $table->enum('reminder_type', ['event', 'course', 'sunday_school_exam', 'certificate', 'finance_approval', 'cms_approval', 'ministry_activity', 'custom']);
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('title');
            $table->text('body')->nullable();
            $table->dateTime('scheduled_at');
            $table->enum('channel', ['in_app', 'email', 'sms', 'whatsapp']);
            $table->enum('status', ['scheduled', 'processing', 'sent', 'failed', 'cancelled'])->default('scheduled');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->dateTime('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('diocese_id');
            $table->index('church_id');
            $table->index('status');
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reminders');
    }
};
