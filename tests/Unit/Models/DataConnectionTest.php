<?php

namespace Tests\Unit\Models;

use App\Models\DataConnection;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DataConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_connection_belongs_to_merchant(): void
    {
        $merchant = Merchant::factory()->create();
        $connection = DataConnection::factory()->for($merchant)->create();

        $this->assertTrue($connection->merchant->is($merchant));
    }

    public function test_granted_scopes_cast_to_array(): void
    {
        $connection = DataConnection::factory()->create([
            'granted_scopes' => ['read_products', 'read_orders'],
        ]);

        $fresh = $connection->fresh();

        $this->assertIsArray($fresh->granted_scopes);
        $this->assertSame(['read_products', 'read_orders'], $fresh->granted_scopes);
    }

    public function test_connected_and_disconnected_at_cast_to_datetime(): void
    {
        $connection = DataConnection::factory()->create([
            'connected_at' => '2026-01-01 00:00:00',
            'disconnected_at' => '2026-02-01 00:00:00',
        ]);

        $fresh = $connection->fresh();

        $this->assertInstanceOf(Carbon::class, $fresh->connected_at);
        $this->assertInstanceOf(Carbon::class, $fresh->disconnected_at);
    }

    public function test_credentials_and_disconnected_at_are_nullable(): void
    {
        $connection = DataConnection::factory()->create([
            'credentials' => null,
            'disconnected_at' => null,
        ]);

        $fresh = $connection->fresh();

        $this->assertNull($fresh->credentials);
        $this->assertNull($fresh->disconnected_at);
    }

    public function test_factory_creates_valid_data_connection(): void
    {
        $connection = DataConnection::factory()->create();

        $this->assertDatabaseHas('data_connections', ['id' => $connection->id]);
    }
}
