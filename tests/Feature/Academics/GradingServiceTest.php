<?php

use App\Models\GradeScale;
use App\Services\Academics\GradingService;
use Database\Seeders\GradeScaleSeeder;

beforeEach(function () {
    $this->seed(GradeScaleSeeder::class);
    $this->grading = new GradingService;
});

it('resolves NUC 5-point grades correctly', function (float $score, string $letter, float $point, bool $pass) {
    $scale = GradeScale::where('slug', 'nuc-5-point')->firstOrFail();

    $band = $this->grading->resolve($scale, $score);

    expect($band->letter)->toBe($letter)
        ->and((float) $band->point)->toBe($point)
        ->and($band->is_pass)->toBe($pass);
})->with([
    'A at 70' => [70.0, 'A', 5.0, true],
    'A at 100' => [100.0, 'A', 5.0, true],
    'B at 64' => [64.0, 'B', 4.0, true],
    'C at 50' => [50.0, 'C', 3.0, true],
    'D at 45' => [45.0, 'D', 2.0, true],
    'E at 40 (pass mark)' => [40.0, 'E', 1.0, true],
    'F at 39.5' => [39.5, 'F', 0.0, false],
    'F at 0' => [0.0, 'F', 0.0, false],
]);

it('resolves NBTE 4-point grades correctly', function (float $score, string $letter, float $point) {
    $scale = GradeScale::where('slug', 'nbte-4-point')->firstOrFail();

    $band = $this->grading->resolve($scale, $score);

    expect($band->letter)->toBe($letter)
        ->and((float) $band->point)->toBe($point);
})->with([
    'A at 75' => [75.0, 'A', 4.0],
    'AB at 72' => [72.0, 'AB', 3.5],
    'B at 65' => [65.0, 'B', 3.25],
    'BC at 60' => [60.0, 'BC', 3.0],
    'C at 55' => [55.0, 'C', 2.75],
    'CD at 50' => [50.0, 'CD', 2.5],
    'D at 45' => [45.0, 'D', 2.25],
    'E at 40 (bare pass)' => [40.0, 'E', 2.0],
    'F at 39' => [39.0, 'F', 0.0],
]);

it('rejects scores outside 0-100', function () {
    $scale = GradeScale::where('slug', 'nuc-5-point')->firstOrFail();

    $this->grading->resolve($scale, 101);
})->throws(InvalidArgumentException::class);
