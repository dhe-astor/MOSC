<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->nullable()->constrained('dioceses')->onDelete('cascade');
            $table->string('report_key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('report_category', [
                'diocese', 'parish', 'members', 'sacraments', 'certificates',
                'courses_events', 'sunday_school', 'ministries', 'finance',
                'cms', 'communications', 'portal', 'gdpr', 'audit', 'custom'
            ]);
            $table->json('default_filters')->nullable();
            $table->json('allowed_roles')->nullable();
            $table->json('required_permissions')->nullable();
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->boolean('is_system')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['diocese_id', 'report_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_definitions');
    }
};
