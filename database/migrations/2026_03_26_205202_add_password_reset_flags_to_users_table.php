<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->timestamp('password_reset_at')->nullable()->after('must_change_password');
            $table->unsignedBigInteger('password_reset_by')->nullable()->after('password_reset_at');
            $table->boolean('active_status')->default(true)->after('password_reset_by');

            $table->foreign('password_reset_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['password_reset_by']);
            $table->dropColumn([
                'must_change_password',
                'password_reset_at',
                'password_reset_by',
                'active_status',
            ]);
        });
    }
};
