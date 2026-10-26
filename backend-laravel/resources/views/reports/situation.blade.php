@php
$r = app(\App\Presenters\SituationReportDocument::class)->data($summary);
$value = function ($v) {
    if ($v instanceof \DateTimeInterface) return $v->format('M d, Y g:i A');
    if (is_bool($v)) return $v ? 'Yes' : 'No';
    return $v === null || $v === '' ? 'Not recorded' : $v;
};
@endphp
<!doctype html><html lang="en"><head><meta charset="utf-8"><title>BDRRMC Situation Report</title>
<style>
@page { size: A4 portrait; margin: 24mm 12mm 20mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111111; }
header { text-align: center; margin-bottom: 12px; } header div { margin: 2px; }
h1 { font-size: 14pt; } h2 { font-size: 11pt; border-bottom: 1px solid #345; padding-bottom: 5px; page-break-after: avoid; }
h3 { font-size: 9pt; page-break-after: avoid; }
table { border-collapse: collapse; width: 100%; margin: 8px 0; table-layout: fixed; }
th, td { border: 1px solid #999999; padding: 5px; vertical-align: top; overflow-wrap: break-word; }
th { background: #eeeeee; text-align: left; } thead { display: table-header-group; }
tr { page-break-inside: avoid; } .closing { page-break-inside: avoid; } .text { white-space: pre-wrap; }
footer { position: fixed; bottom: -12mm; font-size: 7pt; }
</style></head><body>
<header>@foreach ($r['letterhead'] as $line)<div>{{ $line }}</div>@endforeach
<h1>{{ $r['title'] }}</h1></header>
<table><tbody>
@foreach (['supersedes'=>'Supersedes Situation Report No.','report_type'=>'Report type','event_id'=>'Event ID','disaster_name'=>'Event name','disaster_type'=>'Hazard type','generated_at'=>'Date/time prepared','period_start'=>'Reporting period start','period_end'=>'As of','coverage'=>'Area coverage','household_scope'=>'Detail scope'] as $key=>$label)
<tr><th>{{ $label }}</th><td>{{ $value($r[$key] ?? null) }}</td></tr>
@endforeach
</tbody></table>
@if ($r['scope_note'])<p>{{ $r['scope_note'] }}</p>@endif
@foreach ($r['sections'] as $key=>$section)
<h2>{{ $section['number'] }}. {{ $section['title'] }}</h2>
@foreach ($section['tables'] as $table)
@if ($table['name'])<h3>{{ $table['name'] }}</h3>@endif
@include('reports.partials.v3-table', ['table'=>$table])
@endforeach
@if ($section['text'] || !$section['tables'])<p class="text">{{ $value($section['text']) }}</p>@endif
@endforeach
<div class="closing"><table><tr><th>Prepared by</th><th>Reviewed by</th><th>Approved by<br>Punong Barangay, BDRRMC Chair</th></tr>
<tr><td style="height:50px;vertical-align:bottom">________________________<br>{{ $value($r['prepared_by']) }}<br>Signature / date</td><td style="vertical-align:bottom">________________________<br>{{ $value($r['reviewed_by']) }}<br>Signature / date</td><td style="vertical-align:bottom">________________________<br>{{ $r['approved_by'] ?? '' }}<br>Signature / date</td></tr></table>
</div>
<footer>{{ $r['notice'] }}</footer>

</body></html>
