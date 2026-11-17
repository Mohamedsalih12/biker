<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
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
        Schema::table('maintenance_logs', function (Blueprint $table): void {
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('in_progress')->change();
        });

        DB::table('vehicles')->where('status', 'maintenance')
            ->orWhereExists(function (Builder $query): void {
                $query->selectRaw('1')->from('maintenance_logs')
                    ->whereColumn('maintenance_logs.vehicle_id', 'vehicles.id')->where('status', 'in_progress');
            })->update(['status' => DB::raw("CASE WHEN EXISTS (SELECT 1 FROM maintenance_logs WHERE maintenance_logs.vehicle_id = vehicles.id AND maintenance_logs.status = 'in_progress') THEN 'maintenance' ELSE 'available' END")]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('maintenance_logs')->where('status', 'cancelled')->exists()) {
            throw new RuntimeException('Cannot remove the cancelled status while cancelled maintenance logs exist.');
        }

        Schema::table('maintenance_logs', function (Blueprint $table): void {
            $table->enum('status', ['scheduled', 'in_progress', 'completed'])->default('in_progress')->change();
        });
    }
};
