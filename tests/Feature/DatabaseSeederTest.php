<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Merchant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_an_admin_user_and_a_sample_assessment(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::where('role', 'admin')->count());
        $this->assertSame(1, Merchant::count());
        $this->assertSame(1, Assessment::count());
    }

    public function test_database_seeder_is_safe_to_run_more_than_once(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::where('role', 'admin')->count());
        $this->assertSame(1, Merchant::count());
        $this->assertSame(1, Assessment::count());
    }
}
