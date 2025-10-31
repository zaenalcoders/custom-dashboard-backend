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
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        DB::table('users')->truncate();
        DB::table('roles')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

        DB::beginTransaction();
        try {
            $faker = (new Factory())->create();

            $role = new Role();
            $role->name = 'Super Administrator';
            $role->access = ['*'];
            $role->save();

            $user = new User();
            $user->role_id = $role->id;
            $user->name = $faker->name;
            $user->email = 'admin@demo.com';
            $user->password = Hash::make('admin123');
            $user->status = 1;
            $user->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
