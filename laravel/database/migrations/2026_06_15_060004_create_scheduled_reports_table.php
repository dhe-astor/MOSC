<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('church_id')->nullable()->constrained('churches')->onDelete('cascade');
            $table->foreignId('report_definition_id')->constrained('report_definitions')->onDelete('cascade');
            $table->foreignId('saved_report_id')->nullable()->constrained('saved_reports')->onDelete('set null');
            $table->string('name');
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly']);
            $table->integer('scheduled_day')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->string('timezone')->default('Europe/Vienna');
            $table->json('recipients')->nullable();
            $table->enum('export_type', ['csv', 'pdf', 'xlsx'])->default('csv');
            $table->enum('status', ['active', 'paused', 'cancelled'])->default('active');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
    }
};
