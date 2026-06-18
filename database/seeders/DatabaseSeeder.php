<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(GenreSeeder::class);

        $adminRole = Role::firstOrCreate(['role_name' => 'admin']);
        Role::firstOrCreate(['role_name' => 'user']);

        $admin = User::where('username', 'admin')->first()
            ?? User::where('email', 'admin@gmail.com')->first()
            ?? new User();

        $admin->fill([
            'username' => 'admin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('123456'),
            'role_id' => $adminRole->id,
            'status' => 'active',
            'is_verified' => true,
            'is_password_set' => true,
        ]);

        $admin->save();
    }
}
