<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GenreSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('genres')->insert([
            ['name' => 'Pop', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Hip Hop', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Electronic', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Rock', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Jazz', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
