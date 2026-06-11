<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create(['name' => 'Alice', 'phone' => '+380501234567']);
        User::create(['name' => 'Bob', 'phone' => '+380501234568']);
        User::create(['name' => 'Charlie', 'phone' => '+380501234569']);
    }
}
