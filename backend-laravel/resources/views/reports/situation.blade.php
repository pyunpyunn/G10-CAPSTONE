<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Situation Report</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #182533;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
        }

        .report-shell {
            border: 1px solid #cbd5df;
            background: #ffffff;
        }

        .report-header {
            border-top: 4px solid #164e73;
            border-bottom: 1px solid #cbd5df;
            padding: 20px 24px 16px;
            text-align: center;
            background: #ffffff;
        }

        .kicker {
            color: #637384;
            font-size: 7px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .office-name {
            margin-top: 4px;
            font-size: 9px;
            line-height: 1.4;
            font-weight: 700;
        }

        .report-title {
            margin-top: 16px;
            font-size: 18px;
            font-weight: 700;
            color: #164e73;
            text-transform: uppercase;
        }

        .event-name {
            margin-top: 6px;
            font-size: 11px;
            font-weight: 700;
            color: #182533;
        }

        .meta-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
            background: #f5f8fb;
        }

        .meta-item {
            display: table-cell;
            width: 25%;
            padding: 8px 10px;
            border-right: 1px solid #cbd5df;
            border-bottom: 1px solid #cbd5df;
            vertical-align: top;
        }

        .meta-label {
            display: block;
            margin-bottom: 4px;
            color: #637384;
            font-size: 6.5px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .meta-value {
            display: block;
            font-size: 9px;
            font-weight: 700;
            color: #182533;
            word-break: break-word;
        }

        .section {
            padding: 14px 18px 16px;
            border-bottom: 1px solid #cbd5df;
        }

        .section:last-child {
            border-bottom: none;
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .section-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 22px;
            margin-right: 8px;
            border-radius: 4px;
            background: #164e73;
            color: #ffffff;
            font-size: 8px;
            font-weight: 700;
        }

        .section-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            color: #182533;
        }

        .card-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #cbd5df;
            border-top: 1px solid #cbd5df;
        }

        .card {
            display: table-cell;
            width: 20%;
            min-height: 42px;
            padding: 8px 9px;
            border-right: 1px solid #cbd5df;
            border-bottom: 1px solid #cbd5df;
            background: #ffffff;
            vertical-align: top;
        }

        .card.half {
            width: 50%;
        }

        .card.quarter {
            width: 25%;
        }

        .card-label {
            display: block;
            margin-bottom: 4px;
            color: #637384;
            font-size: 6.5px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .card-value {
            display: block;
            font-size: 9px;
            font-weight: 700;
            color: #182533;
            word-break: break-word;
        }

        .note-box {
            margin-top: 10px;
            padding: 8px 10px;
            background: #f5f8fb;
            border-left: 2px solid #164e73;
            color: #48596b;
            line-height: 1.5;
            font-size: 8.5px;
        }

        .table-wrap {
            margin-top: 10px;
            overflow: hidden;
            border-top: 1px solid #cbd5df;
            border-left: 1px solid #cbd5df;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 6px;
            font-size: 8.5px;
            text-align: left;
            vertical-align: top;
            border-right: 1px solid #cbd5df;
            border-bottom: 1px solid #cbd5df;
            word-break: break-word;
        }

        th {
            background: #f5f8fb;
            color: #637384;
            font-weight: 700;
            text-transform: uppercase;
        }

        .actions-box {
            min-height: 90px;
            padding: 10px;
            border: 1px solid #cbd5df;
            background: #ffffff;
            color: #182533;
            line-height: 1.5;
            font-size: 9px;
            white-space: pre-wrap;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    @php
        $report = $summary['report'] ?? [];
        $event = $summary['event'] ?? [];
        $weather = $summary['weather'] ?? [];
        $household = $summary['household'] ?? [];
        $casualties = $summary['casualties'] ?? [];
        $damage = $summary['damage'] ?? [];
        $resources = $summary['resources']['rows'] ?? [];
        $evacuation = $summary['evacuation'] ?? [];
        $dispatch = $summary['dispatch'] ?? [];
        $includedSections = $summary['included_sections'] ?? ['I'];
        $emptyValue = 'Not recorded';
        $text = fn($value) => ($value === null || $value === '') ? $emptyValue : (string) $value;
    @endphp

    <div class="report-shell">
        <div class="report-header">
            <div class="kicker">Republic of the Philippines</div>
            <div class="office-name">Barangay Disaster Risk Reduction and Management Committee<br />Emergency Operations Center</div>
            <div class="report-title">Situation Report</div>
            <div class="event-name">{{ $text($event['name'] ?? null) }}</div>
        </div>

        <div class="meta-grid">
            <div class="meta-item"><span class="meta-label">Disaster type</span><span class="meta-value">{{ $text($event['type'] ?? null) }}</span></div>
            <div class="meta-item"><span class="meta-label">Date declared</span><span class="meta-value">{{ $text($event['declared_at'] ?? null) }}</span></div>
            <div class="meta-item"><span class="meta-label">Date finished</span><span class="meta-value">{{ $text($event['finished_at'] ?? null) }}</span></div>
            <div class="meta-item"><span class="meta-label">Alert level</span><span class="meta-value">{{ $text($event['severity'] ?? null) }}</span></div>
            <div class="meta-item"><span class="meta-label">Reporting period</span><span class="meta-value">{{ $text(($report['period_start'] ?? null).' - '.($report['period_end'] ?? null)) }}</span></div>
            <div class="meta-item"><span class="meta-label">Area coverage</span><span class="meta-value">{{ $text($event['coverage'] ?? null) }}</span></div>
            <div class="meta-item"><span class="meta-label">Situation status</span><span class="meta-value">{{ $text($event['situation_status'] ?? null) }}</span></div>
            <div class="meta-item"><span class="meta-label">Prepared by</span><span class="meta-value">{{ $text($report['prepared_by'] ?? null) }}</span></div>
        </div>

        @if(!empty($summary['scope_note']))
            <div class="note-box">{{ $summary['scope_note'] }}</div>
        @endif
        @foreach($includedSections as $section)
            @switch($section)
                @case('I')
                    <div class="section">
                        <div class="section-header"><span class="section-number">I</span><span class="section-title">Situation Overview</span></div>
                        <div class="card-grid">
                            <div class="card"><span class="card-label">Condition</span><span class="card-value">{{ $text($weather['condition'] ?? null) }}</span></div>
                            <div class="card"><span class="card-label">Wind</span><span class="card-value">{{ $text($weather['wind'] ?? null) }}</span></div>
                            <div class="card"><span class="card-label">Rainfall</span><span class="card-value">{{ $text($weather['rainfall'] ?? null) }}</span></div>
                            <div class="card"><span class="card-label">Temperature</span><span class="card-value">{{ $text($weather['temperature'] ?? null) }}</span></div>
                            <div class="card"><span class="card-label">Source</span><span class="card-value">{{ $text($weather['source'] ?? null) }}</span></div>
                        </div>
                        <div class="note-box">{{ $text($weather['advisory'] ?? null) }}</div>
                    </div>
                    @break

                @case('II')
                    <div class="section">
                        <div class="section-header"><span class="section-number">II</span><span class="section-title">Affected Population</span></div>
                        <div class="card-grid">
                            <div class="card quarter"><span class="card-label">Households in scope</span><span class="card-value">{{ $text($household['total'] ?? null) }}</span></div>
                            <div class="card quarter"><span class="card-label">Safe total</span><span class="card-value">{{ $text($household['safe_total'] ?? null) }}</span></div>
                            <div class="card quarter"><span class="card-label">Evacuated</span><span class="card-value">{{ $text($household['evacuated'] ?? null) }}</span></div>
                            <div class="card quarter"><span class="card-label">Unsafe / At risk</span><span class="card-value">{{ $text($household['unsafe'] ?? null) }}</span></div>
                            <div class="card quarter"><span class="card-label">Unchecked</span><span class="card-value">{{ $text($household['unchecked'] ?? null) }}</span></div>
                        </div>
                        <div class="note-box">Affected means unsafe or evacuated/relocated households. Person counts describe registered household members, not verified individual casualties. Reporting progress: {{ $text($household['progress_text'] ?? null) }} checked - {{ $text($household['progress_sub'] ?? null) }}</div>
                        <div class="table-wrap"><table><thead><tr><th>Sitio</th><th>HH in scope</th><th>Reported</th><th>Affected HH</th><th>Unchecked</th></tr></thead><tbody>
                        @foreach($household['sitios'] ?? [] as $row)
                        <tr>@foreach(['sitio', 'total', 'reported', 'affected', 'unchecked'] as $key)<td>{{ $text($row[$key] ?? null) }}</td>@endforeach</tr>
                        @endforeach
                        </tbody></table></div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>Sitio</th><th>Purok</th><th>Persons in scope</th><th>Affected HH</th><th>Persons in affected HH</th>
                                    <th>Households</th>
                                    <th>Safe</th>
                                    <th>Evacuated</th>
                                    <th>Unsafe</th>
                                    <th>Unchecked</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($household['puroks'] ?? [] as $row)
                                    <tr>
                                        <td>{{ $text($row['sitio'] ?? null) }}</td><td>{{ $text($row['purok'] ?? null) }}</td><td>{{ $text($row['persons'] ?? null) }}</td><td>{{ $text($row['affected'] ?? null) }}</td><td>{{ $text($row['affected_persons'] ?? null) }}</td>
                                        <td>{{ $text($row['total'] ?? null) }}</td>
                                        <td>{{ $text($row['safe'] ?? null) }}</td>
                                        <td>{{ $text($row['evacuated'] ?? null) }}</td>
                                        <td>{{ $text($row['unsafe'] ?? null) }}</td>
                                        <td>{{ $text($row['unchecked'] ?? null) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="10">No purok-level data linked to this event.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @break

                @case('III')
                    <div class="section">
                        <div class="section-header"><span class="section-number">III</span><span class="section-title">Casualties and Immediate Needs</span></div>
                        <div class="card-grid">
                            <div class="card quarter"><span class="card-label">Deaths</span><span class="card-value">{{ $text($casualties['deaths'] ?? null) }}</span></div>
                            <div class="card quarter"><span class="card-label">Missing</span><span class="card-value">{{ $text($casualties['missing'] ?? null) }}</span></div>
                            <div class="card quarter"><span class="card-label">Injured</span><span class="card-value">{{ $text($casualties['injured'] ?? null) }}</span></div>
                            <div class="card quarter"><span class="card-label">Rescued</span><span class="card-value">{{ $text($casualties['rescued'] ?? null) }}</span></div>
                        </div>
                    </div>
                    @break

                @case('V')
                    <div class="section">
                        <div class="section-header"><span class="section-number">V</span><span class="section-title">Rescue Operations Timeline</span></div>
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>Time</th><th>Title</th><th>Actor</th><th>Status</th><th>Detail</th></tr></thead>
                                <tbody>
                                @forelse($dispatch['timeline'] ?? [] as $row)
                                    <tr>
                                        <td>{{ $text($row['time'] ?? null) }}</td>
                                        <td>{{ $text($row['title'] ?? null) }}</td>
                                        <td>{{ $text($row['actor'] ?? null) }}</td>
                                        <td>{{ $text($row['status'] ?? null) }}</td>
                                        <td>{{ $text($row['detail'] ?? null) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5">No dispatch activity linked to this event.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="table-wrap" style="margin-top: 12px;">
                            <table>
                                <thead><tr><th>Team</th><th>Deployed</th><th>Area</th><th>Outcomes</th></tr></thead>
                                <tbody>
                                @forelse($dispatch['rows'] ?? [] as $row)
                                    <tr>
                                        <td>{{ $text($row['team'] ?? null) }}</td>
                                        <td>{{ $text($row['deployed_at'] ?? null) }}</td>
                                        <td>{{ $text($row['area'] ?? null) }}</td>
                                        <td>{{ $text($row['outcomes'] ?? null) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4">No team deployment detail linked to this event.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @break

                @case('VI')
                    <div class="section">
                        <div class="section-header"><span class="section-number">VI</span><span class="section-title">Initial Damage Assessment</span></div>
                        <div class="card-grid">
                            <div class="card half"><span class="card-label">Partially damaged houses</span><span class="card-value">{{ $text($damage['partial'] ?? null) }}</span></div>
                            <div class="card half"><span class="card-label">Totally damaged houses</span><span class="card-value">{{ $text($damage['total'] ?? null) }}</span></div>
                            <div class="card half"><span class="card-label">Estimated damage cost</span><span class="card-value">{{ $text($damage['cost'] ?? null) }}</span></div>
                        </div>
                    </div>
                    @break

                @case('VII')
                    <div class="section">
                        <div class="section-header"><span class="section-number">VII</span><span class="section-title">Resources Deployed and Requests</span></div>
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>Item</th><th>Quantity</th><th>Source</th><th>Status</th></tr></thead>
                                <tbody>
                                @forelse($resources as $row)
                                    <tr>
                                        <td>{{ $text($row['item'] ?? null) }}</td>
                                        <td>{{ $text($row['quantity'] ?? null) }}</td>
                                        <td>{{ $text($row['source'] ?? null) }}</td>
                                        <td>{{ $text($row['status'] ?? null) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4">No resource request records linked to this event.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @break

                @case('VIII')
                    <div class="section">
                        <div class="section-header"><span class="section-number">VIII</span><span class="section-title">Actions Taken and Recommendations</span></div>
                        <div class="actions-box">{{ $summary['actions_text'] ?? 'No action note was added for this report.' }}</div>
                    </div>
                    @break
            @endswitch
        @endforeach
    </div>
</body>
</html>
