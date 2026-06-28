<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('file_path');
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('canvas_width')->default(1080);
            $table->unsignedInteger('canvas_height')->default(1080);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->string('source_name')->default('')->after('link');
            $table->string('source_url')->nullable()->after('source_name');
            $table->json('gallery_images')->nullable()->after('source_url');
            $table->string('selected_image_url')->nullable()->after('gallery_images');
            $table->foreignId('image_template_id')->nullable()->after('selected_image_url')
                ->constrained('image_templates')->nullOnDelete();
            $table->text('original_content')->nullable()->after('original_title');

            $table->dropForeign(['feed_id']);
            $table->foreignId('feed_id')->nullable()->change();
            $table->foreign('feed_id')->references('id')->on('feeds')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['image_template_id']);
            $table->dropForeign(['feed_id']);
            $table->dropColumn([
                'source_name',
                'source_url',
                'gallery_images',
                'selected_image_url',
                'image_template_id',
                'original_content',
            ]);

            $table->foreignId('feed_id')->nullable(false)->change();
            $table->foreign('feed_id')->references('id')->on('feeds')->cascadeOnDelete();
        });

        Schema::dropIfExists('image_templates');
    }
};
