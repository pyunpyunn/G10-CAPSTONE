<?php

namespace App\Services\Web;

use App\Support\SituationReportSections as Sections;
use App\Presenters\SituationReportDocument;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\BorderWidth;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class SituationReportSpreadsheet
{
    private function value(mixed $v): string
    {
        if ($v === SituationReportDocument::BLANK_CELL) return '';
        if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d H:i:s');
        if (is_bool($v)) return $v ? 'Yes' : 'No';
        return $v === null || $v === '' ? 'Not recorded' : (string) $v;
    }

    private function workbookRow(array $values, ?Style $style = null, array $cellStyles = []): Row
    {
        // OpenSpout treats leading '=' strings as formulas by default. All recorded text is literal.
        return new Row(array_map(fn ($v, $index) => $v === SituationReportDocument::BLANK_CELL
            ? new \OpenSpout\Common\Entity\Cell\EmptyCell(null, $cellStyles[$index] ?? $style)
            : (is_int($v) || is_float($v)
                ? \OpenSpout\Common\Entity\Cell::fromValue($v, $cellStyles[$index] ?? $style)
                : new \OpenSpout\Common\Entity\Cell\StringCell($this->value($v), $cellStyles[$index] ?? $style)), $values, array_keys($values)));
    }

    private function rowEntries(array $r): array
    {
        $entries = [];
        $append = static function (array $values, ?string $style = null, ?int $mergeRows = null, ?int $mergeColumns = null, string $sheet = 'summary') use (&$entries): void {
            $entries[] = ['values' => $values, 'style' => $style, 'merge_rows' => $mergeRows, 'merge_columns' => $mergeColumns, 'sheet' => $sheet];
        };
        foreach (Sections::LETTERHEAD as $line) $append([$line], 'letterhead');
        $append([$r['title']], 'report-title');
        foreach (['supersedes'=>'Supersedes Situation Report No.','report_type'=>'Report type','event_id'=>'Event ID','disaster_name'=>'Event name','disaster_type'=>'Hazard type','generated_at'=>'Date/time prepared','period_start'=>'Reporting period start','period_end'=>'As of','coverage'=>'Area coverage','household_scope'=>'Detail scope'] as $key=>$title) {
            $append([$title,$r[$key] ?? null], 'metadata');
        }
        if ($r['scope_note']) $append(['Scope note',$r['scope_note']], 'metadata');
        foreach ($r['sections'] as $key=>$section) {
            $append([]);
            $append([$section['number'].'. '.$section['title']], 'section-title');
            foreach ($section['tables'] as $table) {
                $familySheet = $key === 'II' && str_starts_with((string) ($table['name'] ?? ''), 'Part 3 —');
                $targetSheet = $familySheet ? 'families' : 'summary';
                if ($familySheet) {
                    $append([$table['name'].' — see Families sheet'], 'annex-note');
                } elseif ($table['name']) {
                    $append([$table['name']], 'table-title');
                }
                if ($familySheet) {
                    $append([$table['name']], 'table-title', null, null, $targetSheet);
                }
                if (!empty($table['groups'])) {
                    foreach ($table['groups'] as $sitio) {
                        $append(['Sitio: '.$sitio['name']], 'sitio', null, null, $targetSheet);
                        foreach ($sitio['puroks'] as $purok) {
                            $append(['Purok: '.$purok['name']], 'purok', null, null, $targetSheet);
                            $append($table['headers'], 'table-header', null, null, $targetSheet);
                            foreach ($purok['households'] as $household) {
                                foreach ($household['members'] as $index => $member) {
                                    $append([
                                        $index === 0 ? $household['household'] : SituationReportDocument::BLANK_CELL,
                                        $member['name'],
                                        $member['status'],
                                    ], $member['last'] ? 'household-last' : 'table-row',
                                    $index === 0 && count($household['members']) > 1 ? count($household['members']) : null,
                                    null,
                                    $targetSheet);
                                }
                            }
                        }
                    }
                } else {
                    $append($table['headers'], 'table-header', null, null, $targetSheet);
                    foreach ($table['rows'] as $row) $append($row, 'table-row', null, null, $targetSheet);
                }
            }
            if ($section['text'] || !$section['tables']) $append([$section['text']], 'table-row');
        }
        $append([]);
        $append(['Prepared by',$r['prepared_by']], 'signature');
        $append(['Reviewed by',$r['reviewed_by']], 'signature');
        $append(['Approved by (Punong Barangay, BDRRMC Chair)',$r['approved_by'] ?: '____________________'], 'signature');
        $append([Sections::NOTICE], 'notice');
        return $entries;
    }

    private function rowStyle(?string $type): ?Style
    {
        $gridBorder = new Border(
            new BorderPart(BorderName::TOP, 'D9E2F3', BorderWidth::THIN),
            new BorderPart(BorderName::BOTTOM, 'D9E2F3', BorderWidth::THIN),
            new BorderPart(BorderName::LEFT, 'D9E2F3', BorderWidth::THIN),
            new BorderPart(BorderName::RIGHT, 'D9E2F3', BorderWidth::THIN),
        );

        return match ($type) {
            'letterhead' => new Style(fontBold: true, fontSize: 11, cellAlignment: CellAlignment::CENTER, shouldWrapText: true),
            'report-title' => new Style(fontBold: true, fontSize: 16, fontColor: '17365D', cellAlignment: CellAlignment::CENTER, cellVerticalAlignment: CellVerticalAlignment::CENTER, shouldWrapText: true),
            'metadata-label' => new Style(fontBold: true, backgroundColor: 'EAF0F5', shouldWrapText: true, border: new Border(
                new BorderPart(BorderName::BOTTOM, 'B7C9D6', BorderWidth::THIN),
                new BorderPart(BorderName::LEFT, 'B7C9D6', BorderWidth::THIN),
                new BorderPart(BorderName::RIGHT, 'B7C9D6', BorderWidth::THIN),
            )),
            'metadata-value' => new Style(shouldWrapText: true, border: new Border(
                new BorderPart(BorderName::BOTTOM, 'B7C9D6', BorderWidth::THIN),
                new BorderPart(BorderName::RIGHT, 'B7C9D6', BorderWidth::THIN),
            )),
            'section-title' => new Style(fontBold: true, fontSize: 12, fontColor: 'FFFFFF', backgroundColor: '17365D', shouldWrapText: true),
            'table-title' => new Style(fontBold: true, fontSize: 11, fontColor: '17365D', backgroundColor: 'DCE6F1', shouldWrapText: true),
            'table-header' => new Style(fontBold: true, fontColor: 'FFFFFF', backgroundColor: '315B7D', cellVerticalAlignment: CellVerticalAlignment::CENTER, shouldWrapText: true, border: new Border(
                new BorderPart(BorderName::TOP, '17365D', BorderWidth::THIN),
                new BorderPart(BorderName::BOTTOM, '17365D', BorderWidth::MEDIUM),
                new BorderPart(BorderName::LEFT, 'FFFFFF', BorderWidth::THIN),
                new BorderPart(BorderName::RIGHT, 'FFFFFF', BorderWidth::THIN),
            )),
            'table-row' => new Style(shouldWrapText: true, cellVerticalAlignment: CellVerticalAlignment::CENTER, border: $gridBorder),
            'annex-note' => new Style(fontItalic: true, fontColor: '526579', shouldWrapText: true),
            'sitio' => new Style(fontBold: true, fontSize: 11, backgroundColor: 'DCE6F1', shouldWrapText: true),
            'purok' => new Style(fontBold: true, backgroundColor: 'EDF2F7', shouldWrapText: true),
            'household-last' => new Style(shouldWrapText: true, border: new Border(
                new BorderPart(BorderName::BOTTOM, '334155', BorderWidth::THICK),
            )),
            'signature' => new Style(fontBold: true, shouldWrapText: true, border: new Border(
                new BorderPart(BorderName::BOTTOM, 'B7C9D6', BorderWidth::THIN),
            )),
            'notice' => new Style(fontItalic: true, fontSize: 9, fontColor: '526579', shouldWrapText: true),
            default => null,
        };
    }

    public function rows(array $r): array
    {
        return array_map(fn ($entry) => $entry['values'], $this->rowEntries($r));
    }

    public function writeCsv(string $path, array $r): void
    {
        $handle=fopen($path,'wb');
        if (!$handle) throw new \RuntimeException('Could not open report output.');
        try {
            fwrite($handle,"\xEF\xBB\xBF");
            $rows=$this->rows($r);
            $width=max(1,...array_map('count',$rows));
            foreach ($rows as $row) fputcsv($handle,array_pad(array_map(function ($v) {
                $v=$this->value($v);
                return preg_match('/^[\s]*[=+@-]/u',$v) ? "'".$v : $v;
            },$row),$width,''),',','"','');
        } finally { fclose($handle); }
    }

    public function writeXlsx(string $path, array $r): void
    {
        $entries = $this->rowEntries($r);
        $summaryEntries = array_values(array_filter($entries, fn (array $entry) => $entry['sheet'] === 'summary'));
        $familyEntries = array_values(array_filter($entries, fn (array $entry) => $entry['sheet'] === 'families'));
        if ($familyEntries !== []) {
            $section = $r['sections']['II'];
            array_splice($familyEntries, 0, 0, [
                ['values' => [$section['number'].'. '.$section['title']], 'style' => 'section-title', 'merge_rows' => null, 'merge_columns' => null, 'sheet' => 'families'],
            ]);
        }
        $options = new \OpenSpout\Writer\XLSX\Options(
            FALLBACK_STYLE: new \OpenSpout\Common\Entity\Style\Style(fontSize: 10, shouldWrapText: true),
            DEFAULT_COLUMN_WIDTH: 18,
            pageSetup: new \OpenSpout\Writer\XLSX\Options\PageSetup(
                \OpenSpout\Writer\XLSX\Options\PageOrientation::PORTRAIT,
                \OpenSpout\Writer\XLSX\Options\PaperSize::A4, fitToHeight: 0, fitToWidth: 1),
            headerFooter: new \OpenSpout\Writer\XLSX\Options\HeaderFooter(
                oddFooter: '&amp;LContains personal data. Handle under RA 10173 (Data Privacy Act).&amp;RPage &amp;P of &amp;N')
        );
        $writer=new Writer($options); $writer->openToFile($path);
        try {
            $this->writeSheet($writer, $summaryEntries, 'Summary', 0);
            if ($familyEntries !== []) {
                $familySheet = $writer->addNewSheetAndMakeItCurrent();
                $familySheet->setName('Section II Families');
                $this->writeSheet($writer, $familyEntries, 'Section II Families', 1);
            }
        } finally { $writer->close(); }
    }

    private function writeSheet(Writer $writer, array $entries, string $name, int $sheetIndex): void
    {
        $sheet = $writer->getCurrentSheet();
        $sheet->setName($name);
        $maxColumns = max(1, ...array_map(fn (array $entry) => count($entry['values']), $entries));
        $sheet->setColumnWidth(32, 0);
        if ($maxColumns > 1) $sheet->setColumnWidthForRange(20, 1, $maxColumns - 1);
        if ($maxColumns >= 3) {
            $sheet->setColumnWidth(30, 0);
            $sheet->setColumnWidth(34, 1);
            $sheet->setColumnWidthForRange(22, 2, $maxColumns - 1);
        }
        if ($sheetIndex === 0) {
            $sheet->setPrintTitleRows('1:'.count(Sections::LETTERHEAD));
        }
        $rowNumber = 0;
        foreach ($entries as $entry) {
            $rowNumber++;
            $values = array_pad($entry['values'], $maxColumns, SituationReportDocument::BLANK_CELL);
            $cellStyles = $entry['style'] === 'metadata'
                ? [0 => $this->rowStyle('metadata-label'), 1 => $this->rowStyle('metadata-value')]
                : [];
            $writer->addRow($this->workbookRow($values, $this->rowStyle($entry['style']), $cellStyles));
            if ($entry['merge_rows'] !== null) {
                $writer->getOptions()->mergeCells(0, $rowNumber, 0, $rowNumber + $entry['merge_rows'] - 1, $sheetIndex);
            }
            if (in_array($entry['style'], ['letterhead', 'report-title', 'section-title', 'table-title', 'annex-note', 'sitio', 'purok', 'notice'], true)) {
                $writer->getOptions()->mergeCells(0, $rowNumber, $maxColumns - 1, $rowNumber, $sheetIndex);
            } elseif ($entry['style'] === 'metadata' && $maxColumns > 2) {
                $writer->getOptions()->mergeCells(1, $rowNumber, $maxColumns - 1, $rowNumber, $sheetIndex);
            }
        }
    }
}
