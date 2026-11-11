<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('pos_code');
            $table->string('login_time');
            $table->string('logout_time')->nullable();
            $table->string('ip_address');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_logs');
    }
};
