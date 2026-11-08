<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px 34px; }
        body { font-family: DejaVu Sans, sans-serif; color: #23384d; font-size: 10px; }
        h1 { margin: 0 0 5px; font-size: 22px; color: #173b5e; }
        h2 { margin: 20px 0 10px; font-size: 13px; color: #173b5e; }
        p { margin: 4px 0; color: #64748b; }
        .hero { border-bottom: 2px solid #dce5ed; padding-bottom: 13px; }
        .eyebrow { color: #54718c; font-size: 9px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .summary { width: 100%; margin-top: 16px; }
        .summary td { width: 25%; padding: 11px 12px; border: 1px solid #dce5ed; background: #f5f8fb; }
        .summary small { display: block; color: #657b8f; font-size: 8px; }
        .summary strong { display: block; margin-top: 5px; color: #173b5e; font-size: 17px; }
        .status-group { page-break-inside: avoid; margin: 10px 0 16px; }
        .status-title { padding: 7px 10px; background: #eaf1f7; color: #173b5e; font-size: 11px; font-weight: bold; }
        .rank-item { padding: 8px 10px 9px; border-bottom: 1px solid #e3eaf0; }
        .rank-line { width: 100%; }
        .rank-number { width: 40px; color: #2563eb; font-size: 13px; font-weight: bold; }
        .rank-name { font-size: 11px; font-weight: bold; }
        .rank-value { text-align: right; font-weight: bold; }
        .rank-meta { margin: 4px 0; font-size: 8px; }
        .bar-track { height: 7px; background: #e6edf3; }
        .bar-fill { height: 7px; background: #3b82f6; }
        .priority-card { width: 100%; margin: 8px 0; border: 1px solid #dce5ed; page-break-inside: avoid; }
        .priority-card td { padding: 8px 10px; }
        .priority-card .rank { width: 48px; color: #2563eb; font-size: 14px; font-weight: bold; }
        .priority-card .score { width: 130px; color: #173b5e; font-size: 15px; font-weight: bold; text-align: right; }
        .muted { color: #667d91; font-size: 8px; }
        .chart-box { margin-top: 10px; padding: 10px 4px 0; border: 1px solid #dce5ed; }
        .chart-svg { width: 100%; height: 300px; }
        .legend { width: 100%; margin-top: 7px; }
        .legend td { padding: 4px; color: #526a80; font-size: 8px; }
        .legend-mark { display: inline-block; width: 12px; height: 3px; margin-right: 5px; }
        .page-break { page-break-before: always; }
        .empty { margin-top: 22px; padding: 20px; background: #f5f8fb; color: #64748b; }
    </style>
</head>
<body>
    <div class="hero">
        <div class="eyebrow">RESQPERATION · IMPACT REPORT</div>
        <h1>{{ $report['title'] }}</h1>
        @if(isset($report['event_name']))<p><strong>Disaster event:</strong> {{ $report['event_name'] }}</p>@else<p>Barangay Mambaling · Completed disaster events</p>@endif
        <p>Generated: {{ $report['generated_at'] }}</p>
    </div>

    @if($report['type'] === 'ranking')
        @if($report['status_groups'])
            <h2>Household status rankings · Highest recorded quantities</h2>
            @foreach($report['status_groups'] as $group)
                @php($maximum = max(1, ...array_column($group['entries'], 'quantity')))
                <div class="status-group">
                    <div class="status-title">{{ ucfirst(str_replace('_', ' ', $group['status'])) }}</div>
                    @foreach($group['entries'] as $entry)
                        <div class="rank-item">
                            <table class="rank-line"><tr>
                                <td class="rank-number">#{{ $entry['rank'] }}</td>
                                <td class="rank-name">{{ $entry['sitio'] }}</td>
                                <td class="rank-value">{{ $entry['quantity'] }} households</td>
                            </tr></table>
                            <p class="rank-meta">Current: {{ $entry['current'] }} · Highest recorded: {{ $entry['peak_at'] }}</p>
                            <div class="bar-track"><div class="bar-fill" style="width: {{ round($entry['quantity'] * 100 / $maximum) }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
        @if($report['priority_rows'])
            <h2>Priority rankings · Highest recorded weighted score</h2>
            @foreach($report['priority_rows'] as $entry)
                @php
                    $priorityDetails = [];
                    foreach ([
                        'impact_open' => 'Unsafe',
                        'special_open' => 'Special needs unresolved',
                        'unreported_open' => 'Unreported members',
                        'no_contact_open' => 'No-contact households',
                    ] as $key => $label) {
                        if (isset($entry['metric_peaks'][$key])) {
                            $metric = $entry['metric_peaks'][$key];
                            $priorityDetails[] = $label.': '.$metric['quantity'].' ('.$metric['at'].')';
                        }
                    }
                @endphp
                <table class="priority-card"><tr>
                    <td class="rank">#{{ $entry['rank'] }}</td>
                    <td><strong>{{ $entry['sitio'] }}</strong><br><span class="muted">Current score {{ number_format($entry['current_score'], 1) }} · Peak recorded {{ $entry['peak']['at'] ?? 'No recorded measurements' }}</span><br><span class="muted">{{ implode(' · ', $priorityDetails) }}</span></td>
                    <td class="score">{{ number_format($entry['peak']['score'], 1) }} / 100</td>
                </tr></table>
            @endforeach
        @endif
        @if(!$report['status_groups'] && !$report['priority_rows'])<div class="empty">No event measurements are available to rank.</div>@endif
    @elseif($report['type'] === 'purok-impact')
        <table class="summary"><tr>
            <td><small>Registered sitios</small><strong>{{ count($report['summaries']) }}</strong></td>
            <td><small>Event</small><strong style="font-size: 12px">{{ $report['event_name'] }}</strong></td>
            <td><small>Ranking metric</small><strong style="font-size: 12px">Impact score · 0–100</strong></td>
            <td><small>Timeline</small><strong style="font-size: 12px">Recorded event measurements</strong></td>
        </tr></table>
        <h2>Sitio impact trend</h2>
        @include('reports.partials.impact-line-chart', ['chart' => $report['chart']])
        <h2>Sitio highlights</h2>
        @foreach($report['summaries'] as $summary)
            <table class="priority-card"><tr>
                <td><strong>{{ $summary['sitio'] }}</strong></td>
                <td class="score">{{ number_format($summary['peak'], 1) }} / 100</td>
                <td><span class="muted">Latest {{ number_format($summary['latest'], 1) }} · Peak at {{ $summary['peak_at'] ?? 'Not recorded' }}</span></td>
            </tr></table>
        @endforeach
        @if(!$report['summaries'])<div class="empty">No registered sitio measurements are available for this event.</div>@endif
    @else
        <table class="summary"><tr>
            <td><small>Completed disaster events</small><strong>{{ number_format($report['event_count']) }}</strong></td>
            <td><small>Assessed households</small><strong>{{ number_format($report['assessed_households']) }}</strong></td>
            <td><small>Deaths</small><strong>{{ number_format($report['totals']['deaths']) }}</strong></td>
            <td><small>Injured</small><strong>{{ number_format($report['totals']['injured']) }}</strong></td>
        </tr><tr>
            <td><small>Missing</small><strong>{{ number_format($report['totals']['missing']) }}</strong></td>
            <td><small>Damaged houses</small><strong>{{ number_format($report['totals']['damaged_houses']) }}</strong></td>
            <td colspan="2"><small>Grouped by</small><strong style="font-size: 14px">{{ ucfirst($report['period']) }}</strong></td>
        </tr></table>
        <h2>Recorded impact trend by {{ $report['period'] }}</h2>
        @include('reports.partials.impact-line-chart', ['chart' => $report['chart']])
        @if(!$report['periods'])<div class="empty">No completed disaster events are recorded.</div>@endif
    @endif
</body>
</html>
