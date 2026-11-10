<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28pt 32pt; }
        body { font-family: Helvetica, sans-serif; color: #171717; font-size: 10.5pt; line-height: 1.35; }
        h1 { margin: 4pt 0 7pt; font-size: 16pt; line-height: 1.2; color: #111111; }
        h2 { margin: 14pt 0 7pt; font-size: 12pt; color: #111111; page-break-after: avoid; }
        p { margin: 3pt 0; color: #262626; }
        .hero { border-bottom: 1pt solid #555555; padding-bottom: 9pt; }
        .hero p, .eyebrow { font-size: 9pt; color: #333333; }
        .eyebrow { font-weight: bold; }
        .report-context { margin: 9pt 0 6pt; font-size: 10pt; }
        .totals { width: 100%; border-collapse: collapse; margin: 7pt 0 10pt; }
        .totals th, .totals td { width: 25%; padding: 4pt 6pt; text-align: left; font-size: 10pt; }
        .totals th { border-top: 0.5pt solid #777777; border-bottom: 0.5pt solid #aaaaaa; font-weight: normal; }
        .totals td { border-bottom: 0.5pt solid #777777; font-weight: bold; }
        .status-group { page-break-inside: avoid; margin: 10px 0 16px; }
        .status-title { padding: 5pt 0; border-bottom: 0.5pt solid #aaaaaa; color: #171717; font-size: 10.5pt; font-weight: bold; }
        .rank-item { padding: 8px 10px 9px; border-bottom: 1px solid #e3eaf0; }
        .rank-line { width: 100%; }
        .rank-number { width: 30pt; color: #171717; font-size: 10.5pt; font-weight: bold; }
        .rank-name { font-size: 10.5pt; font-weight: bold; }
        .rank-value { text-align: right; font-weight: bold; }
        .rank-meta { margin: 3pt 0; font-size: 9pt; }
        .bar-track { height: 7px; background: #e6edf3; }
        .bar-fill { height: 7px; background: #3b82f6; }
        .priority-card { width: 100%; border-collapse: collapse; margin: 3pt 0; border-bottom: 0.5pt solid #aaaaaa; page-break-inside: avoid; }
        .priority-card td { padding: 5pt 4pt; }
        .priority-card .rank { width: 30pt; color: #171717; font-size: 10.5pt; font-weight: bold; }
        .priority-card .score { width: 90pt; color: #171717; font-size: 10.5pt; font-weight: bold; text-align: right; }
        .muted { color: #333333; font-size: 9pt; }
        .chart-box { margin-top: 6pt; padding: 4pt 0 0; }
        .chart-svg { width: 100%; height: 300px; }
        .legend { width: 100%; margin-top: 7px; }
        .legend { color: #262626; font-size: 9pt; }
        .legend-mark { display: inline-block; width: 12px; height: 3px; margin-right: 5px; }
        .page-break { page-break-before: always; }
        .empty { margin-top: 10pt; padding: 6pt 0; color: #333333; }
        .ranking-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .ranking-table th, .ranking-table td { padding: 5pt 4pt; border-bottom: 0.5pt solid #aaaaaa; text-align: left; font-size: 9pt; }
        .ranking-table th { border-top: 0.5pt solid #777777; color: #111111; }
        .ranking-table thead { display: table-header-group; }
        .ranking-table tr { page-break-inside: avoid; }
        .household-chart-section { page-break-inside: avoid; margin-bottom: 10pt; }
        .household-chart-image { width: 100%; height: auto; }
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
        @if(!empty($report['ui_rows']))
            @foreach($report['ranking_types'] as $mode)
                @include('reports.partials.impact-household-chart', ['mode' => $mode])
            @endforeach
            <h2>Ranking · Current household priority</h2>
            <table class="ranking-table">
                <thead><tr><th>Rank</th><th>Sitio</th><th>Priority</th><th>Action</th><th>Households</th><th>Unsafe</th><th>Special needs</th><th>Unchecked members</th><th>No mobile / geotag</th></tr></thead>
                <tbody>@foreach($report['ui_rows'] as $row)
                    @php
                        $bandColor = ['first' => '#6a7f94', 'high' => '#962020', 'medium' => '#8f5c18', 'low' => '#3a7d57'][$row['band']['key']] ?? '#6a7f94';
                    @endphp
                    <tr><td>{{ $row['rank'] }}</td><td><strong>{{ $row['sitio'] }}</strong></td><td style="width:140px"><div class="bar-track"><div class="bar-fill" style="width:{{ $row['priority_score'] }}%;background:{{ $bandColor }}"></div></div>{{ round($row['priority_score']) }}%</td><td style="color:{{ $bandColor }}">{{ $row['band']['label'] }}</td><td>{{ $row['households'] }}</td><td>{{ $row['impacted_households'] }}</td><td>{{ $row['special_needs_members'] }}</td><td>{{ $row['unchecked_members'] }}</td><td>{{ $row['no_contact_households'] }}</td></tr>
                @endforeach</tbody>
            </table>
        @endif
        @if($report['status_groups'])
            <h2>Household status rankings · Highest recorded quantities</h2>
            @foreach($report['status_groups'] as $group)
                @php
                    $maximum = max(1, ...array_column($group['entries'], 'quantity'));
                @endphp
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
            <table class="ranking-table">
                <thead><tr><th>Rank</th><th>Sitio</th><th>Latest recorded score</th><th>Highest recorded score</th><th>Peak recorded</th></tr></thead>
                <tbody>@foreach($report['priority_rows'] as $entry)
                    <tr><td>{{ $entry['rank'] }}</td><td>{{ $entry['sitio'] }}</td><td>{{ number_format($entry['current_score'], 1) }}%</td><td>{{ number_format($entry['peak']['score'], 1) }}%</td><td>{{ $entry['peak']['at'] ? \Illuminate\Support\Carbon::parse($entry['peak']['at'])->setTimezone('Asia/Manila')->format('d M Y, H:i') : 'Not recorded' }}</td></tr>
                @endforeach</tbody>
            </table>
            <h2>Highest recorded quantities by criterion</h2>
            <table class="ranking-table">
                <thead><tr><th>Sitio</th><th>Criterion</th><th>Highest count</th><th>Recorded</th></tr></thead>
                <tbody>@foreach($report['priority_rows'] as $entry)
                    @foreach([
                        'impact_open' => 'Unsafe',
                        'special_open' => 'Special needs unresolved',
                        'unreported_open' => 'Unreported members',
                        'no_contact_open' => 'No-contact households',
                    ] as $key => $label)
                        @if(isset($entry['metric_peaks'][$key]))
                            <tr><td>{{ $entry['sitio'] }}</td><td>{{ $label }}</td><td>{{ $entry['metric_peaks'][$key]['quantity'] }}</td><td>{{ $entry['metric_peaks'][$key]['at'] ? \Illuminate\Support\Carbon::parse($entry['metric_peaks'][$key]['at'])->setTimezone('Asia/Manila')->format('d M Y, H:i') : 'Not recorded' }}</td></tr>
                        @endif
                    @endforeach
                @endforeach</tbody>
            </table>
        @endif
        @if(!$report['status_groups'] && !$report['priority_rows'])<div class="empty">No event measurements are available to rank.</div>@endif
    @elseif($report['type'] === 'purok-impact')
        <p class="report-context"><strong>Registered sitios:</strong> {{ count($report['summaries']) }} &nbsp; · &nbsp; <strong>Metric:</strong> Impact score (0–100) &nbsp; · &nbsp; Recorded event measurements</p>
        <h2>Sitio impact trend</h2>
        @include('reports.partials.impact-line-chart', ['chart' => $report['chart']])
        <h2>Sitio highlights</h2>
        <table class="ranking-table">
            <thead><tr><th>Sitio</th><th>Peak impact</th><th>Latest impact</th><th>Peak recorded</th></tr></thead>
            <tbody>@foreach($report['summaries'] as $summary)
                <tr><td>{{ $summary['sitio'] }}</td><td>{{ number_format($summary['peak'], 1) }}%</td><td>{{ number_format($summary['latest'], 1) }}%</td><td>{{ $summary['peak_at'] ? \Illuminate\Support\Carbon::parse($summary['peak_at'])->setTimezone('Asia/Manila')->format('d M Y, H:i') : 'Not recorded' }}</td></tr>
            @endforeach</tbody>
        </table>
        @if(!$report['summaries'])<div class="empty">No registered sitio measurements are available for this event.</div>@endif
    @else
        <p class="report-context"><strong>Completed events:</strong> {{ number_format($report['event_count']) }} &nbsp; · &nbsp; <strong>Assessed households:</strong> {{ number_format($report['assessed_households']) }} &nbsp; · &nbsp; <strong>Grouping:</strong> {{ ucfirst($report['period']) }}</p>
        <table class="totals" aria-label="Overall recorded impact totals">
            <thead><tr><th>Deaths</th><th>Injured</th><th>Missing</th><th>Damaged houses</th></tr></thead>
            <tbody><tr><td>{{ number_format($report['totals']['deaths']) }}</td><td>{{ number_format($report['totals']['injured']) }}</td><td>{{ number_format($report['totals']['missing']) }}</td><td>{{ number_format($report['totals']['damaged_houses']) }}</td></tr></tbody>
        </table>
        <h2>Recorded impact trend by {{ $report['period'] }}</h2>
        @include('reports.partials.impact-line-chart', ['chart' => $report['chart']])
        @if(!$report['periods'])<div class="empty">No completed disaster events are recorded.</div>@endif
    @endif
</body>
</html>
