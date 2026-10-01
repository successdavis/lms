<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('type')->default('university'); // InstitutionType
            $table->string('motto')->nullable();
            $table->string('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('logo_path')->nullable();
            // e.g. "{SHORT}/{YY}/{DEPT}/{SEQ:4}" -> "FUTO/25/CSC/0042"
            $table->string('matric_format')->default('{SHORT}/{YY}/{DEPT}/{SEQ:4}');
            // minimum % of registration-blocking fees paid before course registration opens
            $table->unsignedTinyInteger('min_fee_percent_for_registration')->default(100);
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutions');
    }
};
