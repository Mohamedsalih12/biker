<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;

class ActivityLogUpgradeTest extends TestCase
{
    use CreatesApplication;

    public function test_upgrade_and_rollback_preserve_audit_history_and_custom_metadata(): void
    {
        $this->createLegacyActivityTable();
        $originalProperties = [
            ['attributes' => ['name' => 'After'], 'old' => ['name' => 'Before'], 'ip' => '8.8.8.8', 'status' => 'success', 'context' => ['request' => 42]],
            ['ip' => '1.1.1.1', 'status' => 'failed'],
            ['attributes' => [], 'old' => null],
            null,
            ['attributes' => ['nested' => (object) []], 'context' => (object) []],
        ];
        foreach ($originalProperties as $index => $properties) {
            DB::table('activity_log')->insert([
                'id' => $index + 1,
                'description' => 'Historical audit '.$index,
                'event' => 'updated',
                'properties' => $properties === null ? null : json_encode($properties),
                'country' => 'US',
                'batch_uuid' => '12345678-1234-1234-1234-123456789012',
                'created_at' => '2026-09-01 10:00:00',
                'updated_at' => '2026-09-01 10:00:00',
            ]);
        }
        $originalRows = DB::table('activity_log')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
        $migration = require database_path('migrations/2026_10_15_111517_add_attribute_changes_to_activity_log_table.php');

        $migration->up();

        $this->assertTrue(Schema::hasColumn('activity_log', 'attribute_changes'));
        $rows = DB::table('activity_log')->orderBy('id')->get();
        $this->assertCount(5, $rows);
        $this->assertSame(['attributes' => ['name' => 'After'], 'old' => ['name' => 'Before']], json_decode($rows[0]->attribute_changes, true));
        $this->assertSame(['ip' => '8.8.8.8', 'status' => 'success', 'context' => ['request' => 42]], json_decode($rows[0]->properties, true));
        $this->assertNull($rows[1]->attribute_changes);
        $this->assertSame(['attributes' => [], 'old' => null], json_decode($rows[2]->attribute_changes, true));
        $this->assertNull($rows[3]->attribute_changes);
        $this->assertInstanceOf(\stdClass::class, json_decode($rows[4]->properties)->context);
        $this->assertInstanceOf(\stdClass::class, json_decode($rows[4]->attribute_changes)->attributes->nested);
        foreach ($rows as $index => $row) {
            $unchanged = (array) $row;
            unset($unchanged['properties'], $unchanged['attribute_changes']);
            $original = $originalRows[$index];
            unset($original['properties']);
            $this->assertSame($original, $unchanged);
        }

        DB::table('activity_log')->insert([
            'id' => 6,
            'description' => 'New v5 audit',
            'properties' => json_encode(['status' => 'success']),
            'attribute_changes' => json_encode(['attributes' => ['name' => 'New v5 value'], 'old' => ['name' => 'Previous value']]),
            'country' => 'AU',
        ]);

        $migration->down();

        $this->assertFalse(Schema::hasColumn('activity_log', 'attribute_changes'));
        foreach (DB::table('activity_log')->where('id', '<=', 5)->orderBy('id')->get() as $index => $row) {
            if ($originalProperties[$index] === null) {
                $this->assertNull($row->properties);
            } else {
                $this->assertJsonStringEqualsJsonString(json_encode($originalProperties[$index]), $row->properties);
            }
        }
        $restoredObjects = json_decode(DB::table('activity_log')->find(5)->properties);
        $this->assertInstanceOf(\stdClass::class, $restoredObjects->context);
        $this->assertInstanceOf(\stdClass::class, $restoredObjects->attributes->nested);
        $newActivity = DB::table('activity_log')->find(6);
        $this->assertSame([
            'status' => 'success',
            'attributes' => ['name' => 'New v5 value'],
            'old' => ['name' => 'Previous value'],
        ], json_decode($newActivity->properties, true));
        $this->assertSame('AU', $newActivity->country);
    }

    public function test_interrupted_backfill_resumes_without_overwriting_converted_audit_changes(): void
    {
        $this->createLegacyActivityTable();
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->json('attribute_changes')->nullable();
        });
        $convertedChanges = json_encode(['attributes' => ['name' => 'Converted'], 'old' => ['name' => 'Original']]);
        DB::table('activity_log')->insert([
            'id' => 1,
            'description' => 'Already migrated',
            'properties' => json_encode(['ip' => '8.8.8.8']),
            'attribute_changes' => $convertedChanges,
        ]);
        DB::table('activity_log')->insert([
            'id' => 2,
            'description' => 'Pending migration',
            'properties' => json_encode(['attributes' => ['name' => 'Pending'], 'old' => ['name' => 'Original'], 'status' => 'success']),
        ]);
        $migration = require database_path('migrations/2026_10_15_111517_add_attribute_changes_to_activity_log_table.php');

        $migration->up();
        $migration->up();

        $converted = DB::table('activity_log')->find(1);
        $pending = DB::table('activity_log')->find(2);
        $this->assertSame($convertedChanges, $converted->attribute_changes);
        $this->assertSame(['ip' => '8.8.8.8'], json_decode($converted->properties, true));
        $this->assertSame(['attributes' => ['name' => 'Pending'], 'old' => ['name' => 'Original']], json_decode($pending->attribute_changes, true));
        $this->assertSame(['status' => 'success'], json_decode($pending->properties, true));
    }

    private function createLegacyActivityTable(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        foreach ([
            '2026_03_08_105847_create_activity_log_table.php',
            '2026_03_08_105848_add_event_column_to_activity_log_table.php',
            '2026_03_08_105849_add_batch_uuid_column_to_activity_log_table.php',
        ] as $file) {
            require_once database_path('migrations/'.$file);
        }
        (new \CreateActivityLogTable)->up();
        (new \AddEventColumnToActivityLogTable)->up();
        (new \AddBatchUuidColumnToActivityLogTable)->up();
        $countryMigration = require database_path('migrations/2026_09_17_120650_add_country_to_activity_log_table.php');
        $countryMigration->up();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('activity_log');
        DB::purge('sqlite');
        parent::tearDown();
    }
}
