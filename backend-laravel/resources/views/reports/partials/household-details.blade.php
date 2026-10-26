@php
    $names = match ($householdSection) {
        'II' => ['Affected Households', 'Affected Members'],
        'III' => ['Household Casualties'],
        'VI' => ['Household Damage'],
        default => [],
    };
@endphp
@if($r['include_households'])
<div class="note">Household list scope: {{ $r['household_scope'] }}</div>
@foreach(app(\App\Presenters\SituationReportDocument::class)->tables($r) as [$name, $headings, $records])
@if(in_array($name, $names, true))
<h2 class="sub">{{ $name }}</h2>
<table class="data household-details"><thead><tr>@foreach($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead><tbody>
@forelse($records as $i => $record)
<tr class="{{ $i % 2 ? 'alt' : '' }}">@foreach($record as $cell)<td>{{ $cell ?? 'Not recorded' }}</td>@endforeach</tr>
@empty
<tr><td colspan="{{ count($headings) }}" class="empty">No household records in this scope.</td></tr>
@endforelse
</tbody></table>
@endif
@endforeach
@endif
