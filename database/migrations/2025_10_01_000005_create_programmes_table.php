<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 100L-600L for universities; ND I/II, HND I/II for polytechnics.
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();        // "100", "200", "ND1", "HND2"
            $table->string('name');                  // "100 Level", "ND I"
            $table->unsignedTinyInteger('rank');     // promotion order within a programme group
            $table->timestamps();
        });

        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_scale_id')->constrained();
            $table->string('name');                   // B.Sc Computer Science
            $table->string('code')->unique();         // BSC-CSC
            $table->string('award');                  // BSC, BA, BENG, LLB, ND, HND ...
            $table->unsignedTinyInteger('duration_semesters'); // 8 (4yr), 10, 4 (ND/HND)
            $table->foreignId('entry_level_id')->nullable()->constrained('levels');
            $table->unsignedTinyInteger('min_units_per_semester')->default(15);
            $table->unsignedTinyInteger('max_units_per_semester')->default(24);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmes');
        Schema::dropIfExists('levels');
    }
};
