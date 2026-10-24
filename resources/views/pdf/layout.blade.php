<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { margin: 32px 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #000; }
        .letterhead { text-align: center; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 12px; }
        .letterhead h1 { font-size: 16px; margin: 0; text-transform: uppercase; }
        .letterhead .motto { font-style: italic; }
        .doc-title { text-align: center; font-weight: bold; font-size: 13px; text-decoration: underline; text-transform: uppercase; margin: 6px 0 0; }
        table.bio { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.bio td { padding: 2px 4px; border-bottom: 1px dotted #888; }
        table.bio td.label { font-weight: bold; width: 18%; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 3px 5px; }
        table.grid th { background: #eee; text-align: left; }
        .right { text-align: right; }
        .center { text-align: center; }
        .signatures { width: 100%; margin-top: 48px; }
        .signatures td { text-align: center; padding: 0 12px; }
        .signatures .line { border-top: 1px solid #000; padding-top: 3px; }
        .muted { color: #444; font-size: 9px; }
        .box { border: 1px solid #000; padding: 6px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="letterhead">
        <h1>{{ $institution['name'] ?? 'Institution' }}</h1>
        @if (! empty($institution['motto']))<div class="motto">{{ $institution['motto'] }}</div>@endif
        @if (! empty($institution['address']))<div>{{ $institution['address'] }}</div>@endif
        <div class="doc-title">@yield('title')</div>
    </div>

    @yield('content')
</body>
</html>
