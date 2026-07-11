<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

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

        if (! Merchant::query()->exists()) {
            $merchant = Merchant::factory()->create();
            Assessment::factory()->for($merchant)->create();
        }
    }
}
