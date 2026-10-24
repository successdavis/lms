@extends('pdf.layout')
@section('title', 'Official Payment Receipt')
@section('content')
    @include('pdf.partials-bio', ['extra' => [['Session', $invoice['session']], ['Invoice No', $invoice['number']]]])
    <table class="grid" style="margin-bottom: 10px;">
        <tr><th style="width: 30%">Receipt Reference</th><td>{{ $payment['reference'] }}</td></tr>
        @if ($payment['rrr'])<tr><th>Remita RRR</th><td>{{ $payment['rrr'] }}</td></tr>@endif
        <tr><th>Amount Paid</th><td><strong>{{ '₦'.number_format((float) $payment['amount'], 2) }}</strong></td></tr>
        <tr><th>Payment Method</th><td>{{ ucfirst((string) $payment['gateway']) }}@if ($payment['channel']) ({{ $payment['channel'] }})@endif</td></tr>
        <tr><th>Date</th><td>{{ $payment['paid_at'] ?? '—' }}</td></tr>
    </table>
    <strong>Invoice breakdown</strong>
    <table class="grid">
        @foreach ($invoice['items'] as $item)
            <tr><td>{{ $item['description'] }}</td><td class="right">{{ '₦'.number_format((float) $item['amount'], 2) }}</td></tr>
        @endforeach
        <tr><th>Invoice Total</th><th class="right">{{ '₦'.number_format((float) $invoice['total'], 2) }}</th></tr>
        <tr><th>Total Paid to Date</th><th class="right">{{ '₦'.number_format((float) $invoice['amount_paid'], 2) }} ({{ strtoupper(str_replace('_', ' ', (string) $invoice['status'])) }})</th></tr>
    </table>
    <p class="muted center" style="margin-top: 24px;">
        This receipt is computer-generated and valid without a signature. Verify it against the bursary's records using the reference above.
    </p>
@endsection
