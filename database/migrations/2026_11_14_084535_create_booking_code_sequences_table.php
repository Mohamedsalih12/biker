<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_code_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('last_code');
        });

        $lastCode = 9999;

        DB::table('bookings')->select('booking_code')->orderBy('id')->chunk(500, function (Collection $bookings) use (&$lastCode): void {
            foreach ($bookings as $booking) {
                $bookingCode = (string) $booking->booking_code;

                if (preg_match('/^\d+$/D', $bookingCode) === 1) {
                    $lastCode = max($lastCode, (int) $bookingCode);
                }
            }
        });

        DB::table('booking_code_sequences')->insert(['id' => 1, 'last_code' => $lastCode]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_code_sequences');
    }
};
