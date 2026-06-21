<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->boolean('photo_publication_consent')->default(false);
            $table->timestamp('photo_publication_consent_at')->nullable();
            $table->string('photo_publication_consent_source')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'photo_publication_consent',
                'photo_publication_consent_at',
                'photo_publication_consent_source'
            ]);
        });
    }
};
