<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed local development access accounts.
     *
     * NOTE: These are LOCAL DEVELOPMENT accounts only. The passwords are
     * intentionally simple and MUST NOT be used or described as production
     * credentials. They exist purely to exercise login/authorization during
     * development and testing.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@ebs.local'],
            [
                'name' => 'Dev Admin',
                'password' => 'admin-dev-password',
                'role' => User::ROLE_ADMIN,
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'customer@ebs.local'],
            [
                'name' => 'Dev Customer',
                'password' => 'customer-dev-password',
                'role' => User::ROLE_CUSTOMER,
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ]
        );
    }
}