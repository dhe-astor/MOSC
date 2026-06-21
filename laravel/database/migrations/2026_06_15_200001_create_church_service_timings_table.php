<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_service_timings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->onDelete('cascade');
            $table->string('service_name');
            $table->string('day_of_week')->nullable();
            $table->date('service_date')->nullable();
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('language')->nullable()->default('en');
            $table->string('frequency')->default('weekly'); // weekly, monthly, special, one_time
            $table->text('notes')->nullable();
            $table->boolean('is_public')->default(true);
            $table->string('status')->default('active'); // active, inactive
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_service_timings');
    }
};
