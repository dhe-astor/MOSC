<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('priests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->nullable(); // Rev. Fr., etc.
            $table->string('full_name');
            $table->string('baptism_name')->nullable();
            $table->string('clergy_rank')->default('priest'); // metropolitan, priest, ramban, deacon, assistant_vicar
            $table->date('ordination_date')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('primary_phone');
            $table->string('whatsapp_phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('biography')->nullable();
            $table->string('status')->default('active'); // active, transferred, retired, inactive, deceased
            $table->boolean('show_on_website')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('diocese_id');
            $table->index('status');
            $table->index('email');
            $table->index('primary_phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('priests');
    }
};
