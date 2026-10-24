<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #000; }
        .card { width: 242pt; height: 152pt; position: relative; overflow: hidden; }
        .band { background: #0f3d2e; color: #fff; text-align: center; padding: 4pt 4pt 3pt; }
        .band .name { font-size: 7.5pt; font-weight: bold; text-transform: uppercase; }
        .band .sub { font-size: 5pt; letter-spacing: 1pt; }
        .photo { position: absolute; left: 8pt; top: 30pt; width: 56pt; height: 68pt; border: 1pt solid #0f3d2e; }
        .details { position: absolute; left: 72pt; top: 30pt; right: 6pt; font-size: 6pt; line-height: 1.35; }
        .details .label { color: #555; font-size: 4.8pt; text-transform: uppercase; }
        .details .value { font-weight: bold; margin-bottom: 2pt; }
        .matric { font-size: 8pt; font-weight: bold; }
        .valid { position: absolute; left: 8pt; bottom: 6pt; font-size: 5.5pt; }
        .back { padding: 8pt 10pt; font-size: 5.6pt; line-height: 1.4; }
        .back h3 { font-size: 7pt; margin: 0 0 3pt; text-transform: uppercase; }
        .qr { position: absolute; right: 10pt; bottom: 10pt; width: 64pt; height: 64pt; }
        .signature { position: absolute; left: 10pt; bottom: 12pt; width: 90pt; border-top: 0.5pt solid #000; font-size: 5pt; text-align: center; padding-top: 1pt; }
    </style>
</head>
<body>
    <div class="card" style="page-break-after: always;">
        <div class="band">
            <div class="name">{{ $institution['name'] ?? 'Institution' }}</div>
            <div class="sub">STUDENT IDENTITY CARD</div>
        </div>
        <img class="photo" src="{{ $photo }}" alt="Passport">
        <div class="details">
            <div class="label">Name</div><div class="value">{{ $student['name'] }}</div>
            <div class="label">Matric No</div><div class="matric">{{ $student['matric_no'] }}</div>
            <div class="label">Programme</div><div class="value">{{ $student['programme'] }}</div>
            <div class="label">Faculty / School</div><div class="value">{{ $student['faculty'] }}</div>
        </div>
        <div class="valid">Valid until <strong>{{ $validUntil }}</strong></div>
    </div>
    <div class="card">
        <div class="back">
            <h3>Conditions of use</h3>
            This card is the property of {{ $institution['name'] ?? 'the institution' }} and must be carried at all times on campus,
            presented on request, and shown at every examination hall. It is not transferable. If found, please return it to the
            Office of the Registrar{{ ! empty($institution['address']) ? ', '.$institution['address'] : '' }}.
            <br><br>Scan the QR code to verify this card online.
        </div>
        <div class="signature">Registrar</div>
        <img class="qr" src="{{ $qr }}" alt="Verification QR">
    </div>
</body>
</html>
