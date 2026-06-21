<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ministry_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses');
            $table->foreignId('church_id')->nullable()->constrained('churches');
            $table->foreignId('ministry_unit_id')->constrained('ministry_units');
            $table->string('title');
            $table->string('activity_type'); // meeting, prayer, bible_study, charity, volunteer_work, retreat, conference, cultural, training, visit, other
            $table->text('description')->nullable();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime')->nullable();
            $table->string('timezone');
            $table->string('location_name')->nullable();
            $table->string('mode')->default('offline'); // online, offline, hybrid
            $table->string('meeting_link')->nullable();
            $table->string('status')->default('draft'); // draft, published, completed, cancelled, archived
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ministry_activities');
    }
};
