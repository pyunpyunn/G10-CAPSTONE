<?php

namespace App\Services\Web;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Axis;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImpactReportSpreadsheet
{
    public function write(string $path, array $report): void
    {
        $workbook = new Spreadsheet;
        $info = $workbook->getActiveSheet();
        $info->setTitle('Report');
        $graph = $workbook->createSheet();
        $graph->setTitle('Graphs');
        $data = $workbook->createSheet();
        $data->setTitle('Chart data');
        $data->setSheetState($data::SHEETSTATE_VERYHIDDEN);

        $this->writeReport($info, $report);
        $this->writeCharts($graph, $data, $report);

        $writer = new Xlsx($workbook);
        $writer->setIncludeCharts(true);
        $writer->save($path);
        $workbook->disconnectWorksheets();
    }

    private function writeReport($sheet, array $report): void
    {
        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setWidth(17);
        }
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(19);
        $sheet->getColumnDimension('H')->setWidth(25);
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Aptos')->setSize(11)->getColor()->setRGB('24364B');
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', $report['title']);
        $sheet->getRowDimension(1)->setRowHeight(34);
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 20, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '173B5E']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', isset($report['event_name']) ? 'Disaster event: '.$report['event_name'] : 'Barangay Mambaling · Completed disaster events');
        $sheet->mergeCells('A3:H3');
        $sheet->setCellValue('A3', 'Generated: '.$report['generated_at']);
        $sheet->getStyle('A2:H3')->getFont()->getColor()->setRGB('64748B');

        $row = 5;
        if ($report['type'] === 'ranking') {
            if ($report['status_groups'] !== []) {
                $row = $this->sectionTitle($sheet, $row, 'Household status rankings');
                foreach ($report['status_groups'] as $group) {
                    $row = $this->sectionTitle($sheet, $row, ucfirst(str_replace('_', ' ', $group['status'])));
                    foreach ($group['entries'] as $entry) {
                        $row = $this->rankingCard($sheet, $row, $entry['rank'], $entry['sitio'],
                            'Highest: '.$entry['quantity'], 'Current: '.$entry['current'], 'Peak: '.$entry['peak_at']);
                    }
                }
            }
            if ($report['priority_rows'] !== []) {
                $row = $this->sectionTitle($sheet, $row, 'Priority ranking');
                foreach ($report['priority_rows'] as $entry) {
                    $breakdown = $entry['metric_peaks'];
                    $detail = [];
                    foreach ([
                        'impact_open' => 'Unsafe',
                        'special_open' => 'Special needs unresolved',
                        'unreported_open' => 'Unreported members',
                        'no_contact_open' => 'No-contact households',
                    ] as $key => $label) {
                        if (isset($breakdown[$key])) {
                            $detail[] = $label.': '.$breakdown[$key]['quantity'].' ('.$breakdown[$key]['at'].')';
                        }
                    }
                    $row = $this->rankingCard($sheet, $row, $entry['rank'], $entry['sitio'],
                        'Peak score: '.$entry['peak']['score'], 'Current: '.$entry['current_score'],
                        implode(' · ', $detail));
                }
            }
        } elseif ($report['type'] === 'purok-impact') {
            $row = $this->sectionTitle($sheet, $row, 'Sitio impact highlights');
            foreach ($report['summaries'] as $summary) {
                $row = $this->rankingCard($sheet, $row, null, $summary['sitio'],
                    'Highest: '.$summary['peak'], 'Latest: '.$summary['latest'],
                    'Peak recorded: '.$summary['peak_at']);
            }
        } else {
            $row = $this->sectionTitle($sheet, $row, 'Overall recorded impact');
            $sheet->mergeCells("A{$row}:H{$row}");
            $sheet->setCellValue("A{$row}", $report['event_count'].' completed events · '.$report['assessed_households'].' assessed households');
            $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true)->setSize(14);
            $row += 2;
            $this->metricCard($sheet, $row, 'Deaths', $report['totals']['deaths'], 'DC2626');
            $this->metricCard($sheet, $row, 'Injured', $report['totals']['injured'], 'D97706', 4);
            $this->metricCard($sheet, $row + 3, 'Missing', $report['totals']['missing'], '7C3AED');
            $this->metricCard($sheet, $row + 3, 'Damaged houses', $report['totals']['damaged_houses'], '2563EB', 4);
        }

        $sheet->freezePane('A5');
        $sheet->setShowGridlines(false);
    }

    private function writeCharts($chartSheet, $dataSheet, array $report): void
    {
        foreach (range('A', 'Z') as $column) {
            $chartSheet->getColumnDimension($column)->setWidth(12);
        }
        $chartSheet->setShowGridlines(false);
        $chartSheet->mergeCells('A1:N1');
        $chartSheet->setCellValue('A1', $report['title'].' · Visual report');
        $chartSheet->getStyle('A1:N1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => '173B5E']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $chartSheet->getRowDimension(1)->setRowHeight(32);

        if ($report['type'] === 'ranking') {
            $nextRow = 3;
            $charts = [];
            if ($report['chart']['status_series'] !== [] && $report['chart']['labels'] !== []) {
                $columns = $this->writeMatrix($dataSheet, 'A1', $report['chart']['labels'], $report['chart']['status_series']);
                $charts[] = ['title' => 'Highest recorded household status counts', 'type' => DataSeries::TYPE_BARCHART,
                    'category_column' => $columns['category'], 'series_columns' => $columns['series'], 'top' => $nextRow];
                $nextRow += 24;
            }
            if ($report['chart']['priority_series'] !== []) {
                $priority = $report['chart']['priority_series'][0];
                $columns = $this->writeMatrix($dataSheet, 'J1', $priority['labels'], [$priority]);
                $charts[] = ['title' => 'Highest recorded priority score', 'type' => DataSeries::TYPE_BARCHART,
                    'category_column' => $columns['category'], 'series_columns' => $columns['series'], 'top' => $nextRow];
            }
            foreach ($charts as $index => $chart) {
                $this->addChart($chartSheet, $chart['title'], $chart['type'], $chart['category_column'],
                    $chart['series_columns'], $chart['top'], $index === 0 && count($charts) > 1 ? 20 : 16);
            }
        } else {
            $numericCategories = isset($report['chart']['x_values']);
            $categories = $numericCategories ? $report['chart']['x_values'] : $report['chart']['labels'];
            $columns = $this->writeMatrix($dataSheet, 'A1', $categories, $report['chart']['series']);
            if ($report['chart']['labels'] !== [] && $columns['series'] !== []) {
                $this->addChart($chartSheet, $report['title'],
                    $numericCategories ? DataSeries::TYPE_SCATTERCHART : DataSeries::TYPE_LINECHART,
                    $columns['category'], $columns['series'], 3, 24,
                    $report['chart']['axis_label'], $report['chart']['value_label'], $numericCategories,
                    $numericCategories ? max(1, (float) max($categories)) : null,
                    $numericCategories ? (float) ($report['chart']['max_value'] ?? 100) : null);
            } else {
                $chartSheet->mergeCells('A3:N3');
                $chartSheet->setCellValue('A3', 'There is not enough recorded data to draw a chart.');
            }
        }

        $dataSheet->setSheetState($dataSheet::SHEETSTATE_VERYHIDDEN);
    }

    private function writeMatrix($sheet, string $startCell, array $categories, array $series): array
    {
        [$startColumn, $startRow] = Coordinate::coordinateFromString($startCell);
        $baseColumn = Coordinate::columnIndexFromString($startColumn);
        $sheet->setCellValue([$baseColumn, $startRow], 'Category');
        foreach ($series as $index => $item) {
            $column = $baseColumn + $index + 1;
            $sheet->setCellValue([$column, $startRow], $item['label']);
            foreach ($categories as $rowIndex => $category) {
                $sheet->setCellValue([$baseColumn, $startRow + $rowIndex + 1], $category);
                $sheet->setCellValue([$column, $startRow + $rowIndex + 1], $item['values'][$rowIndex] ?? null);
            }
        }

        $lastRow = $startRow + count($categories);

        return [
            'category' => Coordinate::stringFromColumnIndex($baseColumn),
            'series' => array_map(fn (int $index): array => [
                'column' => Coordinate::stringFromColumnIndex($baseColumn + $index + 1),
                'label' => $series[$index]['label'],
                'color' => ltrim($series[$index]['color'] ?? '2563EB', '#'),
            ], array_keys($series)),
            'first_row' => $startRow + 1,
            'last_row' => $lastRow,
        ];
    }

    private function addChart($sheet, string $title, string $type, string $categoryColumn, array $series, int $topRow, int $height, string $xAxisLabel = '', string $yAxisLabel = '', bool $numericCategories = false, ?float $xAxisMaximum = null, ?float $yAxisMaximum = null): void
    {
        if ($series === []) {
            return;
        }
        $categories = new DataSeriesValues(
            $numericCategories ? DataSeriesValues::DATASERIES_TYPE_NUMBER : DataSeriesValues::DATASERIES_TYPE_STRING,
            "'Chart data'!\${$categoryColumn}\$2:\${$categoryColumn}\$".(count($series[0]['values'] ?? []) + 1),
            null,
            count($series[0]['values'] ?? []),
        );
        $labels = [];
        $values = [];
        foreach ($series as $item) {
            $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Chart data'!\${$item['column']}\$1", null, 1);
            $values[] = new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_NUMBER,
                "'Chart data'!\${$item['column']}\$2:\${$item['column']}\$".(count($series[0]['values'] ?? []) + 1),
                null,
                count($series[0]['values'] ?? []),
                null,
                null,
                $item['color'],
            );
        }
        $plot = new DataSeries(
            $type,
            $type === DataSeries::TYPE_BARCHART ? DataSeries::GROUPING_CLUSTERED : DataSeries::GROUPING_STANDARD,
            array_keys($values),
            $labels,
            [$categories],
            $values,
            $type === DataSeries::TYPE_BARCHART ? DataSeries::DIRECTION_BAR : DataSeries::DIRECTION_COL,
            true,
            in_array($type, [DataSeries::TYPE_LINECHART, DataSeries::TYPE_SCATTERCHART], true) ? DataSeries::STYLE_LINEMARKER : null,
        );
        $xAxis = new Axis;
        if ($numericCategories) {
            $xAxis->setAxisOptionsProperties('nextTo', minimum: 0, maximum: $xAxisMaximum);
        }
        $yAxis = new Axis;
        if ($yAxisMaximum !== null) {
            $yAxis->setAxisOptionsProperties('nextTo', minimum: 0, maximum: $yAxisMaximum);
        }
        $chart = new Chart($title, new Title($title), new Legend(Legend::POSITION_BOTTOM, null, false),
            new PlotArea(null, [$plot]), true, DataSeries::EMPTY_AS_GAP,
            $xAxisLabel !== '' ? new Title($xAxisLabel) : null,
            $yAxisLabel !== '' ? new Title($yAxisLabel) : null,
            $xAxis, $yAxis);
        $chart->setTopLeftPosition('A'.$topRow);
        $chart->setBottomRightPosition('N'.($topRow + $height));
        $sheet->addChart($chart);
    }

    private function sectionTitle($sheet, int $row, string $title): int
    {
        $sheet->mergeCells("A{$row}:H{$row}");
        $sheet->setCellValue("A{$row}", $title);
        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '173B5E']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF1F7']],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCD8E4']]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);

        return $row + 1;
    }

    private function rankingCard($sheet, int $row, ?int $rank, string $sitio, string $peak, string $current, ?string $detail): int
    {
        $sheet->setCellValue("A{$row}", $rank === null ? '' : '#'.$rank);
        $sheet->mergeCells("B{$row}:C{$row}");
        $sheet->setCellValue("B{$row}", $sitio);
        $sheet->mergeCells("D{$row}:E{$row}");
        $sheet->setCellValue("D{$row}", $peak);
        $sheet->mergeCells("F{$row}:G{$row}");
        $sheet->setCellValue("F{$row}", $current);
        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F7FAFC']],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'D7E1EA']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setRGB('2563EB');
        $sheet->getStyle("B{$row}")->getFont()->setBold(true);
        $sheet->getRowDimension($row)->setRowHeight(24);
        if ($detail === null || $detail === '') {
            return $row + 1;
        }

        $detailRow = $row + 1;
        $sheet->mergeCells("B{$detailRow}:H{$detailRow}");
        $sheet->setCellValue("B{$detailRow}", $detail);
        $sheet->getStyle("A{$detailRow}:H{$detailRow}")->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => '64748B']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'D7E1EA']]],
        ]);
        $sheet->getRowDimension($detailRow)->setRowHeight(30);

        return $row + 2;
    }

    private function metricCard($sheet, int $row, string $label, int $value, string $color, int $column = 0): void
    {
        $start = $column + 1;
        $end = $column + 4;
        $startName = Coordinate::stringFromColumnIndex($start);
        $endName = Coordinate::stringFromColumnIndex($end);
        $sheet->mergeCells("{$startName}{$row}:{$endName}{$row}");
        $sheet->mergeCells("{$startName}".($row + 1).":{$endName}".($row + 1));
        $sheet->setCellValue("{$startName}{$row}", $label);
        $sheet->setCellValue("{$startName}".($row + 1), $value);
        $sheet->getStyle("{$startName}{$row}:{$endName}".($row + 1))->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F6FA']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("{$startName}{$row}")->getFont()->getColor()->setRGB($color);
        $sheet->getStyle("{$startName}".($row + 1))->getFont()->setBold(true)->setSize(21);
        $sheet->getRowDimension($row)->setRowHeight(24);
        $sheet->getRowDimension($row + 1)->setRowHeight(36);
    }
}
