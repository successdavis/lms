<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Students Industrial Work Experience Scheme (ITF): one placement per
        // student per session, approved then graded by the SIWES coordinator.
        Schema::create('siwes_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->string('employer_name');
            $table->text('employer_address');
            $table->string('industry_supervisor_name');
            $table->string('industry_supervisor_phone');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status')->default('pending'); // SiwesStatus
            // Assessment: logbook /40, report /40, institution supervisor visits /20.
            $table->decimal('logbook_score', 5, 2)->nullable();
            $table->decimal('report_score', 5, 2)->nullable();
            $table->decimal('visit_score', 5, 2)->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            $table->boolean('is_passed')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['student_id', 'academic_session_id']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // LeaveType
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedSmallInteger('days'); // working days (Mon-Fri)
            $table->text('reason')->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->string('note')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users');
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('siwes_placements');
    }
};
