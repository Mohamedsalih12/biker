<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = config('activitylog.database_connection');
        $tableName = config('activitylog.table_name', 'activity_log');

        if (! Schema::connection($connection)->hasColumn($tableName, 'attribute_changes')) {
            Schema::connection($connection)->table($tableName, function (Blueprint $table): void {
                $table->json('attribute_changes')->nullable();
            });
        }

        DB::connection($connection)->table($tableName)
            ->whereNotNull('properties')
            ->whereNull('attribute_changes')
            ->chunkById(500, function ($rows) use ($connection, $tableName): void {
                foreach ($rows as $row) {
                    $decoded = json_decode($row->properties, false, 512, JSON_THROW_ON_ERROR);

                    if (! is_object($decoded)) {
                        continue;
                    }

                    $properties = get_object_vars($decoded);
                    $changes = array_intersect_key($properties, array_flip(['attributes', 'old']));

                    if ($changes === []) {
                        continue;
                    }

                    $remaining = array_diff_key($properties, $changes);

                    DB::connection($connection)->table($tableName)->where('id', $row->id)->update([
                        'attribute_changes' => json_encode($changes, JSON_THROW_ON_ERROR),
                        'properties' => $remaining === [] ? null : json_encode($remaining, JSON_THROW_ON_ERROR),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('activitylog.database_connection');
        $tableName = config('activitylog.table_name', 'activity_log');

        DB::connection($connection)->table($tableName)
            ->whereNotNull('attribute_changes')
            ->chunkById(500, function ($rows) use ($connection, $tableName): void {
                foreach ($rows as $row) {
                    $properties = $row->properties === null
                        ? []
                        : (array) json_decode($row->properties, false, 512, JSON_THROW_ON_ERROR);
                    $changes = (array) json_decode($row->attribute_changes, false, 512, JSON_THROW_ON_ERROR);

                    DB::connection($connection)->table($tableName)->where('id', $row->id)->update([
                        'properties' => json_encode(array_merge($properties, $changes), JSON_THROW_ON_ERROR),
                    ]);
                }
            });

        Schema::connection($connection)->table($tableName, function (Blueprint $table): void {
            $table->dropColumn('attribute_changes');
        });
    }
};
