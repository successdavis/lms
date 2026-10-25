<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with a fully populated university
     * (ten years of students, staff, results and payments). For the small
     * fixture the tests use, run: php artisan db:seed --class=DemoSeeder
     * (after RoleSeeder, GradeScaleSeeder and LevelSeeder).
     */
    public function run(): void
    {
        $this->call(FullUniversitySeeder::class);
    }
}
