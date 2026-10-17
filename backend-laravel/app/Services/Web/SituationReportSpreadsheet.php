<?php

namespace App\Services\Web;

use App\Presenters\SituationReportDocument;
use App\Support\SituationReportSections;

/** OOXML workbook using the supplied sample's fonts, fills, borders and number formats. */
class SituationReportSpreadsheet
{
    private int $priority = 0;

    private function pair(string $left, mixed $value, string $right = '', mixed $other = '', int $format = 9, int $otherFormat = 9): array
    {
        return ['kind' => 'pair', 'values' => [$left, $value, $right, $other], 'styles' => [8, $format, 8, $otherFormat]];
    }

    public function summaryRows(array $r): array
    {
        $p = $r['population'];
        $w = $r['weather'];
        $c = $r['casualties'];
        $d = $r['damage'];
        $rows = [['kind' => 'blank'], ['kind' => 'band', 'value' => 'REPUBLIC OF THE PHILIPPINES', 'style' => 1],
            ['kind' => 'band', 'value' => 'Barangay Disaster Risk Reduction and Management Committee', 'style' => 2],
            ['kind' => 'band', 'value' => 'Emergency Operations Center  |  '.$r['barangay'], 'style' => 3], ['kind' => 'blank'],
            ['kind' => 'band', 'value' => 'SITUATION REPORT', 'style' => 4, 'height' => 40],
            ['kind' => 'band', 'value' => $r['disaster_type'].'  |  '.$r['disaster_name'].'  |  '.$r['report_number'], 'style' => 5, 'height' => 22], ['kind' => 'blank'],
            ['kind' => 'section', 'value' => 'REPORT DETAILS'],
            $this->pair('Barangay', $r['barangay'], 'Report number', $r['report_number']),
            $this->pair('Disaster name', $r['disaster_name'], 'Event status', $r['status']),
            $this->pair('Disaster type', $r['disaster_type'], 'Generated at', $r['generated_at'], 9, 10),
            $this->pair('Date declared', $r['declared_at'], 'Period start', $r['period_start'], 10, 10),
            $this->pair('Date finished', $r['finished_at'], 'Period end', $r['period_end'], 10, 10),
            $this->pair('Prepared by', $r['prepared_by'], 'Reviewed by', $r['reviewed_by']),
            $this->pair('Area coverage', $r['coverage'], 'Alert level', $r['alert_level']),
            $this->pair('Household list scope', $r['household_scope'], 'Include household lists', $r['include_households'] ? 'Yes' : 'No'),
        ];
        if ($r['scope_note']) {
            $rows[] = ['kind' => 'text', 'value' => $r['scope_note']];
        }
        foreach ($r['included_sections'] as $section) {
            $rows[] = ['kind' => 'blank'];
            $rows[] = ['kind' => 'section', 'value' => $section.'   '.mb_strtoupper(SituationReportSections::LABELS[$section])];
            $extra = match ($section) {
                'I' => [$this->pair('Condition', $w['condition'], 'Temperature', $w['temp_c'], 9, 11),
                    $this->pair('Wind speed', $w['wind_kmh'], 'Wind direction', $w['wind_dir'], 12),
                    $this->pair('Rainfall', $w['rain_mm'], 'Data source', $w['source'], 13, 14), ['kind' => 'advisory', 'value' => $w['advisory']]],
                'II' => [$this->pair('Households in scope', $p['in_scope'], 'Reported households', $p['reported'], 17, 17),
                    $this->pair('Reported affected households', $p['affected'], 'Reporting progress', ($p['in_scope'] ?? 0) > 0 ? ($p['reported'] ?? 0) / $p['in_scope'] : 0, 17, 18),
                    $this->pair('Safe households', $p['safe'], 'Evacuated households', $p['evacuated'], 17, 17),
                    $this->pair('Unsafe households', $p['unsafe'], 'Unchecked households', $p['unchecked'], 17, 17)],
                'III' => [$this->pair('Deaths', $c['deaths'], 'Missing', $c['missing'], 17, 17), $this->pair('Injured', $c['injured'], 'Rescued', $c['rescued'], 17, 17)],
                'V' => [$this->pair('Public broadcasts', count(array_filter($r['timeline'], fn ($t) => $t['category'] === 'Public broadcast')),
                    'Dispatch assignments', count(array_filter($r['timeline'], fn ($t) => $t['category'] === 'Dispatch assignment')), 17, 17),
                    $this->pair('Teams reporting outcomes', count($r['teams']), '', '', 17)],
                'VI' => [$this->pair('Partially damaged houses', $d['partial'], 'Totally damaged houses', $d['total'], 17, 17), $this->pair('Estimated damage cost', $d['cost'], '', '', 19)],
                'VII' => [$this->pair('Items listed', count($r['resources']), 'Needing validation', count(array_filter($r['resources'], fn ($x) => strtolower($x['status'] ?? '') === 'needs validation')), 17, 17)],
                'VIII' => [['kind' => 'text', 'value' => $r['actions']]],
            };
            array_push($rows, ...$extra);
        }

        return $rows;
    }

    public function writeCsv(string $path, array $r): void
    {
        $tables = app(SituationReportDocument::class)->tables($r);
        $width = max(4, ...array_map(fn ($t) => count($t[1]), $tables));
        $handle = fopen($path, 'wb');
        if (! $handle) {
            throw new \RuntimeException('Could not open report output.');
        }
        try {
            fwrite($handle, "\xEF\xBB\xBF");
            $write = function (array $row) use ($handle, $width): void {
                $cells = array_map(function ($v) {
                    if ($v instanceof \DateTimeInterface) {
                        $v = $v->format('Y-m-d H:i:s');
                    }
                    $v = $v === null ? 'Not recorded' : (string) $v;

                    return preg_match('/^[\s]*[=+@-]/u', $v) ? "'".$v : $v;
                }, array_pad($row, $width, ''));
                fputcsv($handle, $cells, ',', '"', '');
            };
            foreach ($this->summaryRows($r) as $row) {
                $values = $row['values'] ?? [$row['value'] ?? ''];
                foreach ($row['styles'] ?? [] as $j => $style) {
                    if (is_numeric($values[$j])) {
                        $values[$j] = match ($style) {
                            11 => number_format($values[$j], 1, '.', '').' °C', 12 => number_format($values[$j], 2, '.', '').' km/h',
                            13 => number_format($values[$j], 2, '.', '').' mm', 18 => round($values[$j] * 100).'%',
                            19 => 'PHP '.number_format($values[$j], 2, '.', ','), default => $values[$j],
                        };
                    }
                }
                $write($values);
            }
            foreach ($tables as [$name,$headings,$records]) {
                $write([]);
                $write([$name]);
                $write($headings);
                foreach ($records as $record) {
                    $write($record);
                }
            }
        } finally {
            fclose($handle);
        }
    }

    public function writeXlsx(string $path, array $r): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create workbook.');
        }
        try {
            $sheets = [['Summary', $this->summaryXml($r)]];
            foreach (app(SituationReportDocument::class)->tables($r) as [$name,$headings,$records,$widths]) {
                $sheets[] = [$name, $this->tableXml($headings, $records, $widths)];
            }
            $types = '';
            $tabs = '';
            $rels = '';
            $names = '';
            foreach ($sheets as $i => [$name,$xml]) {
                $id = $i + 1;
                $tabs .= '<sheet name="'.$this->escape($name).'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
                $rels .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$id.'.xml"/>';
                $types .= '<Override PartName="/xl/worksheets/sheet'.$id.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
                if ($i > 0) {
                    $names .= '<definedName name="_xlnm.Print_Titles" localSheetId="'.$i.'">'.$this->escape("'".$name."'!\$1:\$1").'</definedName>';
                }
                $zip->addFromString('xl/worksheets/sheet'.$id.'.xml', $xml);
            }
            $styleId = count($sheets) + 1;
            $rels .= '<Relationship Id="rId'.$styleId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
            $zip->addFromString('[Content_Types].xml', $this->declaration().'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$types.'</Types>');
            $zip->addFromString('_rels/.rels', $this->declaration().'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->declaration().'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
            $zip->addFromString('xl/workbook.xml', $this->declaration().'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets>'.$tabs.'</sheets><definedNames>'.$names.'</definedNames></workbook>');
            $zip->addFromString('xl/styles.xml', file_get_contents(resource_path('reports/situation-styles.xml')));
        } finally {
            $zip->close();
        }
    }

    private function summaryXml(array $r): string
    {
        $data = '';
        $merges = [];
        $rows = $this->summaryRows($r);
        $rules = '';
        foreach ($rows as $i => $row) {
            $n = $i + 1;
            $kind = $row['kind'];
            $cells = '';
            $height = $row['height'] ?? ($kind === 'section' ? 24 : 20);
            if ($kind === 'pair') {
                foreach ($row['values'] as $j => $v) {
                    $ref = chr(66 + $j).$n;
                    $label = $row['values'][$j - 1] ?? '';
                    if ($j === 3 && $label === 'Reporting progress') {
                        $v = ['formula' => 'IF(C'.($n - 1).'=0,0,E'.($n - 1).'/C'.($n - 1).')', 'cached' => $v];
                    }
                    if ($j % 2 === 1 && in_array($label, ['Public broadcasts', 'Dispatch assignments', 'Teams reporting outcomes', 'Items listed', 'Needing validation'])) {
                        $f = match ($label) {
                            'Public broadcasts' => 'COUNTIF(Timeline!A2:A'.max(2, count($r['timeline']) + 1).',"Public broadcast")',
                            'Dispatch assignments' => 'COUNTIF(Timeline!A2:A'.max(2, count($r['timeline']) + 1).',"Dispatch assignment")',
                            'Teams reporting outcomes' => "COUNTA('Team Outcomes'!A2:A".max(2, count($r['teams']) + 1).')',
                            'Items listed' => 'COUNTA(Resources!A2:A'.max(2, count($r['resources']) + 1).')',
                            'Needing validation' => 'COUNTIF(Resources!D2:D'.max(2, count($r['resources']) + 1).',"Needs Validation")',
                        };
                        $v = ['formula' => $f, 'cached' => $v];
                    }
                    $cells .= $this->cell($ref, $v, $row['styles'][$j]);
                    if ($j % 2 === 1 && in_array($label, ['Deaths', 'Missing', 'Injured', 'Unsafe households', 'Unchecked households'])) {
                        $rules .= $this->condition($ref, 'cellIs', $label === 'Unchecked households' ? 2 : 1, '0', 'greaterThan');
                    }
                }
            } elseif ($kind === 'band' || $kind === 'section') {
                $style = $row['style'] ?? 6;
                foreach (['B', 'C', 'D', 'E'] as $col) {
                    $cells .= $this->cell($col.$n, $col === 'B' ? $row['value'] : '', $style);
                }
                $merges[] = 'B'.$n.':E'.$n;
            } elseif ($kind === 'text' || $kind === 'advisory') {
                $height = max(48, ceil(mb_strlen((string) $row['value']) / 95) * 14);
                if ($kind === 'advisory') {
                    $cells = $this->cell('B'.$n, 'Advisory', 15).$this->cell('C'.$n, $row['value'], 16);
                    $merges[] = 'C'.$n.':E'.$n;
                } else {
                    $cells = $this->cell('B'.$n, $row['value'], 20);
                    $merges[] = 'B'.$n.':E'.$n;
                }
            } else {
                $height = 7;
            }
            $data .= '<row r="'.$n.'" ht="'.$height.'" customHeight="1">'.$cells.'</row>';
        }
        $merge = '<mergeCells count="'.count($merges).'">'.implode('', array_map(fn ($m) => '<mergeCell ref="'.$m.'"/>', $merges)).'</mergeCells>';

        return $this->sheet($data, [2.5, 30, 28, 30, 28, 2.5], $merge.$rules, false, count($rows), 6, $r['report_number']);
    }

    private function tableXml(array $headers, array $rows, array $widths): string
    {
        $data = '<row r="1" ht="26" customHeight="1">';
        foreach ($headers as $i => $v) {
            $data .= $this->cell($this->column($i + 1).'1', $v, 21);
        }
        $data .= '</row>';
        foreach ($rows as $i => $row) {
            $n = $i + 2;
            $data .= '<row r="'.$n.'" ht="30" customHeight="1">';
            foreach ($row as $j => $v) {
                $data .= $this->cell($this->column($j + 1).$n, $v, $v instanceof \DateTimeInterface ? 26 : (is_numeric($v) ? 17 : 23));
            }
            $data .= '</row>';
        }
        $end = max(2, count($rows) + 1);
        $rules = $this->condition('A2:'.$this->column(count($headers)).$end, 'expression', 5, 'MOD(ROW(),2)=0');
        if (($col = array_search('Status', $headers, true)) !== false) {
            $range = $this->column($col + 1).'2:'.$this->column($col + 1).$end;
            foreach (['Needs Validation' => 2, 'Needs validation' => 2, 'Forwarded' => 13, 'Delivered' => 10, 'Active' => 10] as $label => $dxf) {
                $rules .= $this->condition($range, 'cellIs', $dxf, '"'.$label.'"', 'equal');
            }
        }

        return $this->sheet($data, $widths, $rules, true, count($rows) + 1, count($headers));
    }

    private function sheet(string $data, array $widths, string $merge, bool $table, int $rows, int $columns, string $report = ''): string
    {
        $cols = '';
        foreach ($widths as $i => $width) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$width.'" customWidth="1"/>';
        }
        $last = $this->column($columns).max(1, $rows);

        return $this->declaration().'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="A1:'.$last.'"/><sheetViews><sheetView workbookViewId="0" showGridLines="0">'.($table ? '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>' : '').'</sheetView></sheetViews><cols>'.$cols.'</cols><sheetData>'.$data.'</sheetData>'.($table ? '<autoFilter ref="A1:'.$last.'"/>' : '').$merge.'<pageMargins left="0.5" right="0.5" top="0.5" bottom="0.8" header="0.3" footer="0.35"/><pageSetup paperSize="9" orientation="'.($table ? 'landscape' : 'portrait').'" fitToWidth="1" fitToHeight="'.($table ? '0' : '1').'"/><headerFooter><oddFooter>'.$this->escape('&L&A&CPage &P of &N&R'.$report).'</oddFooter></headerFooter></worksheet>';
    }

    private function cell(string $ref, mixed $value, int $style): string
    {
        if (is_array($value) && isset($value['formula'])) {
            return '<c r="'.$ref.'" s="'.$style.'"><f>'.$this->escape($value['formula']).'</f><v>'.$value['cached'].'</v></c>';
        }
        if ($value instanceof \DateTimeInterface) {
            $value = (new \DateTimeImmutable($value->format('Y-m-d H:i:s'), new \DateTimeZone('UTC')))->getTimestamp() / 86400 + 25569;
        }
        if (is_int($value) || is_float($value)) {
            return '<c r="'.$ref.'" s="'.$style.'" t="n"><v>'.$value.'</v></c>';
        }

        return '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->escape($value ?? 'Not recorded').'</t></is></c>';
    }

    private function condition(string $range, string $type, int $dxf, string $formula, ?string $operator = null): string
    {
        return '<conditionalFormatting sqref="'.$range.'"><cfRule type="'.$type.'" dxfId="'.$dxf.'" priority="'.(++$this->priority).'"'.($operator ? ' operator="'.$operator.'"' : '').'><formula>'.$this->escape($formula).'</formula></cfRule></conditionalFormatting>';
    }

    private function column(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $n--;
            $s = chr(65 + $n % 26).$s;
            $n = intdiv($n,26);
        }

return $s;
    }

    private function declaration(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    }

    private function escape(mixed $s): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u','',(string) $s),ENT_XML1 | ENT_QUOTES,'UTF-8');
    }
}
