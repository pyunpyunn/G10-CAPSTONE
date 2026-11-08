<table>
@if (!empty($table['groups']))
@foreach ($table['groups'] as $sitio)
<tbody>
<tr class="sitio-group"><th colspan="{{ count($table['headers']) }}">Sitio: {{ $value($sitio['name']) }}</th></tr>
</tbody>
@foreach ($sitio['puroks'] as $purok)
<tbody>
<tr class="purok-group"><th colspan="{{ count($table['headers']) }}">Purok: {{ $value($purok['name']) }}</th></tr>
<tr>@foreach ($table['headers'] as $heading)<th>{{ $heading }}</th>@endforeach</tr>
</tbody>
@foreach ($purok['households'] as $household)
<tbody>
<tr class="household-family-row"><td colspan="{{ count($table['headers']) }}"><table class="household-family-table"><tbody>
@foreach ($household['members'] as $member)
<tr class="household-member{{ $member['last'] ? ' household-member-last' : '' }}">@if ($member['household_first'])<td rowspan="{{ count($household['members']) }}" class="{{ count($household['members']) > 1 ? 'household-name-span' : '' }}">{{ $value($household['household']) }}</td>@endif<td>{{ $value($member['name']) }}</td><td>{{ $value($member['status']) }}</td></tr>
@endforeach
</tbody></table></td></tr>
</tbody>
@endforeach
@endforeach
@endforeach
@else
<thead><tr>@foreach ($table['headers'] as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead><tbody>
@foreach ($table['rows'] as $row)<tr>@foreach ($row as $cell)<td>{{ $value($cell) }}</td>@endforeach</tr>@endforeach
</tbody>
@endif
</table>
