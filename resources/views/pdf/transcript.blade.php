<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Transcript {{ $transcript->number }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #000; }
        h1 { font-size: 16px; text-align: center; margin: 0; text-transform: uppercase; }
        .motto { text-align: center; font-style: italic; margin: 2px 0; }
        .title { text-align: center; font-size: 13px; font-weight: bold; text-decoration: underline; margin: 8px 0 10px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .meta td { padding: 2px 4px; }
        .meta td.label { font-weight: bold; width: 18%; }
        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 2px 4px; }
        table.grid th { background: #eee; text-align: left; }
        .right { text-align: right; }
        .center { text-align: center; }
        .semester { font-weight: bold; margin: 8px 0 2px; }
        .summary { margin: 0 0 6px; }
        .final { border: 1px solid #000; padding: 6px; margin-top: 10px; }
        .key td { border: 1px solid #000; padding: 1px 4px; }
        .signature { margin-top: 36px; width: 100%; }
        .signature td { width: 50%; text-align: center; padding-top: 24px; }
        .line { border-top: 1px solid #000; display: inline-block; width: 70%; padding-top: 2px; }
        .footer { position: fixed; bottom: -10px; left: 0; right: 0; font-size: 8px; text-align: center; color: #444; }
    </style>
</head>
<body>
    <h1>{{ $institution?->name ?? 'Institution' }}</h1>
    @if ($institution?->motto)
        <div class="motto">{{ $institution->motto }}</div>
    @endif
    @if ($institution?->address)
        <div class="center">{{ $institution->address }}</div>
    @endif
    <div class="title">Official Academic Transcript</div>

    <table class="meta">
        <tr>
            <td class="label">Name:</td><td>{{ $student->user->name }}</td>
            <td class="label">Matric No:</td><td>{{ $student->matric_no }}</td>
        </tr>
        <tr>
            <td class="label">Programme:</td><td>{{ $student->programme->name }}</td>
            <td class="label">Department:</td><td>{{ $student->programme->department->name }}</td>
        </tr>
        <tr>
            <td class="label">Faculty/School:</td><td>{{ $student->programme->department->faculty->name }}</td>
            <td class="label">Year of Entry:</td><td>{{ $student->entrySession?->name }}</td>
        </tr>
        <tr>
            <td class="label">Gender:</td><td>{{ ucfirst((string) $student->gender) }}</td>
            <td class="label">Date of Birth:</td><td>{{ $student->date_of_birth?->toFormattedDateString() }}</td>
        </tr>
        @if ($student->graduated_on)
            <tr>
                <td class="label">Graduated:</td><td>{{ $student->graduated_on->toFormattedDateString() }}</td>
                <td></td><td></td>
            </tr>
        @endif
    </table>

    @forelse ($record['semesters'] as $semester)
        <div class="semester">{{ $semester['session'] }} — {{ $semester['semester'] }}</div>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 12%">Code</th>
                    <th>Course Title</th>
                    <th class="right" style="width: 8%">Units</th>
                    <th class="center" style="width: 8%">Grade</th>
                    <th class="right" style="width: 8%">GP</th>
                    <th class="right" style="width: 8%">QP</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($semester['courses'] as $course)
                    <tr>
                        <td>{{ $course['code'] }}</td>
                        <td>{{ $course['title'] }}@if ($course['is_carryover']) (repeat)@endif</td>
                        <td class="right">{{ $course['credit_units'] }}</td>
                        <td class="center">{{ $course['grade_letter'] }}</td>
                        <td class="right">{{ $course['grade_point'] }}</td>
                        <td class="right">{{ $course['quality_points'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="summary">
            Units: {{ $semester['total_units'] }}
            @if ($semester['gpa']) &nbsp;|&nbsp; GPA: {{ $semester['gpa'] }} @endif
            @if ($semester['cgpa']) &nbsp;|&nbsp; CGPA: {{ $semester['cgpa'] }} @endif
        </div>
    @empty
        <p>No approved results on record.</p>
    @endforelse

    <div class="final">
        <strong>Cumulative units:</strong> {{ $record['cumulative_units'] }}
        &nbsp;&nbsp; <strong>CGPA:</strong> {{ $record['cgpa'] ?? '—' }}
        @if ($record['classification'])
            &nbsp;&nbsp; <strong>Class:</strong> {{ $record['classification'] }}
        @endif
    </div>

    <p style="margin: 8px 0 2px;"><strong>Key to grades</strong> (GPA = Σ quality points ÷ Σ registered units)</p>
    <table class="key" style="border-collapse: collapse;">
        <tr>
            @foreach ($record['grade_key'] as $band)
                <td>{{ $band['letter'] }}: {{ $band['range'] }} = {{ $band['point'] }}</td>
            @endforeach
        </tr>
    </table>

    <table class="signature">
        <tr>
            <td><span class="line">Date of issue: {{ $issuedOn }}</span></td>
            <td><span class="line">Registrar</span></td>
        </tr>
    </table>

    <div class="footer">
        Transcript {{ $transcript->number }} issued to {{ $transcript->recipient_name }}.
        Verify with the Exams &amp; Records office quoting this number. Not valid if altered.
    </div>
</body>
</html>
