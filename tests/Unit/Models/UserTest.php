<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_defaults_to_the_staff_role(): void
    {
        $user = User::factory()->create();

        $this->assertSame('staff', $user->role);
    }

    public function test_admin_factory_state_sets_the_admin_role(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertSame('admin', $user->role);
    }

    public function test_staff_factory_state_explicitly_sets_the_staff_role(): void
    {
        $user = User::factory()->staff()->create();

        $this->assertSame('staff', $user->role);
    }
}
