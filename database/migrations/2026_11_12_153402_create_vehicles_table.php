<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('registration_number')->unique();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('chassis_number')->nullable();
            $table->string('battery_serial_number')->nullable();
            $table->unsignedBigInteger('odometer_reading')->nullable();
            $table->decimal('rate_per_unit', 12, 2)->nullable();
            $table->enum('status', ['available', 'rented', 'maintenance', 'decommissioned'])->default('available')->index();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->string('insurance_policy_number')->nullable();
            $table->date('insurance_expires_at')->nullable()->index();
            $table->string('image')->nullable();
            $table->text('damages')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
