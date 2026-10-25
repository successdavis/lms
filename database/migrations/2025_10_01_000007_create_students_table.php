<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('programme_id')->constrained();
            $table->foreignId('level_id')->constrained();       // current level
            $table->foreignId('entry_session_id')->constrained('academic_sessions');
            $table->string('entry_mode');                        // EntryMode
            $table->string('matric_no')->nullable()->unique();
            $table->string('jamb_reg_no')->nullable();
            $table->string('status')->default('active');         // StudentStatus
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable()->default('Nigerian');
            $table->string('state_of_origin')->nullable();
            $table->string('lga_of_origin')->nullable();
            // Indigenes of the owning state often pay reduced fees in state institutions.
            $table->boolean('is_indigene')->default(false);
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->string('passport_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
