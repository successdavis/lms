<table class="bio">
    <tr><td class="label">Name:</td><td>{{ $student['name'] }}</td><td class="label">Matric No:</td><td>{{ $student['matric_no'] }}</td></tr>
    <tr><td class="label">Faculty/School:</td><td>{{ $student['faculty'] }}</td><td class="label">Department:</td><td>{{ $student['department'] }}</td></tr>
    <tr><td class="label">Programme:</td><td>{{ $student['programme'] }}</td><td class="label">Level:</td><td>{{ $student['level'] }}</td></tr>
    @foreach ($extra ?? [] as $pair)
        <tr><td class="label">{{ $pair[0] }}:</td><td colspan="3">{{ $pair[1] }}</td></tr>
    @endforeach
</table>
