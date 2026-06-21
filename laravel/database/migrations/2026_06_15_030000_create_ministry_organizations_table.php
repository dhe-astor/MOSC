<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ministry_organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diocese_id')->constrained('dioceses');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('organization_type'); // enum: youth_association, marthamariyam_samajam, other
            $table->text('description')->nullable();
            $table->json('eligibility_rules')->nullable(); // jsonb
            $table->string('status')->default('active'); // active, inactive, archived
            $table->boolean('show_on_portal')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ministry_organizations');
    }
};
