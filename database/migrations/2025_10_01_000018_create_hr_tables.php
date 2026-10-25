<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Establishment and payroll fields. Federal institutions pay through
        // IPPIS; the payroll export carries what an IPPIS-style system needs.
        Schema::table('staff', function (Blueprint $table) {
            $table->string('salary_structure')->nullable()->after('designation'); // CONUASS | CONPCASS | CONTISS | CONTEDISS
            $table->unsignedTinyInteger('grade_level')->nullable()->after('salary_structure');
            $table->unsignedTinyInteger('step')->nullable()->after('grade_level');
            $table->date('rank_since')->nullable()->after('step');
            $table->string('ippis_number')->nullable()->unique()->after('rank_since');
            $table->string('bank_name')->nullable()->after('ippis_number');
            $table->string('account_number', 10)->nullable()->after('bank_name'); // NUBAN
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->string('from_designation')->nullable();
            $table->string('to_designation')->nullable();
            $table->unsignedTinyInteger('from_grade_level')->nullable();
            $table->unsignedTinyInteger('to_grade_level');
            $table->date('effective_on');
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->text('justification')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('recommended_by_id')->constrained('users');
            $table->foreignId('decided_by_id')->nullable()->constrained('users');
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
        Schema::table('staff', fn (Blueprint $table) => $table->dropColumn([
            'salary_structure', 'grade_level', 'step', 'rank_since', 'ippis_number', 'bank_name', 'account_number',
        ]));
    }
};
