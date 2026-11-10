@php
    $status = $mode === 'status';
    $svgFont = str_replace('\\', '/', app('dompdf')->getFontMetrics()->getFont('Helvetica'));
    $rows = $report['ui_rows'];
    $longestName = max(1, ...array_map(fn ($row) => mb_strlen($row['sitio']), $rows));
    $left = max(65, min(220, $longestName * 5 + 10));
    $plotWidth = 980 - $left;
    $slotWidth = $plotWidth / max(1, count($rows));
    $barWidth = min(36, $slotWidth * 0.65);
    $height = max(360, 250 + $longestName * 5);
    $keys = $status
        ? ['unsafe' => ['Unsafe', '#962020'], 'unchecked' => ['Unchecked', '#6a7f94'], 'evacuated' => ['Evacuated', '#2a5a90'], 'safe' => ['Safe', '#3a7d57']]
        : ['impact' => ['Unsafe households', '#962020'], 'special_needs' => ['Special needs', '#8f5c18'], 'unreported' => ['Unchecked', '#6a7f94'], 'no_contact' => ['No mobile / geotag', '#1b3050']];
@endphp
<div class="household-chart-section">
    <h2>{{ $status ? 'Household status by sitio' : 'Priority by sitio' }}</h2>
    @php ob_start(); @endphp
    <svg xmlns="http://www.w3.org/2000/svg" font-family="{{ $svgFont }}" width="1000" height="{{ $height }}" viewBox="0 0 1000 {{ $height }}">
        @foreach([0, 25, 50, 75, 100] as $tick)
            <line x1="{{ $left }}" y1="{{ 230 - 2.1 * $tick }}" x2="980" y2="{{ 230 - 2.1 * $tick }}" stroke="#dce5ed" />
            <text x="{{ $left - 8 }}" y="{{ 234 - 2.1 * $tick }}" text-anchor="end" fill="#262626" font-size="12">{{ $tick }}%</text>
        @endforeach
        @foreach($rows as $index => $row)
            @php $x = $left + $slotWidth * ($index + .5); $bottom = 230; @endphp
            @foreach($keys as $key => [$label, $color])
                @php
                    $value = $status ? ($row['households'] ? 100 * ($row['status_counts'][$key] ?? 0) / $row['households'] : 0) : ($row['contributions'][$key] ?? 0);
                    $barHeight = 2.1 * $value;
                    $bottom -= $barHeight;
                @endphp
                <rect x="{{ $x - $barWidth / 2 }}" y="{{ $bottom }}" width="{{ $barWidth }}" height="{{ $barHeight }}" fill="{{ $color }}" />
            @endforeach
            <text x="{{ $x }}" y="247" text-anchor="end" transform="rotate(-45 {{ $x }} 247)" fill="#262626" font-size="12">{{ $row['sitio'] }}</text>
        @endforeach
    </svg>
    @php $svg = ob_get_clean(); @endphp
    <img class="household-chart-image" alt="{{ $status ? 'Stacked household status percentages' : 'Stacked priority contributions' }} by sitio" src="data:image/svg+xml;base64,{{ base64_encode($svg) }}">
<div class="legend">
    @foreach($keys as [$label, $color])
        <span style="margin-right:16px"><i class="legend-mark" style="background:{{ $color }}"></i>{{ $label }}</span>
    @endforeach
</div>
</div>
