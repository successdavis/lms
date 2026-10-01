<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // e.g. NUC 5-Point Scale
            $table->string('slug')->unique();             // nuc-5-point, nbte-4-point
            $table->decimal('max_point', 3, 2);           // 5.00 / 4.00
            $table->decimal('pass_mark', 5, 2);           // 40.00
            $table->decimal('probation_cgpa', 3, 2);      // below this -> probation
            $table->decimal('withdrawal_cgpa', 3, 2)->nullable(); // below this on probation -> withdrawal
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('grade_scale_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_scale_id')->constrained()->cascadeOnDelete();
            $table->string('letter');                 // A, AB, B, BC, ... F
            $table->decimal('min_score', 5, 2);
            $table->decimal('max_score', 5, 2);
            $table->decimal('point', 3, 2);
            $table->boolean('is_pass')->default(true);
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['grade_scale_id', 'letter']);
        });

        // Final award classification (First Class ... Pass / Distinction ... Pass)
        Schema::create('classification_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_scale_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('min_cgpa', 3, 2);
            $table->decimal('max_cgpa', 3, 2);
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classification_bands');
        Schema::dropIfExists('grade_scale_bands');
        Schema::dropIfExists('grade_scales');
    }
};
