<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ministry_service_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses');
            $table->foreignId('church_id')->nullable()->constrained('churches');
            $table->foreignId('ministry_unit_id')->constrained('ministry_units');
            $table->foreignId('member_id')->nullable()->constrained('members');
            $table->foreignId('activity_id')->nullable()->constrained('ministry_activities');
            $table->string('service_type'); // charity, volunteering, hospital_visit, home_visit, food_support, fundraising_support, event_support, other
            $table->date('service_date');
            $table->decimal('hours_count', 5, 2)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->string('status')->default('submitted'); // submitted, verified, rejected, archived
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ministry_service_logs');
    }
};
