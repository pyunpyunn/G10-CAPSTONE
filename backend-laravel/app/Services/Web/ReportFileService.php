<?php

namespace App\Services\Web;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ReportFileService
{
    public function generate(string $type, string $format, array $headers, iterable $rows): array
    {
        $format = $format === 'excel' ? 'xlsx' : $format;
        if (!in_array($format, ['pdf', 'csv', 'xlsx'], true)) {
            throw ValidationException::withMessages(['format' => 'Select PDF, CSV, or Excel.']);
        }
        $filename = $type.'_'.now()->format('Ymd_His').'_'.Str::random(16).'.'.$format;
        $path = tempnam(sys_get_temp_dir(), 'report-');
        try {
            if ($format === 'xlsx') {
                $writer = new Writer();
                $writer->openToFile($path);
                try {
                    $writer->addRow(Row::fromValues(array_values($headers)));
                    foreach ($rows as $row) $writer->addRow(Row::fromValues(array_map(fn ($value) => (string) ($value ?? ''), $row)));
                } finally { $writer->close(); }
            } elseif ($format === 'csv') {
                $handle = fopen($path, 'w');
                try {
                    fwrite($handle, "\xEF\xBB\xBF");
                    fputcsv($handle, array_values($headers), ',', '"', '');
                    foreach ($rows as $row) {
                        fputcsv($handle, array_map(function ($value) {
                            $text = (string) ($value ?? '');
                            return preg_match('/^[\s]*[=+@-]/u', $text) ? "'".$text : $text;
                        }, $row), ',', '"', '');
                    }
                } finally { fclose($handle); }
            } else {
                $pdfRows = [];
                foreach ($rows as $row) {
                    if (count($pdfRows) >= config('reports.pdf_max_rows')) {
                        throw ValidationException::withMessages(['format' => 'This report is too large for PDF. Narrow the filters or select CSV or Excel.']);
                    }
                    $pdfRows[] = $row;
                }
                Pdf::loadView('reports.table', ['title' => str_replace('-', ' ', $type), 'headers' => $headers, 'rows' => $pdfRows, 'generatedAt' => now()])
                    ->setPaper('a4', 'landscape')->save($path);
            }
            $disk = Storage::disk(config('reports.disk'));
            $handle = fopen($path, 'r');
            try {
                if (!$disk->put('reports/'.$filename, $handle)) throw new \RuntimeException('Report storage failed.');
            } finally { fclose($handle); }
            $expires = now()->addMinutes(config('reports.expires_minutes'));
            $url = config('reports.disk') === 's3'
                ? $disk->temporaryUrl('reports/'.$filename, $expires)
                : URL::temporarySignedRoute('external.reports.download', $expires, ['filename' => $filename]);
            return ['status' => 'success', 'report_type' => $type, 'format' => $format, 'file_name' => $filename, 'download_url' => $url,
                'expires_at' => $expires->toIso8601String(), 'expires_in_minutes' => config('reports.expires_minutes')];
        } finally { if (file_exists($path)) unlink($path); }
    }
}
