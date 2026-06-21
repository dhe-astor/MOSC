<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->onDelete('cascade');
            $table->enum('target_type', ['all_members', 'church', 'role', 'family', 'member', 'course_batch', 'event', 'sunday_school_class', 'ministry_unit', 'custom']);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('filters')->nullable();
            $table->timestamps();

            $table->index('announcement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_targets');
    }
};
