<?php

namespace Tests\Feature\DeletedModels;

use App\Models\User;
use Spatie\DeletedModels\Models\DeletedModel;
use Tests\TestCase;

class DeletedModelPruningTest extends TestCase
{
    public function test_pruning_deletes_deleted_models_older_than_configured_retention_period(): void
    {
        $this->freezeTime();

        $retentionPeriod = config('deleted-models.prune_after_days');
        // $retentionPeriod = 360;
        $expiredRecord = DeletedModel::query()->create([
            'key' => 'expired-record',
            'model' => User::class,
            'values' => [],
            'created_at' => now()->subDays($retentionPeriod + 1),
            'updated_at' => now()->subDays($retentionPeriod + 1),
        ]);
        $recentRecord = DeletedModel::query()->create([
            'key' => 'recent-record',
            'model' => User::class,
            'values' => [],
            'created_at' => now()->subDays($retentionPeriod - 1),
            'updated_at' => now()->subDays($retentionPeriod - 1),
        ]);

        $this->artisan('model:prune', ['--model' => DeletedModel::class])
            ->assertSuccessful();

        $this->assertDatabaseMissing('deleted_models', ['id' => $expiredRecord->id]);
        $this->assertDatabaseHas('deleted_models', ['id' => $recentRecord->id]);
    }
}
