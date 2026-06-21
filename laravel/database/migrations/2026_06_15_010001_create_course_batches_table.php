<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('church_id')->nullable()->constrained('churches')->onDelete('cascade'); // Nullable = Diocesan level
            $table->string('batch_name');
            $table->string('batch_code')->unique()->nullable();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->string('timezone')->default('Europe/Vienna');
            $table->string('mode'); // online, offline, hybrid
            $table->string('venue')->nullable();
            $table->string('meeting_link')->nullable();
            $table->dateTime('registration_open_at')->nullable();
            $table->dateTime('registration_close_at')->nullable();
            $table->integer('max_participants')->nullable();
            $table->decimal('fee_amount', 10, 2)->nullable();
            $table->string('currency')->default('EUR');
            $table->boolean('certificate_enabled')->default(false);
            $table->foreignId('certificate_template_id')->nullable()->constrained('certificate_templates')->onDelete('set null');
            $table->string('status')->default('draft'); // draft, open, closed, ongoing, completed, cancelled, archived
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_batches');
    }
};
