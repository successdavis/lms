@extends('pdf.layout')
@section('title', 'Provisional Offer of Admission')
@section('content')
    <table class="bio">
        <tr><td class="label">Name:</td><td>{{ $applicant['name'] }}</td><td class="label">Date:</td><td>{{ $admitted_on }}</td></tr>
        <tr><td class="label">Application No:</td><td>{{ $applicant['application_no'] }}</td><td class="label">JAMB Reg No:</td><td>{{ $applicant['jamb_reg_no'] }}</td></tr>
    </table>
    <p>Dear {{ $applicant['name'] }},</p>
    <p><strong>PROVISIONAL OFFER OF ADMISSION — {{ $session }} ACADEMIC SESSION</strong></p>
    <p>
        I am pleased to inform you that you have been offered provisional admission into
        <strong>{{ $institution['name'] ?? 'this institution' }}</strong> to study
        <strong>{{ $programme['name'] }} ({{ $programme['award'] }})</strong> in the Department of {{ $department }},
        {{ $faculty }}@if ($list), under the {{ $list }}@endif.
    </p>
    <p>
        This offer is provisional and subject to: (a) verification of your credentials during physical clearance; (b) payment of the
        acceptance fee and prescribed school fees; and (c) confirmation of your admission on the JAMB Central Admissions Processing System
        (CAPS). Any false declaration will lead to the withdrawal of this offer.
    </p>
    <p>
        @if ($accepted)
            Our records show you have accepted this offer. Proceed with clearance and fee payment on the portal.
        @else
            To accept this offer, log in to the application portal and click "Accept admission offer", then proceed with clearance and fee payment.
        @endif
    </p>
    <p>Congratulations!</p>
    <table class="signatures">
        <tr><td></td><td><div class="line"><strong>Registrar</strong><br><span class="muted">For: {{ $institution['name'] ?? '' }}</span></div></td></tr>
    </table>
@endsection
