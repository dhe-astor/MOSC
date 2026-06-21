<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('certificate_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->string('certificate_type');
            $table->integer('year');
            $table->integer('last_number')->default(0);
            $table->timestamps();

            // Unique index across diocese, type, and year
            $table->unique(['diocese_id', 'certificate_type', 'year'], 'cert_seq_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_sequences');
    }
};
