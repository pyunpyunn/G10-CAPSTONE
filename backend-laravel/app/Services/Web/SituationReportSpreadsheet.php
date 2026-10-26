<?php

namespace App\Services\Web;

use App\Support\SituationReportSections as Sections;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class SituationReportSpreadsheet
{
    private function value(mixed $v): string
    {
        if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d H:i:s');
        if (is_bool($v)) return $v ? 'Yes' : 'No';
        return $v === null || $v === '' ? 'Not recorded' : (string) $v;
    }

    private function workbookRow(array $values): Row
    {
        // OpenSpout treats leading '=' strings as formulas by default. All recorded text is literal.
        return new Row(array_map(fn ($v) => is_int($v) || is_float($v)
            ? \OpenSpout\Common\Entity\Cell::fromValue($v)
            : new \OpenSpout\Common\Entity\Cell\StringCell($this->value($v)), $values));
    }

    public function rows(array $r): array
    {
        $rows = array_map(fn ($v) => [$v], Sections::LETTERHEAD);
        $rows[] = [$r['title']];
        foreach (['supersedes'=>'Supersedes Situation Report No.','report_type'=>'Report type','event_id'=>'Event ID','disaster_name'=>'Event name','disaster_type'=>'Hazard type','generated_at'=>'Date/time prepared','period_start'=>'Reporting period start','period_end'=>'As of','coverage'=>'Area coverage','household_scope'=>'Detail scope'] as $key=>$title) $rows[]=[$title,$r[$key] ?? null];
        if ($r['scope_note']) $rows[]=['Scope note',$r['scope_note']];
        foreach ($r['sections'] as $key=>$section) {
            $rows[]=[];
            $rows[]=[$section['number'].'. '.$section['title']];
            foreach ($section['tables'] as $table) {
                if ($table['name']) $rows[]=[$table['name']];
                $rows[]=$table['headers'];
                array_push($rows,...$table['rows']);
            }
            if ($section['text'] || !$section['tables']) $rows[]=[$section['text']];
        }
        $rows[]=[];
        $rows[]=['Prepared by',$r['prepared_by']];
        $rows[]=['Reviewed by',$r['reviewed_by']];
        $rows[]=['Approved by (Punong Barangay, BDRRMC Chair)',$r['approved_by'] ?: '____________________'];
        $rows[]=[Sections::NOTICE];
        return $rows;
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
        $options = new \OpenSpout\Writer\XLSX\Options(
            FALLBACK_STYLE: new \OpenSpout\Common\Entity\Style\Style(fontSize: 10, shouldWrapText: true),
            DEFAULT_COLUMN_WIDTH: 28,
            pageSetup: new \OpenSpout\Writer\XLSX\Options\PageSetup(
                \OpenSpout\Writer\XLSX\Options\PageOrientation::PORTRAIT,
                \OpenSpout\Writer\XLSX\Options\PaperSize::A4, fitToHeight: 0, fitToWidth: 1),
            headerFooter: new \OpenSpout\Writer\XLSX\Options\HeaderFooter(
                oddFooter: '&amp;LContains personal data. Handle under RA 10173 (Data Privacy Act).&amp;RPage &amp;P of &amp;N')
        );
        $writer=new Writer($options); $writer->openToFile($path);
        try {
            $writer->getCurrentSheet()->setName('Summary');
            foreach ($this->rows($r) as $row) $writer->addRow($this->workbookRow($row));
        } finally { $writer->close(); }
    }
}
