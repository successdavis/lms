@extends('pdf.layout')

@section('title', $title)

@section('content')
    <table class="bio">
        @foreach ($meta as $label => $value)
            <tr><td class="label">{{ $label }}:</td><td>{{ $value }}</td></tr>
        @endforeach
        <tr><td class="label">Generated:</td><td>{{ $generatedAt }}</td></tr>
    </table>

    @foreach ($tables as $table)
        <div style="page-break-inside: avoid; margin-top: 12px;">
            <div style="font-weight: bold; margin-bottom: 3px;">{{ $table['title'] }}</div>
            @if (count($table['rows']) === 0)
                <div class="muted">No data for this selection.</div>
            @else
                <table class="grid">
                    <thead>
                        <tr>
                            @foreach ($table['columns'] as $i => $column)
                                <th @class(['right' => $i > 0])>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($table['rows'] as $row)
                            <tr>
                                @foreach ($row as $i => $cell)
                                    <td @class(['right' => $i > 0 && is_numeric($cell)])>{{ is_float($cell) ? number_format($cell, 2) : (is_int($cell) ? number_format($cell) : $cell) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach
@endsection
