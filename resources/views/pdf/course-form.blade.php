@extends('pdf.layout')
@section('title', 'Course Registration Form')
@section('content')
    @include('pdf.partials-bio', ['extra' => [['Session', $session], ['Semester', $semester]]])
    <table class="grid">
        <thead><tr><th>S/N</th><th>Course Code</th><th>Course Title</th><th class="right">Units</th><th>Remark</th></tr></thead>
        <tbody>
            @foreach ($registration['courses'] as $i => $course)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $course['code'] }}</td>
                    <td>{{ $course['title'] }}</td>
                    <td class="right">{{ $course['credit_units'] }}</td>
                    <td>{{ $course['is_carryover'] ? 'Carryover' : '' }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="3" class="right"><strong>Total Units</strong></td>
                <td class="right"><strong>{{ $registration['total_units'] }}</strong></td>
                <td>{{ ucfirst((string) $registration['status']) }}</td>
            </tr>
        </tbody>
    </table>
    <table class="signatures">
        <tr>
            <td><div class="line">Student</div></td>
            <td><div class="line">Course Adviser</div></td>
            <td><div class="line">Head of Department</div></td>
        </tr>
    </table>
@endsection
