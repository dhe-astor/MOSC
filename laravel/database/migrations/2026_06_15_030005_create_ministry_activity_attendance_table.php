<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ministry_activity_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ministry_activity_id')->constrained('ministry_activities');
            $table->foreignId('ministry_membership_id')->nullable()->constrained('ministry_memberships');
            $table->foreignId('member_id')->nullable()->constrained('members');
            $table->string('status'); // present, absent, late, excused
            $table->foreignId('marked_by')->constrained('users');
            $table->timestamp('marked_at');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ministry_activity_attendance');
    }
};
