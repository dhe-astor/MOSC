<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('priest_profiles', function (Blueprint $table) {
            $table->string('public_slug')->unique()->nullable();
            $table->text('public_bio')->nullable();
            $table->boolean('show_public_profile')->default(true);
            $table->boolean('show_public_phone')->default(false);
            $table->boolean('show_public_email')->default(false);
            $table->integer('public_sort_order')->default(0);
        });

        Schema::table('churches', function (Blueprint $table) {
            $table->string('public_slug')->unique()->nullable();
            $table->text('public_description')->nullable();
            $table->string('public_photo_path')->nullable();
            $table->boolean('show_public_page')->default(true);
            $table->boolean('show_service_times')->default(true);
            $table->boolean('show_map')->default(true);
            $table->integer('public_sort_order')->default(0);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('priest_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'public_slug',
                'public_bio',
                'show_public_profile',
                'show_public_phone',
                'show_public_email',
                'public_sort_order'
            ]);
        });

        Schema::table('churches', function (Blueprint $table) {
            $table->dropColumn([
                'public_slug',
                'public_description',
                'public_photo_path',
                'show_public_page',
                'show_service_times',
                'show_map',
                'public_sort_order',
                'seo_title',
                'seo_description'
            ]);
        });
    }
};
