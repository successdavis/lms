<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');               // School Fees, Acceptance Fee, Hostel ...
            $table->string('code')->unique();
            // Unpaid fees of this type block course registration for the session.
            $table->boolean('blocks_registration')->default(false);
            $table->boolean('is_recurring')->default(true); // charged every session vs one-off
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Amount rules; most-specific match wins (programme > level > entry mode > indigene scope).
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('entry_mode')->nullable();      // EntryMode filter
            $table->string('indigene_scope')->nullable();  // 'indigene' | 'non_indigene' | null = all
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained();
            $table->decimal('total', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('status')->default('unpaid'); // InvoiceStatus
            $table->date('due_on')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_type_id')->constrained();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('gateway');                    // PaymentGateway
            $table->string('reference')->unique();        // gateway transaction reference
            $table->string('rrr')->nullable();            // Remita Retrieval Reference
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending'); // PaymentStatus
            $table->string('channel')->nullable();        // card, bank_transfer, ussd ...
            $table->dateTime('paid_at')->nullable();
            $table->json('meta')->nullable();             // raw gateway/webhook payload
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_types');
    }
};
