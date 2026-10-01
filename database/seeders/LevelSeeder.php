<?php

namespace Database\Seeders;

use App\Models\Level;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            // University levels
            ['100', '100 Level', 1],
            ['200', '200 Level', 2],
            ['300', '300 Level', 3],
            ['400', '400 Level', 4],
            ['500', '500 Level', 5],
            ['600', '600 Level', 6],
            // Polytechnic levels (ND and HND are separate programmes)
            ['ND1', 'ND I', 1],
            ['ND2', 'ND II', 2],
            ['HND1', 'HND I', 1],
            ['HND2', 'HND II', 2],
        ];

        foreach ($levels as [$code, $name, $rank]) {
            Level::updateOrCreate(['code' => $code], ['name' => $name, 'rank' => $rank]);
        }
    }
}
