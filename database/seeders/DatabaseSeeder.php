<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! User::where('email', 'admin@merchant-readiness.test')->exists()) {
            User::factory()->admin()->create([
                'name' => 'Admin User',
                'email' => 'admin@merchant-readiness.test',
            ]);
        }

        $this->call(DemoMerchantsSeeder::class);
    }
}
