<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('priest_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('priest_id')->constrained('priests')->onDelete('cascade');
            $table->foreignId('church_id')->constrained('churches')->onDelete('cascade');
            $table->string('role')->default('vicar'); // vicar, assistant_vicar, in_charge, visiting_priest
            $table->date('assignment_start_date');
            $table->date('assignment_end_date')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('remarks')->nullable();
            $table->string('status')->default('active'); // active, ended, temporary, cancelled
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('priest_id');
            $table->index('church_id');
            $table->index('status');
            $table->index('is_primary');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            \Illuminate\Support\Facades\DB::statement("CREATE UNIQUE INDEX unique_active_primary_vicar_per_church ON priest_assignments (church_id) WHERE is_primary = 1 AND status = 'active' AND deleted_at IS NULL;");
        } else {
            \Illuminate\Support\Facades\DB::statement("CREATE UNIQUE INDEX unique_active_primary_vicar_per_church ON priest_assignments (church_id) WHERE is_primary = true AND status = 'active' AND deleted_at IS NULL;");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('priest_assignments');
    }
};
