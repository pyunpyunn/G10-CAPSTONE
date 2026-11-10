@php
    $width = 900;
    $svgFont = str_replace('\\', '/', app('dompdf')->getFontMetrics()->getFont('Helvetica'));
    $height = 320;
    $left = 54;
    $right = 18;
    $top = 12;
    $bottom = 52;
    $plotWidth = $width - $left - $right;
    $plotHeight = $height - $top - $bottom;
    $values = collect($chart['series'])->flatMap(fn ($series) => $series['values'])->filter(fn ($value) => $value !== null)->values();
    $maxValue = max(5, (float) ($chart['max_value'] ?? $values->max() ?? 5));
    $labels = $chart['labels'];
    $lastIndex = max(1, count($labels) - 1);
    $ticks = min(5, max(1, count($labels)));
    $suffix = ($chart['max_value'] ?? null) === 100 ? '%' : '';
    $yTicks = $suffix ? 10 : min(5, (int) $maxValue);
    $xValues = $chart['x_values'] ?? array_keys($labels);
    $xAxisMaximum = isset($chart['x_values']) ? (float) ($chart['x_max_value'] ?? max(1 / 3600, (float) max($xValues ?: [0]))) : null;
@endphp
<div class="chart-box">
    @if(!$labels || !$chart['series'])
        <div class="empty">No recorded measurements are available to draw this chart.</div>
    @else
        @php ob_start(); @endphp
        <svg xmlns="http://www.w3.org/2000/svg" font-family="{{ $svgFont }}" width="900" height="320" viewBox="0 0 {{ $width }} {{ $height }}">
            @for($tick = 0; $tick <= $yTicks; $tick++)
                @php
                    $y = $top + $plotHeight * $tick / $yTicks;
                    $value = $maxValue * ($yTicks - $tick) / $yTicks;
                @endphp
                <line x1="{{ $left }}" y1="{{ $y }}" x2="{{ $width - $right }}" y2="{{ $y }}" stroke="#dce5ed" stroke-width="1" />
                <text x="{{ $left - 8 }}" y="{{ $y + 3 }}" text-anchor="end" fill="#262626" font-size="12">{{ number_format($value, 0) }}{{ $suffix }}</text>
            @endfor
            @foreach($chart['series'] as $series)
                @php
                    $seriesX = isset($series['samples']) ? array_column($series['samples'], 'x') : $xValues;
                    $seriesValues = isset($series['samples']) ? array_column($series['samples'], 'y') : $series['values'];
                    $segments = [];
                    $segment = [];
                    foreach ($seriesValues as $index => $value) {
                        if ($value === null) {
                            if ($segment) $segments[] = $segment;
                            $segment = [];
                            continue;
                        }
                        $x = $left + $plotWidth * (isset($chart['x_values']) ? (float) $seriesX[$index] / $xAxisMaximum : $index / $lastIndex);
                        $y = $top + $plotHeight * (1 - (float) $value / $maxValue);
                        $segment[] = round($x, 2).','.round($y, 2);
                    }
                    if ($segment) $segments[] = $segment;
                @endphp
                @foreach($segments as $segment)
                    @if(count($segment) > 1)
                        @php
                            $coords = array_map(fn ($pair) => array_map('floatval', explode(',', $pair)), $segment);
                            $slopes = [];
                            for ($i = 0; $i < count($coords) - 1; $i++) {
                                $dx = $coords[$i + 1][0] - $coords[$i][0];
                                $slopes[] = $dx ? ($coords[$i + 1][1] - $coords[$i][1]) / $dx : 0;
                            }
                            $tangents = [$slopes[0]];
                            for ($i = 1; $i < count($coords) - 1; $i++) {
                                $a = $slopes[$i - 1]; $b = $slopes[$i];
                                $tangents[] = $a * $b <= 0 ? 0 : ($a + $b) / 2;
                            }
                            $tangents[] = $slopes[array_key_last($slopes)];
                            foreach ($slopes as $i => $slope) {
                                if ($slope == 0) { $tangents[$i] = $tangents[$i + 1] = 0; continue; }
                                $radius = hypot($tangents[$i] / $slope, $tangents[$i + 1] / $slope);
                                if ($radius > 3) { $tangents[$i] *= 3 / $radius; $tangents[$i + 1] *= 3 / $radius; }
                            }
                            $path = 'M'.$segment[0];
                            foreach ($slopes as $i => $slope) {
                                [$x0, $y0] = $coords[$i]; [$x1, $y1] = $coords[$i + 1];
                                $dx = ($x1 - $x0) / 3;
                                $path .= ' C'.($x0 + $dx).','.($y0 + $dx * $tangents[$i]).' '.($x1 - $dx).','.($y1 - $dx * $tangents[$i + 1]).' '.$x1.','.$y1;
                            }
                        @endphp
                        <path d="{{ $path }}" fill="none" stroke="{{ $series['color'] }}" stroke-width="{{ isset($chart['x_values']) ? 1.2 : 2 }}" stroke-linecap="round" stroke-linejoin="round" />
                    @endif
                @endforeach
                @if($segments)
                    @foreach($seriesValues as $index => $value)
                        @if($value !== null)
                            @php
                                $x = $left + $plotWidth * (isset($chart['x_values']) ? (float) $seriesX[$index] / $xAxisMaximum : $index / $lastIndex);
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
                    <text x="{{ $x }}" y="{{ $height - 24 }}" text-anchor="middle" fill="#262626" font-size="11">{{ $label }}</text>
                @endfor
            @else
                @for($tick = 0; $tick < $ticks; $tick++)
                    @php
                        $index = $ticks === 1 ? 0 : (int) round($tick * (count($labels) - 1) / ($ticks - 1));
                        $x = $left + $plotWidth * $index / $lastIndex;
                    @endphp
                    <text x="{{ $x }}" y="{{ $height - 24 }}" text-anchor="middle" fill="#262626" font-size="11">{{ $labels[$index] }}</text>
                @endfor
            @endif
            <text x="{{ $left + $plotWidth / 2 }}" y="{{ $height - 5 }}" text-anchor="middle" fill="#262626" font-size="12">{{ $chart['axis_label'] }}</text>
            <text x="12" y="{{ $top + $plotHeight / 2 }}" text-anchor="middle" fill="#262626" font-size="12" transform="rotate(-90 12 {{ $top + $plotHeight / 2 }})">{{ $chart['value_label'] }}</text>
        </svg>
        @php $svg = ob_get_clean(); @endphp
        <img class="chart-svg" alt="{{ $report['title'] }} trend chart" src="data:image/svg+xml;base64,{{ base64_encode($svg) }}">
        <div class="legend">
            @foreach($chart['series'] as $series)
                <span style="display:inline-block;margin:3px 12px 3px 0;color:#262626;font-size:9pt">
                    <i class="legend-mark" style="background:{{ $series['color'] }}"></i>{{ $series['label'] }}
                </span>
            @endforeach
        </div>
    @endif
</div>
