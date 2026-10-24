@extends('pdf.layout')
@section('title', 'Examination Clearance Card')
@section('content')
    <div class="box center"><strong>
        This card certifies that the student named below has been cleared (fees fully paid, courses duly registered) to sit the
        {{ $semester }} examinations, {{ $session }} session. Present it with a valid ID at every examination hall.
    </strong></div>
    @include('pdf.partials-bio', ['extra' => [['Session', $session], ['Semester', $semester]]])
    <table class="grid">
        <thead><tr><th>S/N</th><th>Course</th><th>Title</th><th class="right">Units</th><th>Invigilator's Sign</th></tr></thead>
        <tbody>
            @foreach ($courses as $i => $course)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $course['code'] }}</td>
                    <td>{{ $course['title'] }}</td>
                    <td class="right">{{ $course['credit_units'] }}</td>
                    <td style="width: 22%"></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <table class="signatures">
        <tr>
            <td><div class="line">Student's Signature</div></td>
            <td><div class="line">Exams Officer (Signature &amp; Stamp)</div></td>
        </tr>
    </table>
@endsection
