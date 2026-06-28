<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@haberbot.local'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ]
        );

        foreach (Setting::defaults() as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }

        File::ensureDirectoryExists(storage_path('app/fonts'));
        File::ensureDirectoryExists(storage_path('app/public/articles/generated'));
        File::ensureDirectoryExists(storage_path('app/public/articles/uploads'));
        File::ensureDirectoryExists(storage_path('app/tmp/articles'));

        $this->call(ImageTemplateSeeder::class);
    }
}
