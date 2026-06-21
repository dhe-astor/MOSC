<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunday_school_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->string('level_name');
            $table->string('level_code');
            $table->integer('sort_order');
            $table->integer('minimum_age')->nullable();
            $table->integer('maximum_age')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active'); // active, inactive, archived
            $table->timestamps();

            $table->unique(['diocese_id', 'level_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunday_school_levels');
    }
};
