<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Invoices now serve more than sessional school fees: transcript and
        // hostel charges ride the same payment pipeline but must not be
        // mistaken for (or block generation of) the session's school-fees bill.
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('purpose')->default('school_fees')->after('academic_session_id'); // InvoicePurpose
        });

        Schema::create('transcript_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('destination');   // local | international
            $table->string('delivery');      // e_copy | hard_copy
            $table->string('recipient_name');             // receiving institution/organisation
            $table->text('recipient_address')->nullable(); // hard copies
            $table->string('recipient_email')->nullable(); // e-copies
            $table->string('purpose')->nullable();        // e.g. "Masters admission"
            $table->decimal('fee', 10, 2);
            $table->string('status')->default('awaiting_payment'); // TranscriptStatus
            $table->string('dispatch_reference')->nullable(); // courier tracking no. / email message id
            $table->foreignId('processed_by_id')->nullable()->constrained('users');
            $table->dateTime('processed_at')->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_requests');
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('purpose'));
    }
};
