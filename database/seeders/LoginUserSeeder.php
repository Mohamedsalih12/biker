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
                'name' => 'director',
                'email' => 'dr@gmb.com',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => '2',
                'name' => 'account_manager',
                'email' => 'am@gmb.com',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => '3',
                'name' => 'project_manager',
                'email' => 'pm@gmb.com',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => '4',
                'name' => 'account_executive',
                'email' => 'ae@gmb.com',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => '5',
                'name' => 'warehouse_personnel',
                'email' => 'wp@gmb.com',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => '6',
                'name' => 'supervisor',
                'email' => 'sr@gmb.com',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => '7',
                'name' => 'promoter',
                'email' => 'pr@gmb.com',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => '8',
                'name' => 'admin',
                'email' => 'an@gmb.com',
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
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => '10',
                'name' => 'Danny',
                'email' => 'danny@tmsdiensten.nl',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach (array_chunk($records, 100) as $chunk) {
            foreach ($chunk as $record) {
                DB::table('users')->updateOrInsert(
                    ['email' => $record['email']],
                    $record
                );
            }
        }
    }
}
