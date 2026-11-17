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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code', 30)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('rental_type', ['daily', 'weekly', 'monthly'])->default('monthly');
            $table->decimal('rate_per_unit', 12, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('actual_return_date')->nullable();
            $table->unsignedBigInteger('start_odometer');
            $table->unsignedBigInteger('end_odometer')->nullable();
            $table->enum('booking_status', ['reserved', 'taken', 'returned', 'cancelled'])->default('reserved')->index();
            $table->timestamp('payment_due_at')->nullable()->index();
            $table->enum('payment_status', ['pending', 'paid', 'overdue'])->default('pending')->index();
            $table->text('handover_notes')->nullable();
            $table->text('return_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
