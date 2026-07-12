<?php

namespace Database\Factories;

use App\Models\DataImport;
use App\Models\DataImportFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataImportFile>
 */
class DataImportFileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'data_import_id' => DataImport::factory(),
            'data_type' => 'products',
            'original_filename' => 'products.csv',
            'stored_path' => 'imports/'.fake()->uuid().'.csv',
            'row_count' => fake()->numberBetween(10, 500),
            'accepted_count' => fake()->numberBetween(10, 500),
            'rejected_count' => 0,
            'fingerprint' => fake()->sha256(),
        ];
    }
}
