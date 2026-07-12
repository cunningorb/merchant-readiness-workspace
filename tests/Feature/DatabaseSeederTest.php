<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentBenchmarkComparison;
use App\Models\Merchant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_an_admin_user_and_demo_merchants(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::where('role', 'admin')->count());
        $this->assertSame(3, Merchant::where('is_demo', true)->count());
        $this->assertSame(3, Assessment::where('status', 'submitted')->count());
        $this->assertGreaterThan(0, AssessmentBenchmarkComparison::count());
    }

    public function test_database_seeder_is_safe_to_run_more_than_once(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::where('role', 'admin')->count());
        $this->assertSame(3, Merchant::where('is_demo', true)->count());
        $this->assertSame(3, Assessment::where('status', 'submitted')->count());
    }
}
