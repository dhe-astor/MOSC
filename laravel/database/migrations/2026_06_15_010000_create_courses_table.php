<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('course_type'); // pre_marriage, post_marriage, syriac_language, bible_course, liturgical_course, altar_assistants, other
            $table->text('description')->nullable();
            $table->string('eligibility')->nullable();
            $table->decimal('default_fee_amount', 10, 2)->nullable();
            $table->string('currency')->default('EUR');
            $table->boolean('certificate_enabled')->default(false);
            $table->foreignId('certificate_template_id')->nullable()->constrained('certificate_templates')->onDelete('set null');
            $table->boolean('feedback_required')->default(false);
            $table->integer('attendance_required_percentage')->default(75);
            $table->string('status')->default('active'); // active, inactive, archived
            $table->boolean('show_on_portal')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
