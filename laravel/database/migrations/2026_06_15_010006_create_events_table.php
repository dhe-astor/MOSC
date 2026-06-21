<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('church_id')->nullable()->constrained('churches')->onDelete('cascade'); // Null = Diocesan level
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('event_type'); // qurbana, retreat, conference, youth_meeting, family_conference, sunday_school, charity, feast, meeting, course_related, other
            $table->text('description')->nullable();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->string('timezone')->default('Europe/Vienna');
            $table->string('location_name')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->onDelete('set null');
            $table->string('mode')->default('offline'); // online, offline, hybrid
            $table->string('meeting_link')->nullable();
            
            $table->boolean('registration_required')->default(false);
            $table->decimal('registration_fee', 10, 2)->nullable();
            $table->string('currency')->default('EUR');
            $table->integer('max_participants')->nullable();
            
            $table->string('poster_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->string('visibility')->default('public'); // public, members_only, admins_only
            $table->string('status')->default('draft'); // draft, published, registration_open, registration_closed, completed, cancelled, archived
            
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('approved_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
