<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@activitymind.local'],
            [
                'name' => 'Администратор',
                'password' => 'password', // хэшируется через cast; сменить после первого входа
            ]
        );
        $admin->forceFill(['role' => 'admin'])->save();
    }
}
