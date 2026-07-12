<?php

namespace Tests\Unit\Models;

use App\Models\DataImport;
use App\Models\DataImportError;
use App\Models\DataImportFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataImportFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_import_file_belongs_to_data_import(): void
    {
        $import = DataImport::factory()->create();
        $file = DataImportFile::factory()->for($import)->create();

        $this->assertTrue($file->dataImport->is($import));
    }

    public function test_data_import_file_has_many_errors(): void
    {
        $file = DataImportFile::factory()->create();
        $error = DataImportError::factory()->for($file, 'dataImportFile')->create();

        $this->assertTrue($file->fresh()->errors->contains($error));
    }

    public function test_deleting_data_import_cascades_to_files(): void
    {
        $import = DataImport::factory()->create();
        $file = DataImportFile::factory()->for($import)->create();

        $import->delete();

        $this->assertDatabaseMissing('data_import_files', ['id' => $file->id]);
    }

    public function test_row_counts_are_nullable(): void
    {
        $file = DataImportFile::factory()->create([
            'row_count' => null,
            'accepted_count' => null,
            'rejected_count' => null,
        ]);

        $fresh = $file->fresh();

        $this->assertNull($fresh->row_count);
        $this->assertNull($fresh->accepted_count);
        $this->assertNull($fresh->rejected_count);
    }

    public function test_factory_creates_valid_data_import_file(): void
    {
        $file = DataImportFile::factory()->create();

        $this->assertDatabaseHas('data_import_files', ['id' => $file->id]);
    }
}
