<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->text('link')->change();
            $table->text('source_url')->nullable()->change();
            $table->text('source_image_url')->nullable()->change();
            $table->text('selected_image_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('link')->change();
            $table->string('source_url')->nullable()->change();
            $table->string('source_image_url')->nullable()->change();
            $table->string('selected_image_url')->nullable()->change();
        });
    }
};
