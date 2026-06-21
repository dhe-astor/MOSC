<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_gallery_id')->constrained('media_galleries')->onDelete('cascade');
            $table->string('title')->nullable();
            $table->text('caption')->nullable();
            $table->string('media_type')->default('image'); // image, video
            $table->string('media_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('external_video_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('alt_text')->nullable();
            $table->string('status')->default('active'); // active, hidden, archived
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_items');
    }
};
