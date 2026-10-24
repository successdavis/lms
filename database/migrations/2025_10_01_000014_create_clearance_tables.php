<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Final-year clearance: one request per student, signed off unit by
        // unit (department, faculty, library, bursary, student affairs).
        Schema::create('clearance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('in_progress'); // in_progress | cleared
            $table->dateTime('cleared_at')->nullable();
            $table->timestamps();
        });

        Schema::create('clearance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clearance_request_id')->constrained()->cascadeOnDelete();
            $table->string('unit');                       // ClearanceUnit
            $table->string('status')->default('pending'); // ClearanceItemStatus
            $table->string('note')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users');
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['clearance_request_id', 'unit']);
        });

        // Graduation + NYSC mobilization bookkeeping on the student record.
        Schema::table('students', function (Blueprint $table) {
            $table->date('graduated_on')->nullable()->after('status');
            $table->string('nysc_batch')->nullable()->after('graduated_on'); // e.g. "2026 Batch A"
        });
    }

    public function down(): void
    {
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn(['graduated_on', 'nysc_batch']));
        Schema::dropIfExists('clearance_items');
        Schema::dropIfExists('clearance_requests');
    }
};
