<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('event_registration_id')->constrained('event_registrations')->onDelete('cascade');
            $table->foreignId('member_id')->nullable()->constrained('members')->onDelete('cascade');
            $table->date('attendance_date');
            $table->string('status'); // present, absent, checked_in, late, excused
            $table->foreignId('marked_by')->constrained('users')->onDelete('cascade');
            $table->dateTime('marked_at');
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['event_registration_id'], 'event_reg_attendance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendance');
    }
};
