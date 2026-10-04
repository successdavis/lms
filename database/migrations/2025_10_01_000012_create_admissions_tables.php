<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One admission exercise per academic session: application window and
        // the screening formula (aggregate = UTME% x weight + Post-UTME% x weight).
        Schema::create('admission_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();
            $table->unsignedTinyInteger('utme_weight')->default(60);
            $table->unsignedTinyInteger('post_utme_weight')->default(40);
            $table->decimal('default_cutoff', 5, 2)->default(50);  // aggregate (0-100)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Merit list, 2nd/3rd batch, supplementary — published in batches.
        Schema::create('admission_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. "Merit List", "Supplementary List"
            $table->unsignedTinyInteger('sort')->default(0);
            $table->dateTime('published_at')->nullable();
            $table->timestamps();

            $table->unique(['admission_cycle_id', 'name']);
        });

        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('admission_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('application_no')->unique();
            $table->string('status')->default('draft'); // ApplicantStatus

            // Choices: applied programme; the offer may be for a different one.
            $table->foreignId('programme_id')->constrained();
            $table->foreignId('admitted_programme_id')->nullable()->constrained('programmes');
            $table->string('entry_mode')->default('utme'); // EntryMode

            // JAMB and screening
            $table->string('jamb_reg_no')->nullable();
            $table->unsignedSmallInteger('utme_score')->nullable();      // 0-400
            $table->decimal('post_utme_score', 5, 2)->nullable();        // 0-100
            $table->decimal('aggregate_score', 5, 2)->nullable();        // 0-100

            // Bio data
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone')->nullable();
            $table->string('state_of_origin')->nullable();
            $table->string('lga_of_origin')->nullable();
            $table->text('address')->nullable();

            // O'level results: [{exam, year, subjects: {subject: grade}}, ...]
            $table->json('o_level_results')->nullable();

            $table->foreignId('admission_list_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('admitted_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('matriculated_at')->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['admission_cycle_id', 'jamb_reg_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicants');
        Schema::dropIfExists('admission_lists');
        Schema::dropIfExists('admission_cycles');
    }
};
