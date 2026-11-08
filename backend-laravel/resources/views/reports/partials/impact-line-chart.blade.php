@php
    $width = 900;
    $height = 320;
    $left = 54;
    $right = 18;
    $top = 12;
    $bottom = 52;
    $plotWidth = $width - $left - $right;
    $plotHeight = $height - $top - $bottom;
    $values = collect($chart['series'])->flatMap(fn ($series) => $series['values'])->filter(fn ($value) => $value !== null)->values();
    $maxValue = max(1, (float) ($chart['max_value'] ?? $values->max() ?? 1));
    $labels = $chart['labels'];
    $lastIndex = max(1, count($labels) - 1);
    $ticks = min(5, max(1, count($labels)));
    $suffix = ($chart['max_value'] ?? null) === 100 ? '%' : '';
    $xValues = $chart['x_values'] ?? array_keys($labels);
    $xAxisMaximum = isset($chart['x_values']) ? max(1, (float) max($xValues)) : null;
@endphp
<div class="chart-box">
    @if(!$labels || !$chart['series'])
        <div class="empty">No recorded measurements are available to draw this chart.</div>
    @else
        <svg class="chart-svg" viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="{{ $report['title'] }} trend chart">
            @for($tick = 0; $tick <= 4; $tick++)
                @php
                    $y = $top + $plotHeight * $tick / 4;
                    $value = $maxValue * (4 - $tick) / 4;
                @endphp
                <line x1="{{ $left }}" y1="{{ $y }}" x2="{{ $width - $right }}" y2="{{ $y }}" stroke="#dce5ed" stroke-width="1" />
                <text x="{{ $left - 8 }}" y="{{ $y + 3 }}" text-anchor="end" fill="#657b8f" font-size="9">{{ number_format($value, 0) }}{{ $suffix }}</text>
            @endfor
            @foreach($chart['series'] as $series)
                @php
                    $segments = [];
                    $segment = [];
                    foreach ($series['values'] as $index => $value) {
                        if ($value === null) {
                            if ($segment) $segments[] = $segment;
                            $segment = [];
                            continue;
                        }
                        $x = $left + $plotWidth * (isset($chart['x_values']) ? (float) $xValues[$index] / $xAxisMaximum : $index / $lastIndex);
                        $y = $top + $plotHeight * (1 - (float) $value / $maxValue);
                        $segment[] = round($x, 2).','.round($y, 2);
                    }
                    if ($segment) $segments[] = $segment;
                @endphp
                @foreach($segments as $segment)
                    @if(count($segment) > 1)
                        <polyline points="{{ implode(' ', $segment) }}" fill="none" stroke="{{ $series['color'] }}" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" />
                    @endif
                @endforeach
                @if($segments)
                    @foreach($series['values'] as $index => $value)
                        @if($value !== null)
                            @php
                                $x = $left + $plotWidth * (isset($chart['x_values']) ? (float) $xValues[$index] / $xAxisMaximum : $index / $lastIndex);
                                $y = $top + $plotHeight * (1 - (float) $value / $maxValue);
                            @endphp
                            <circle cx="{{ $x }}" cy="{{ $y }}" r="2.2" fill="{{ $series['color'] }}" />
                        @endif
                    @endforeach
                @endif
            @endforeach
            @if(isset($chart['x_values']))
                @for($tick = 0; $tick <= 4; $tick++)
                    @php
                        $x = $left + $plotWidth * $tick / 4;
                        $label = number_format($xAxisMaximum * $tick / 4, 2).'h';
                    @endphp
                    <text x="{{ $x }}" y="{{ $height - 24 }}" text-anchor="middle" fill="#657b8f" font-size="8">{{ $label }}</text>
                @endfor
            @else
                @for($tick = 0; $tick < $ticks; $tick++)
                    @php
                        $index = $ticks === 1 ? 0 : (int) round($tick * (count($labels) - 1) / ($ticks - 1));
                        $x = $left + $plotWidth * $index / $lastIndex;
                    @endphp
                    <text x="{{ $x }}" y="{{ $height - 24 }}" text-anchor="middle" fill="#657b8f" font-size="8">{{ $labels[$index] }}</text>
                @endfor
            @endif
            <text x="{{ $left + $plotWidth / 2 }}" y="{{ $height - 5 }}" text-anchor="middle" fill="#526a80" font-size="9">{{ $chart['axis_label'] }}</text>
            <text x="12" y="{{ $top + $plotHeight / 2 }}" text-anchor="middle" fill="#526a80" font-size="9" transform="rotate(-90 12 {{ $top + $plotHeight / 2 }})">{{ $chart['value_label'] }}</text>
        </svg>
        <div class="legend">
            @foreach($chart['series'] as $series)
                <span style="display:inline-block;margin:3px 12px 3px 0;color:#526a80;font-size:8px">
                    <i class="legend-mark" style="background:{{ $series['color'] }}"></i>{{ $series['label'] }}
                </span>
            @endforeach
        </div>
    @endif
</div>
