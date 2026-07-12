<?php

namespace Database\Factories;

use App\Enums\ImportMethod;
use App\Enums\ImportStatus;
use App\Models\Assessment;
use App\Models\DataImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataImport>
 */
class DataImportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'data_connection_id' => null,
            'provider' => 'demo',
            'method' => ImportMethod::Demo->value,
            'data_types' => ['catalog', 'orders_returns', 'inventory_locations'],
            'status' => ImportStatus::Created->value,
            'started_at' => null,
            'completed_at' => null,
            'warnings_count' => 0,
            'errors_count' => 0,
            'metadata' => null,
        ];
    }
}
