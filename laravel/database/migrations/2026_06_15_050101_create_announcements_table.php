<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('church_id')->nullable()->constrained('churches')->onDelete('cascade');
            $table->string('title');
            $table->longText('body');
            $table->enum('announcement_type', ['diocese', 'parish', 'course', 'event', 'sunday_school', 'ministry', 'finance', 'cms', 'emergency', 'general']);
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('visibility', ['internal', 'members', 'public'])->default('members');
            $table->enum('status', ['draft', 'scheduled', 'sent', 'cancelled', 'archived'])->default('draft');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index('diocese_id');
            $table->index('church_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
