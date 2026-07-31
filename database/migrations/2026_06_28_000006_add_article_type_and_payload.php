<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('type')->default('news')->after('id');
            $table->json('payload')->nullable()->after('summary');
            $table->timestamp('data_fetched_at')->nullable()->after('payload');
            $table->json('last_successful_payload')->nullable()->after('data_fetched_at');
            $table->foreignId('created_by_user_id')->nullable()->after('feed_id')
                ->constrained('users')->nullOnDelete();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropIndex(['type', 'status']);
            $table->dropColumn([
                'type',
                'payload',
                'data_fetched_at',
                'last_successful_payload',
                'created_by_user_id',
            ]);
        });
    }
};
