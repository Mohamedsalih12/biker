<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('maintenance_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->text('service_description');
            $table->date('delivered_date')->default(new Expression('(CURRENT_DATE)'))->index();
            $table->date('expected_completion_date');
            $table->decimal('cost', 12, 2)->nullable();
            $table->unsignedBigInteger('odometer_reading')->nullable();
            $table->enum('vendor_name', ['vendor_1', 'vendor_2', 'vendor_3', 'vendor_4']);
            $table->enum('status', ['scheduled', 'in_progress', 'completed'])->default('in_progress')->index();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_logs');
    }
};
