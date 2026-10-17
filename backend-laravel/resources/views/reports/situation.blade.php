@php
    $r = app(\App\Presenters\SituationReportDocument::class)->data($summary);
    // ---------- helpers (plain PHP, no framework features that dompdf cannot see) ----------
    $NAVY = '#14304F'; $BLUE = '#1F6FB2'; $RED = '#C0392B'; $GREEN = '#2E7D32'; $AMBER = '#B26A00';
    $dash = '—';
    $dt   = fn ($d) => $d instanceof \DateTimeInterface ? $d->format('M j, Y · g:i A') : ($d ?: $dash);
    $time = fn ($d) => $d instanceof \DateTimeInterface ? $d->format('M j · g:i A') : ($d ?: $dash);
    $num  = fn ($v, $d = 0) => is_numeric($v) ? number_format($v, $d) : ($v === null || $v === '' ? $dash : $v);
    $statusColor = [
        'Active' => $GREEN, 'Dispatched' => $GREEN, 'Delivered' => $GREEN,
        'Saved' => $BLUE, 'Forwarded' => $BLUE,
        'Needs Validation' => $AMBER, 'Full' => $RED,
    ];
    $w = $r['weather']; $p = $r['population']; $c = $r['casualties']; $d = $r['damage'];
    $checked = ($p['in_scope'] ?? 0) > 0 ? round(($p['reported'] ?? 0) / $p['in_scope'] * 100) : 0;
    $timeline = collect($r['timeline'] ?? []);
    $teams = collect($r['teams'] ?? []);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Situation Report - {{ $r['disaster_type'] }}</title>
<style>
    /* Page: A4, margins match the Word file (0.5in on every side) */
    @page { margin: 0.5in; }
    * { box-sizing: border-box; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #222B36; margin: 0; padding: 0; }
    table { border-collapse: collapse; border-spacing: 0; }
    td, th { vertical-align: middle; padding: 0; }

    .gov-1 { text-align: center; font-size: 8.5pt; font-weight: bold; color: #5F6B7A; letter-spacing: 2px; }
    .gov-2 { text-align: center; font-size: 10pt; font-weight: bold; color: {{ $NAVY }}; margin-top: 2px; }
    .gov-3 { text-align: center; font-size: 9pt; color: #5F6B7A; margin: 2px 0 8px 0; }

    .banner td { background: {{ $NAVY }}; text-align: center; padding: 10px 10px; }
    .banner-title { font-size: 22pt; font-weight: bold; color: #FFFFFF; letter-spacing: 1.5px; }
    .banner-sub { font-size: 11pt; color: #CFE0F2; margin-top: 3px; }

    .info td { background: #F2F5F9; border: 1px solid #D5DCE5; padding: 6px 8px; width: 33.33%; }
    .info td.alert { background: {{ $RED }}; border: 1px solid #D5DCE5; }
    .lbl { font-size: 7pt; font-weight: bold; color: #5F6B7A; text-transform: uppercase; letter-spacing: 0.5px; }
    .val { font-size: 11pt; font-weight: bold; color: {{ $NAVY }}; margin-top: 2px; }
    .alert .lbl, .alert .val { color: #FFFFFF; }

    .period td { background: #E3EEF8; padding: 6px 8px; }
    .period .k { width: 110px; font-size: 7.5pt; font-weight: bold; color: {{ $BLUE }}; text-transform: uppercase; }
    .period .v { font-size: 10pt; font-weight: bold; color: {{ $NAVY }}; }

    h1.sec { font-size: 12pt; font-weight: bold; color: {{ $NAVY }}; text-transform: uppercase;
             border-bottom: 1.5px solid {{ $BLUE }}; padding: 0 0 4px 0; margin: 18px 0 8px 0; page-break-after: avoid; }
    h1.sec .n { color: {{ $BLUE }}; font-size: 13pt; margin-right: 6px; }
    h2.sub { font-size: 10.5pt; font-weight: bold; color: {{ $NAVY }}; margin: 12px 0 5px 0; page-break-after: avoid; }

    /* stat cards: white side borders create the gaps (same trick as the Word file) */
    .card { background: #F2F5F9; text-align: center; padding: 4px 4px 3px 4px;
            border-left: 3px solid #FFFFFF; border-right: 3px solid #FFFFFF; border-bottom: 2px solid #FFFFFF; }
    .card-value { font-weight: bold; line-height: 1.15; padding-top: 3px; }
    .card-label { font-size: 7.5pt; font-weight: bold; color: #5F6B7A; text-transform: uppercase; padding-bottom: 3px; }

    .note { font-size: 8pt; color: #5F6B7A; font-style: italic; margin: 5px 0 5px 0; }
    .advisory td { background: #FFF4D6; border-left: 4px solid #E0A800; padding: 8px 10px; font-size: 10pt; color: #3D3000; }
    .advisory b { font-size: 9pt; color: {{ $AMBER }}; }
    .progress td { background: #E9EDF2; padding: 4px 8px; font-size: 9.5pt; color: #5F6B7A; }
    .progress td.k { background: #FFFFFF; width: 130px; font-size: 7.5pt; font-weight: bold; padding-left: 0; text-transform: uppercase; }
    .progress b { color: {{ $NAVY }}; }

    /* data tables */
    .data { width: 100%; }
    .data th { background: {{ $NAVY }}; color: #FFFFFF; font-size: 8pt; text-transform: uppercase; text-align: left;
               padding: 6px 7px; border: 1px solid {{ $NAVY }}; }
    .data td { font-size: 9.5pt; padding: 6px 7px; border: 1px solid #D5DCE5; background: #FFFFFF; }
    .data tr.alt td { background: #F2F5F9; }
    .data tr { page-break-inside: avoid; }
    .data thead { display: table-header-group; }
    .c { text-align: center !important; }
    .empty { text-align: center; font-style: italic; color: #5F6B7A; padding: 12px 7px !important; }
    .st { font-weight: bold; }

    .sig td.line { border-top: 1px solid {{ $NAVY }}; padding-top: 3px; font-size: 7.5pt; font-weight: bold; color: #5F6B7A; text-transform: uppercase; }

    /* footer (fixed, repeats on every page) */
    .footer { position: fixed; left: 0; right: 0; bottom: -0.35in; height: 0.25in; border-top: 1px solid #D5DCE5; font-size: 7.5pt; color: #5F6B7A; }
    .footer td { padding-top: 5px; font-size: 7.5pt; color: #5F6B7A; }
    .pn:before { content: counter(page); }

    .keep { page-break-inside: avoid; }
    .population { page-break-inside: auto; }
    .population .data td, .population .data th { font-size: 7pt; padding: 5px 3px; }
.household-details td,.household-details th{font-size:7pt;padding:5px 3px;overflow-wrap:break-word}
</style>
</head>
<body>

<div class="footer">
    <table width="100%"><tr>
        <td>Situation Report · {{ $r['disaster_type'] }} · Data as of {{ $dt($r['period_end'] ?? null) }}</td>
        <td style="text-align:right;">Page <span class="pn"></span></td>
    </tr></table>
</div>

{{-- ============ HEADER ============ --}}
<div class="gov-1">REPUBLIC OF THE PHILIPPINES</div>
<div class="gov-2">Barangay Disaster Risk Reduction and Management Committee</div>
<div class="gov-3">Emergency Operations Center · {{ $r['barangay'] }}</div>

<table class="banner" width="100%"><tr><td>
    <div class="banner-title">SITUATION REPORT</div>
    <div class="banner-sub">{{ $r['disaster_type'] }} &nbsp;·&nbsp; Event: {{ $r['disaster_name'] }}</div>
</td></tr></table>

<div class="note">Report {{ $r['report_number'] }} @if($r['scope_note']) · {{ $r['scope_note'] }} @endif</div>
<table class="info" width="100%">
    <tr>
        <td><div class="lbl">Disaster type</div><div class="val">{{ $r['disaster_type'] }}</div></td>
        <td class="alert"><div class="lbl">Alert level</div><div class="val">{{ strtoupper($r['alert_level'] ?? 'Not recorded') }}</div></td>
        <td><div class="lbl">Situation status</div><div class="val">{{ $r['situation_status'] ?? $r['status'] }}</div></td>
    </tr>
    <tr>
        <td><div class="lbl">Date declared</div><div class="val">{{ $dt($r['declared_at']) }}</div></td>
        <td><div class="lbl">Date finished</div><div class="val">{{ $r['finished_at'] instanceof \DateTimeInterface ? $dt($r['finished_at']) : ($r['finished_at'] ?: 'Ongoing') }}</div></td>
        <td><div class="lbl">Area coverage</div><div class="val">{{ $r['coverage'] }}</div></td>
    </tr>
</table>
<div style="height:4px;"></div>
<table class="period" width="100%"><tr>
    <td class="k">Reporting period</td>
    <td class="v">{{ $dt($r['period_start']) }} &nbsp;to&nbsp; {{ $dt($r['period_end']) }}</td>
</tr></table>

{{-- ============ I ============ --}}
@if(in_array('I', $r['included_sections'], true))
<div class="keep">
<h1 class="sec"><span class="n">I</span> Situation Overview</h1>
@include('reports.partials.cards', ['items' => [
    ['value' => $w['condition'] ?? $dash, 'label' => 'Condition', 'color' => $BLUE, 'small' => true],
    ['value' => $w['wind_display'], 'label' => 'Wind', 'color' => $BLUE, 'small' => true],
    ['value' => $w['rain_display'], 'label' => 'Rainfall', 'color' => $BLUE, 'small' => true],
    ['value' => $w['temp_display'], 'label' => 'Temperature', 'color' => $BLUE, 'small' => true],
]])
<div class="note">Source: {{ $w['source'] ?? $dash }}</div>
<table class="advisory" width="100%"><tr><td><b>ADVISORY</b> &nbsp;{{ $w['advisory'] ?? '' }}</td></tr></table>
</div>

@endif

{{-- ============ II ============ --}}
@if(in_array('II', $r['included_sections'], true))
<div class="population">
<h1 class="sec"><span class="n">II</span> Affected Population</h1>
@include('reports.partials.cards', ['items' => [
    ['value' => $num($p['affected']),  'label' => 'Total HH affected', 'color' => $NAVY],
    ['value' => $num($p['safe']),      'label' => 'Safe total',        'color' => $GREEN],
    ['value' => $num($p['evacuated']), 'label' => 'Evacuated',         'color' => $BLUE],
    ['value' => $num($p['unsafe']),    'label' => 'Unsafe / at risk',  'color' => $RED],
    ['value' => $num($p['unchecked']), 'label' => 'Unchecked',         'color' => $AMBER],
]])
<div style="height:8px;"></div>
<table class="progress" width="100%"><tr>
    <td class="k">Reporting progress</td>
    <td><b>{{ $checked }}% checked</b> &nbsp;·&nbsp; {{ $num($p['reported']) }} / {{ $num($p['in_scope']) }} households</td>
</tr></table>
<div class="note">Affected households = unsafe + evacuated/relocated. Persons reflect registered household members.</div>
<div style="height:8px;"></div>
<table class="data">
    <thead><tr>
        <th>Sitio / Purok</th><th class="c">HH in scope</th><th class="c">Persons</th><th class="c">Affected HH</th><th class="c">Affected persons</th><th class="c" style="width:14.6%;">Safe</th>
        <th class="c" style="width:14.6%;">Evacuated</th><th class="c" style="width:14.6%;">Unsafe</th><th class="c" style="width:14.6%;">Unchecked</th>
    </tr></thead>
    <tbody>
    @forelse (($r['puroks'] ?? []) as $i => $pk)
        <tr class="{{ $i % 2 ? 'alt' : '' }}">
            <td>{{ $pk['sitio'] }} / {{ $pk['name'] }}</td>
            <td class="c">{{ $num($pk['households']) }}</td><td class="c">{{ $num($pk['persons']) }}</td><td class="c">{{ $num($pk['affected']) }}</td><td class="c">{{ $num($pk['affected_persons']) }}</td><td class="c">{{ $num($pk['safe']) }}</td>
            <td class="c">{{ $num($pk['evacuated']) }}</td><td class="c">{{ $num($pk['unsafe']) }}</td><td class="c">{{ $num($pk['unchecked']) }}</td>
        </tr>
    @empty
        <tr><td colspan="9" class="empty">No purok-level data linked to this event.</td></tr>
    @endforelse
    </tbody>
</table>
</div>

@include('reports.partials.household-details', ['householdSection' => 'II'])
@endif

{{-- ============ III ============ --}}
@if(in_array('III', $r['included_sections'], true))
<div class="keep">
<h1 class="sec"><span class="n">III</span> Casualties and Immediate Needs</h1>
@include('reports.partials.cards', ['items' => [
    ['value' => $num($c['deaths']),  'label' => 'Deaths',  'color' => $NAVY],
    ['value' => $num($c['missing']), 'label' => 'Missing', 'color' => $AMBER],
    ['value' => $num($c['injured']), 'label' => 'Injured', 'color' => $RED],
    ['value' => $num($c['rescued']), 'label' => 'Rescued', 'color' => $GREEN],
]])
</div>

@include('reports.partials.household-details', ['householdSection' => 'III'])
@endif

{{-- ============ V ============ --}}
@if(in_array('V', $r['included_sections'], true))
<h1 class="sec"><span class="n">V</span> Rescue Operations Timeline</h1>
<table class="data">
    <thead><tr>
        <th style="width:16%;">Date / time</th><th style="width:22%;">Activity</th><th style="width:17%;">Actor</th><th style="width:13%;">Status</th><th style="width:32%;">Detail</th>
    </tr></thead>
    <tbody>
    @forelse ($timeline as $i => $t)
        <tr class="{{ $i % 2 ? 'alt' : '' }}">
            <td>{{ $time($t['logged_at'] ?? null) }}</td>
            <td>{{ $t['category'] }}</td>
            <td>{{ $t['actor'] ?? ($t['title'] ?? $dash) }}</td>
            <td class="st" style="color: {{ $statusColor[$t['status'] ?? ''] ?? '#222B36' }};">{{ $t['status'] ?? '' }}</td>
            <td>{{ $t['detail'] }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="empty">No rescue operations logged.</td></tr>
    @endforelse
    </tbody>
</table>

<h2 class="sub">Team Deployments and Outcomes</h2>
<table class="data">
    <thead><tr>
        <th style="width:17%;">Team</th><th style="width:12%;">Deployed</th><th style="width:23%;">Area</th>
        <th class="c" style="width:8%;">Safe</th><th class="c" style="width:8%;">Evac</th><th class="c" style="width:10%;">Unsafe</th>
        <th class="c" style="width:11%;">Injured</th><th class="c" style="width:11%;">Missing</th>
    </tr></thead>
    <tbody>
    @forelse ($teams as $i => $t)
        <tr class="{{ $i % 2 ? 'alt' : '' }}">
            <td>{{ $t['team'] }}</td><td>{{ $time($t['deployed_at'] ?? null) }}</td><td>{{ $t['area'] }}</td>
            <td class="c">{{ $num($t['safe']) }}</td><td class="c">{{ $num($t['evacuated']) }}</td><td class="c">{{ $num($t['unsafe']) }}</td>
            <td class="c">{{ $num($t['injured']) }}</td><td class="c">{{ $num($t['missing']) }}</td>
        </tr>
    @empty
        <tr><td colspan="8" class="empty">No team deployments recorded.</td></tr>
    @endforelse
    </tbody>
</table>

@endif

{{-- ============ VI ============ --}}
@if(in_array('VI', $r['included_sections'], true))
<div class="keep">
<h1 class="sec"><span class="n">VI</span> Initial Damage Assessment</h1>
@include('reports.partials.cards', ['items' => [
    ['value' => $num($d['partial']), 'label' => 'Partially damaged houses', 'color' => $AMBER],
    ['value' => $num($d['total']),   'label' => 'Totally damaged houses',   'color' => $RED],
    ['value' => is_numeric($d['cost'] ?? null) ? 'PHP ' . number_format($d['cost'], 2) : $dash, 'label' => 'Estimated damage cost', 'color' => $NAVY, 'small' => is_numeric($d['cost'] ?? null)],
]])
</div>

@include('reports.partials.household-details', ['householdSection' => 'VI'])
@endif

{{-- ============ VII ============ --}}
@if(in_array('VII', $r['included_sections'], true))
<h1 class="sec"><span class="n">VII</span> Resources Deployed and Requests</h1>
<table class="data">
    <thead><tr>
        <th style="width:31%;">Item</th><th class="c" style="width:17%;">Quantity</th><th style="width:24%;">Source</th><th style="width:28%;">Status</th>
    </tr></thead>
    <tbody>
    @forelse (($r['resources'] ?? []) as $i => $x)
        <tr class="{{ $i % 2 ? 'alt' : '' }}">
            <td>{{ $x['item'] }}</td><td class="c">{{ $num($x['quantity']) }}</td><td>{{ $x['source'] }}</td>
            <td class="st" style="color: {{ $statusColor[$x['status']] ?? '#222B36' }};">{{ $x['status'] }}</td>
        </tr>
    @empty
        <tr><td colspan="4" class="empty">No resources recorded.</td></tr>
    @endforelse
    </tbody>
</table>

@endif

{{-- ============ VIII ============ --}}
@if(in_array('VIII', $r['included_sections'], true))
<div class="keep">
<h1 class="sec"><span class="n">VIII</span> Actions Taken and Recommendations</h1>
<table class="data">
    <thead><tr><th>Actions taken and recommendations</th></tr></thead>
    <tbody><tr>
        @if (!empty($r['actions']))
            <td style="padding: 10px 8px;">{!! nl2br(e($r['actions'])) !!}</td>
        @else
            <td class="empty">No actions or recommendations recorded yet.</td>
        @endif
    </tr></tbody>
</table>

</div>
@endif
<div class="keep">
<div style="height:34px;"></div>
<table class="sig" width="100%"><tr>
    <td class="line" style="width:47%;">{{ $r['prepared_by'] ?: '—' }}<br />Prepared by</td>
    <td style="width:6%;"></td>
    <td class="line" style="width:47%;">{{ $r['reviewed_by'] ?: '—' }}<br />Reviewed by</td>
</tr></table>
</div>

</body>
</html>
