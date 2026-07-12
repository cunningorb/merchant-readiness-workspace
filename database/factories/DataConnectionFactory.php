<?php

namespace Database\Factories;

use App\Models\DataConnection;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataConnection>
 */
class DataConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'provider' => 'shopify',
            'status' => 'connected',
            'credentials' => null,
            'granted_scopes' => ['read_products', 'read_orders'],
            'connected_at' => now(),
            'disconnected_at' => null,
        ];
    }
}
