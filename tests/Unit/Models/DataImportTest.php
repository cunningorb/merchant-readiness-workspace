<?php

namespace Tests\Unit\Models;

use App\Enums\ImportMethod;
use App\Enums\ImportStatus;
use App\Models\Assessment;
use App\Models\DataConnection;
use App\Models\DataImport;
use App\Models\DataImportError;
use App\Models\DataImportFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DataImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_import_belongs_to_assessment(): void
    {
        $assessment = Assessment::factory()->create();
        $import = DataImport::factory()->for($assessment)->create();

        $this->assertTrue($import->assessment->is($assessment));
    }

    public function test_data_import_belongs_to_data_connection(): void
    {
        $connection = DataConnection::factory()->create();
        $import = DataImport::factory()->create(['data_connection_id' => $connection->id]);

        $this->assertTrue($import->dataConnection->is($connection));
    }

    public function test_data_connection_id_is_nullable(): void
    {
        $import = DataImport::factory()->create(['data_connection_id' => null]);

        $this->assertNull($import->fresh()->dataConnection);
    }

    public function test_deleting_data_connection_nullifies_data_import_foreign_key(): void
    {
        $connection = DataConnection::factory()->create();
        $import = DataImport::factory()->create(['data_connection_id' => $connection->id]);

        $connection->delete();

        $this->assertNull($import->fresh()->data_connection_id);
        $this->assertDatabaseHas('data_imports', ['id' => $import->id]);
    }

    public function test_data_import_has_many_files(): void
    {
        $import = DataImport::factory()->create();
        $file = DataImportFile::factory()->for($import)->create();

        $this->assertTrue($import->fresh()->files->contains($file));
    }

    public function test_data_import_has_many_errors(): void
    {
        $import = DataImport::factory()->create();
        $error = DataImportError::factory()->for($import)->create();

        $this->assertTrue($import->fresh()->errors->contains($error));
    }

    public function test_deleting_assessment_cascades_to_data_import(): void
    {
        $assessment = Assessment::factory()->create();
        $import = DataImport::factory()->for($assessment)->create();

        $assessment->delete();

        $this->assertDatabaseMissing('data_imports', ['id' => $import->id]);
    }

    public function test_data_types_and_metadata_cast_to_array(): void
    {
        $import = DataImport::factory()->create([
            'data_types' => ['catalog', 'orders_returns'],
            'metadata' => ['requested_by' => 'wizard'],
        ]);

        $fresh = $import->fresh();

        $this->assertSame(['catalog', 'orders_returns'], $fresh->data_types);
        $this->assertSame(['requested_by' => 'wizard'], $fresh->metadata);
    }

    public function test_started_and_completed_at_cast_to_datetime(): void
    {
        $import = DataImport::factory()->create([
            'started_at' => '2026-01-01 00:00:00',
            'completed_at' => '2026-01-01 01:00:00',
        ]);

        $fresh = $import->fresh();

        $this->assertInstanceOf(Carbon::class, $fresh->started_at);
        $this->assertInstanceOf(Carbon::class, $fresh->completed_at);
    }

    public function test_status_and_method_accept_documented_values(): void
    {
        $import = DataImport::factory()->create([
            'status' => ImportStatus::CompletedWithWarnings->value,
            'method' => ImportMethod::Csv->value,
        ]);

        $fresh = $import->fresh();

        $this->assertSame('completed_with_warnings', $fresh->status);
        $this->assertSame('csv', $fresh->method);
    }

    public function test_factory_creates_valid_data_import(): void
    {
        $import = DataImport::factory()->create();

        $this->assertDatabaseHas('data_imports', ['id' => $import->id]);
    }
}
