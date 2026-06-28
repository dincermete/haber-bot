<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('error_message');
            $table->timestamp('edited_at')->nullable()->after('approved_at');
        });

        DB::table('articles')
            ->whereIn('status', ['sent', 'auto_sent'])
            ->update([
                'status' => 'approved',
                'approved_at' => DB::raw('COALESCE(telegram_sent_at, updated_at)'),
            ]);

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('telegram_sent_at');
        });

        DB::table('settings')
            ->whereIn('key', ['telegram_token', 'telegram_chat_id', 'auto_send_enabled'])
            ->delete();
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->timestamp('telegram_sent_at')->nullable()->after('error_message');
        });

        DB::table('articles')
            ->where('status', 'approved')
            ->update([
                'status' => 'sent',
                'telegram_sent_at' => DB::raw('approved_at'),
            ]);

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'edited_at']);
        });
    }
};
