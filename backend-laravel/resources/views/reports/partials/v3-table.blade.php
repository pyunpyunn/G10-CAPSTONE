<table><thead><tr>@foreach ($table['headers'] as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead><tbody>
@foreach ($table['rows'] as $row)<tr>@foreach ($row as $cell)<td>{{ $value($cell) }}</td>@endforeach</tr>@endforeach
</tbody></table>
