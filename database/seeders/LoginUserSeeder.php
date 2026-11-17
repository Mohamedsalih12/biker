<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoginUserSeeder extends Seeder
{
    public function run(): void
    {
        $records = [
            [
                'id' => '1',
                'name' => 'Staff',
                'email' => 'mohamedsalih12@gmail.com',
                'role' => 'staff',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => '9',
                'name' => 'Salih',
                'email' => 'mohamedsalihusyed@gmail.com',
                'role' => 'admin',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach (array_chunk($records, 2) as $chunk) {
            foreach ($chunk as $record) {
                DB::table('users')->updateOrInsert(
                    ['email' => $record['email']],
                    $record
                );
            }
        }
    }
}
