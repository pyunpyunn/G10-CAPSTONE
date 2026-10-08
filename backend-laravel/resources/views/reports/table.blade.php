<!doctype html>
<html><head><meta charset="utf-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 9px; } h1 { font-size: 18px; }
table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ccc; padding: 5px; overflow-wrap: anywhere; } th { background: #eee; } thead { display: table-header-group; }
</style></head><body><h1>{{ ucfirst($title) }}</h1><p>Generated: {{ $generatedAt }}</p>
<table><thead><tr>@foreach($headers as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
<tbody>@foreach($rows as $row)<tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>@endforeach</tbody></table></body></html>
