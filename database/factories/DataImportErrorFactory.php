<?php

namespace Database\Factories;

use App\Models\DataImport;
use App\Models\DataImportError;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataImportError>
 */
class DataImportErrorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'data_import_id' => DataImport::factory(),
            'data_import_file_id' => null,
            'row_number' => fake()->numberBetween(1, 100),
            'message' => 'Missing required column: sku',
            'context' => ['column' => 'sku'],
        ];
    }
}
