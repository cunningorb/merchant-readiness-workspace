<?php

namespace Tests\Unit\Models;

use App\Models\DataImport;
use App\Models\DataImportError;
use App\Models\DataImportFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataImportErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_import_error_belongs_to_data_import(): void
    {
        $import = DataImport::factory()->create();
        $error = DataImportError::factory()->for($import)->create();

        $this->assertTrue($error->dataImport->is($import));
    }

    public function test_data_import_error_belongs_to_data_import_file(): void
    {
        $file = DataImportFile::factory()->create();
        $error = DataImportError::factory()->for($file, 'dataImportFile')->create();

        $this->assertTrue($error->dataImportFile->is($file));
    }

    public function test_data_import_file_id_is_nullable(): void
    {
        $error = DataImportError::factory()->create(['data_import_file_id' => null]);

        $this->assertNull($error->fresh()->dataImportFile);
    }

    public function test_deleting_data_import_file_cascades_to_error(): void
    {
        $file = DataImportFile::factory()->create();
        $error = DataImportError::factory()->for($file, 'dataImportFile')->create();

        $file->delete();

        $this->assertDatabaseMissing('data_import_errors', ['id' => $error->id]);
    }

    public function test_context_casts_to_array(): void
    {
        $error = DataImportError::factory()->create(['context' => ['column' => 'sku']]);

        $this->assertSame(['column' => 'sku'], $error->fresh()->context);
    }

    public function test_factory_creates_valid_data_import_error(): void
    {
        $error = DataImportError::factory()->create();

        $this->assertDatabaseHas('data_import_errors', ['id' => $error->id]);
    }
}
