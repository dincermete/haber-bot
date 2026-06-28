<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feeds', function (Blueprint $table) {
            $table->id();
            $table->string('url')->unique();
            $table->string('title')->default('');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('filters', function (Blueprint $table) {
            $table->id();
            $table->string('keyword');
            $table->string('list_type'); // whitelist | blacklist
            $table->string('logic_mode'); // and | or
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_id')->constrained()->cascadeOnDelete();
            $table->string('article_uid')->unique();
            $table->string('original_title')->default('');
            $table->string('title')->default('');
            $table->text('summary')->nullable();
            $table->string('link')->default('');
            $table->string('source_image_url')->nullable();
            $table->string('generated_image_path')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('telegram_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('send_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level')->default('info');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('send_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('filters');
        Schema::dropIfExists('feeds');
    }
};
