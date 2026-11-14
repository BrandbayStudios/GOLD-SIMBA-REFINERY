<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@handynow.co.zm');
        if (User::where('email', $email)->exists()) {
            return;
        }
        $password = env('ADMIN_PASSWORD');
        if (! $password) {
            if (app()->isProduction()) {
                $this->command?->error('Set ADMIN_PASSWORD in .env before seeding the administrator in production.');

                return;
            }
            $password = 'Admin@12345';
        }
        User::create([
            'name' => 'HANDY NOW Admin',
            'email' => $email,
            'phone' => zm_phone(env('ADMIN_PHONE', '0970000001')),
            'password' => $password,
            'role' => User::ROLE_ADMIN,
        ]);
        $this->command?->info("Administrator created: {$email}");
    }
}
