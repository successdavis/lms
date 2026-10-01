<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g. 2025/2026
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number'); // 1 = First, 2 = Second
            $table->string('name'); // First Semester / Harmattan, Second Semester / Rain
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->dateTime('registration_opens_at')->nullable();
            $table->dateTime('registration_closes_at')->nullable();
            $table->dateTime('late_registration_closes_at')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['academic_session_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('academic_sessions');
    }
};
