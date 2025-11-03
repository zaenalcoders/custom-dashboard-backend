<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Faker\Factory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = (new Factory())->create();
        $role = Role::where('name', 'Super Administrator')->first();
        if (!$role) {
            $role = new Role();
            $role->name = 'Super Administrator';
            $role->access = ['*'];
            $role->save();
        }

        $user = User::where('email', 'admin@demo.com')->first();
        if (!$user) {
            $user = new User();
            $user->role_id = $role->id;
            $user->name = $faker->name;
            $user->email = 'admin@demo.com';
            $user->password = Hash::make('admin123');
            $user->status = 1;
            $user->save();
        }
    }
}
