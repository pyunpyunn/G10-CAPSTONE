<?php

namespace App\Services\Web;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Carbon;
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
use RuntimeException;
use ZipArchive;

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
        $workbook->setActiveSheetIndex(1);

        $writer = new Xlsx($workbook);
        $writer->setIncludeCharts(true);
        $writer->save($path);
        $workbook->disconnectWorksheets();
        $this->normalizeScatterAxes($path);
    }

    private function normalizeScatterAxes(string $path): void
    {
        // PhpSpreadsheet writes category-only elements on the scatter chart's numeric X axis.
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new RuntimeException('Could not open the generated impact workbook.');
        }
        try {
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = $archive->getNameIndex($index);
                if (! preg_match('~^xl/charts/chart\d+\.xml$~', $name)) {
                    continue;
                }
                $document = new DOMDocument;
                if (! $document->loadXML($archive->getFromName($name), LIBXML_NONET)) {
                    throw new RuntimeException('The generated chart XML is invalid.');
                }
                $xpath = new DOMXPath($document);
                $xpath->registerNamespace('c', 'http://schemas.openxmlformats.org/drawingml/2006/chart');
                if ($xpath->query('//c:scatterChart')->length === 0) {
                    continue;
                }
                foreach ($xpath->query('//c:plotArea[c:scatterChart]/c:valAx/*[self::c:auto or self::c:lblAlgn or self::c:lblOffset or self::c:noMultiLvlLbl]') as $node) {
                    $node->parentNode->removeChild($node);
                }
                foreach ($xpath->query('//c:plotArea[c:scatterChart]/c:valAx/c:scaling') as $scaling) {
                    foreach (['logBase', 'orientation', 'max', 'min', 'extLst'] as $tag) {
                        foreach ($xpath->query('c:'.$tag, $scaling) as $node) {
                            $scaling->appendChild($node);
                        }
                    }
                }
                if (! $archive->addFromString($name, $document->saveXML())) {
                    throw new RuntimeException('Could not write compatible chart axes.');
                }
            }
        } finally {
            if (! $archive->close()) {
                throw new RuntimeException('Could not save the generated impact workbook.');
            }
        }
    }

    private function writeReport($sheet, array $report): void
    {
        foreach (['A' => 7, 'B' => 24, 'C' => 12, 'D' => 16, 'E' => 12, 'F' => 12, 'G' => 15, 'H' => 18, 'I' => 20] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(11)->getColor()->setRGB('171717');
        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', $report['title']);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '111111']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->mergeCells('A2:I2');
        $sheet->setCellValue('A2', isset($report['event_name']) ? 'Disaster event: '.$report['event_name'] : 'Barangay Mambaling · Completed disaster events');
        $sheet->mergeCells('A3:I3');
        $sheet->setCellValue('A3', 'Generated: '.$this->displayTime($report['generated_at']));
        $sheet->getStyle('A2:I3')->getFont()->setSize(10)->getColor()->setRGB('333333');

        $row = 5;
        if (! empty($report['ui_rows'])) {
            $headers = ['Rank', 'Sitio', 'Current priority', 'Action', 'Households', 'Unsafe', 'Special needs', 'Unchecked members', 'No mobile / geotag'];
            $sheet->fromArray($headers, null, 'A5');
            $this->tableHeader($sheet, 5, 'I');
            $row = 6;
            foreach ($report['ui_rows'] as $entry) {
                $sheet->fromArray([$entry['rank'], $entry['sitio'], $entry['priority_score'], $entry['band']['label'], $entry['households'], $entry['impacted_households'], $entry['special_needs_members'], $entry['unchecked_members'], $entry['no_contact_households']], null, 'A'.$row, true);
                $sheet->getRowDimension($row++)->setRowHeight(30);
            }
            $sheet->getStyle('A5:I'.($row - 1))->getAlignment()->setWrapText(true);
            $sheet->getStyle('C6:C'.($row - 1))->getNumberFormat()->setFormatCode('0"%"');
            $sheet->getStyle('A6:I'.($row - 1))->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A6:A'.($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C6:C'.($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E6:I'.($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->setAutoFilter('A5:I'.($row - 1));
            $row += 2;
        }
        if ($report['type'] === 'ranking') {
            if ($report['status_groups'] !== []) {
                $row = $this->sectionTitle($sheet, $row, 'Household status rankings');
                foreach ($report['status_groups'] as $group) {
                    $row = $this->sectionTitle($sheet, $row, ucfirst(str_replace('_', ' ', $group['status'])));
                    $sheet->fromArray(['Rank', 'Sitio', 'Peak count', 'Current count', 'Peak recorded'], null, 'A'.$row);
                    $sheet->mergeCells("E{$row}:I{$row}");
                    $this->tableHeader($sheet, $row++, 'I');
                    foreach ($group['entries'] as $entry) {
                        $sheet->fromArray([$entry['rank'], $entry['sitio'], $entry['quantity'], $entry['current'], $this->displayTime($entry['peak_at'])], null, 'A'.$row, true);
                        $sheet->mergeCells("E{$row}:I{$row}");
                        $sheet->getRowDimension($row++)->setRowHeight(22);
                    }
                    $row++;
                }
            }
            if ($report['priority_rows'] !== []) {
                $row = $this->sectionTitle($sheet, $row, 'Historical priority scores — recorded measurements');
                $sheet->fromArray(['Rank', 'Sitio', 'Latest score', 'Peak score', 'Peak recorded'], null, 'A'.$row);
                $sheet->mergeCells("E{$row}:I{$row}");
                $this->tableHeader($sheet, $row++, 'I');
                foreach ($report['priority_rows'] as $entry) {
                    $sheet->fromArray([$entry['rank'], $entry['sitio'], $entry['current_score'], $entry['peak']['score'], $this->displayTime($entry['peak']['at'])], null, 'A'.$row, true);
                    $sheet->mergeCells("E{$row}:I{$row}");
                    $sheet->getStyle("C{$row}:D{$row}")->getNumberFormat()->setFormatCode('0.0"%"');
                    $sheet->getRowDimension($row++)->setRowHeight(22);
                }
                $row++;
                $sheet->fromArray(['Rank', 'Sitio', 'Metric', null, 'Peak count', 'Recorded'], null, 'A'.$row);
                $sheet->mergeCells("C{$row}:D{$row}");
                $sheet->mergeCells("F{$row}:I{$row}");
                $this->tableHeader($sheet, $row++, 'I');
                foreach ($report['priority_rows'] as $entry) {
                    $breakdown = $entry['metric_peaks'];
                    foreach ([
                        'impact_open' => 'Unsafe',
                        'special_open' => 'Special needs unresolved',
                        'unreported_open' => 'Unreported members',
                        'no_contact_open' => 'No-contact households',
                    ] as $key => $label) {
                        if (isset($breakdown[$key])) {
                            $sheet->fromArray([$entry['rank'], $entry['sitio'], $label, null, $breakdown[$key]['quantity'], $this->displayTime($breakdown[$key]['at'])], null, 'A'.$row, true);
                            $sheet->mergeCells("C{$row}:D{$row}");
                            $sheet->mergeCells("F{$row}:I{$row}");
                            $sheet->getRowDimension($row++)->setRowHeight(22);
                        }
                    }
                }
            }
        } elseif ($report['type'] === 'purok-impact') {
            $row = $this->sectionTitle($sheet, $row, 'Sitio impact highlights');
            $sheet->fromArray(['No.', 'Sitio', 'Peak impact', 'Latest impact', 'Peak recorded'], null, 'A'.$row);
            $sheet->mergeCells("E{$row}:I{$row}");
            $this->tableHeader($sheet, $row++, 'I');
            foreach ($report['summaries'] as $index => $summary) {
                $sheet->fromArray([$index + 1, $summary['sitio'], $summary['peak'], $summary['latest'], $this->displayTime($summary['peak_at'])], null, 'A'.$row, true);
                $sheet->mergeCells("E{$row}:I{$row}");
                $sheet->getStyle("C{$row}:D{$row}")->getNumberFormat()->setFormatCode('0.0"%"');
                $sheet->getStyle("A{$row}:I{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CCCCCC');
                $sheet->getRowDimension($row++)->setRowHeight(22);
            }
            if ($report['summaries'] !== []) {
                $sheet->setAutoFilter('A6:I'.($row - 1));
            }
        } else {
            $row = $this->sectionTitle($sheet, $row, 'Overall recorded impact');
            $sheet->mergeCells("A{$row}:H{$row}");
            $sheet->setCellValue("A{$row}", $report['event_count'].' completed events · '.$report['assessed_households'].' assessed households');
            $sheet->getStyle("A{$row}:H{$row}")->getFont()->setSize(11);
            $row += 2;
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->mergeCells("C{$row}:D{$row}");
            $sheet->setCellValue("A{$row}", 'Impact');
            $sheet->setCellValue("C{$row}", 'Recorded total');
            $this->tableHeader($sheet, $row++, 'D');
            foreach (['deaths' => 'Deaths', 'injured' => 'Injured', 'missing' => 'Missing', 'damaged_houses' => 'Damaged houses'] as $key => $label) {
                $sheet->mergeCells("A{$row}:B{$row}");
                $sheet->mergeCells("C{$row}:D{$row}");
                $sheet->setCellValue("A{$row}", $label);
                $sheet->setCellValue("C{$row}", $report['totals'][$key]);
                $sheet->getStyle("A{$row}:D{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CCCCCC');
                $sheet->getRowDimension($row++)->setRowHeight(22);
            }
        }

        $sheet->freezePane($report['type'] === 'purok-impact' ? 'C7' : (empty($report['ui_rows']) ? 'A5' : 'C6'));
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->setShowGridlines(false);
    }

    private function tableHeader($sheet, int $row, string $lastColumn): void
    {
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '111111']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEEEEE']],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '777777']]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(32);
    }

    private function displayTime(?string $value): string
    {
        return $value ? Carbon::parse($value)->setTimezone('Asia/Manila')->format('d M Y, H:i') : 'Not recorded';
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
            if (! empty($report['ui_rows'])) {
                foreach ($report['ranking_types'] as $mode) {
                    $rows = $report['ui_rows'];
                    $keys = $mode === 'status'
                        ? ['unsafe' => ['Unsafe', '#962020'], 'unchecked' => ['Unchecked', '#6a7f94'], 'evacuated' => ['Evacuated', '#2a5a90'], 'safe' => ['Safe', '#3a7d57']]
                        : ['impact' => ['Unsafe households', '#962020'], 'special_needs' => ['Special needs', '#8f5c18'], 'unreported' => ['Unchecked', '#6a7f94'], 'no_contact' => ['No mobile / geotag', '#1b3050']];
                    $series = [];
                    foreach ($keys as $key => [$label, $color]) {
                        $series[] = ['label' => $label, 'color' => $color, 'values' => array_map(fn ($entry) => $mode === 'status'
                            ? ($entry['households'] ? 100 * ($entry['status_counts'][$key] ?? 0) / $entry['households'] : 0)
                            : (float) ($entry['contributions'][$key] ?? 0), $rows)];
                    }
                    $columns = $this->writeMatrix($dataSheet, 'A'.($nextRow * 100), array_column($rows, 'sitio'), $series);
                    $this->addChart($chartSheet, $mode === 'status' ? 'Household status by sitio' : 'Priority by sitio', DataSeries::TYPE_BARCHART,
                        $columns['category'], $columns['series'], $nextRow, 22, '', 'Percentage', false, null, 100, true);
                    $nextRow += 25;
                }
            }
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
            $columns = ['category' => 'A', 'series' => []];
            if ($numericCategories) {
                foreach ($report['chart']['series'] as $index => $item) {
                    $samples = $item['samples'] ?? array_map(fn ($x, $y) => ['x' => $x, 'y' => $y], $categories, $item['values']);
                    if ($samples === []) {
                        continue;
                    }
                    $item['values'] = array_column($samples, 'y');
                    $column = Coordinate::stringFromColumnIndex(1 + 2 * $index);
                    $matrix = $this->writeMatrix($dataSheet, $column.'1', array_column($samples, 'x'), [$item]);
                    $matrix['series'][0]['category_column'] = $column;
                    $columns['series'][] = $matrix['series'][0];
                }
            } else {
                $columns = $this->writeMatrix($dataSheet, 'A1', $categories, $report['chart']['series']);
            }
            if ($report['chart']['labels'] !== [] && $columns['series'] !== []) {
                $topRow = 3;
                if ($report['type'] === 'past-event-impact') {
                    $chartSheet->mergeCells('A3:N3');
                    $chartSheet->setCellValue('A3', implode('     ', array_map(fn ($key, $label) => $label.': '.number_format($report['totals'][$key]),
                        ['deaths', 'injured', 'missing', 'damaged_houses'], ['Deaths', 'Injured', 'Missing', 'Damaged houses'])));
                    $chartSheet->getStyle('A3:N3')->getFont()->setBold(true)->setSize(14);
                    $topRow = 5;
                }
                $this->addChart($chartSheet, $report['title'],
                    $numericCategories ? DataSeries::TYPE_SCATTERCHART : DataSeries::TYPE_LINECHART,
                    $columns['category'], $columns['series'], $topRow, 24,
                    $report['chart']['axis_label'], $report['chart']['value_label'], $numericCategories,
                    $numericCategories ? (float) ($report['chart']['x_max_value'] ?? max(1 / 3600, (float) max($categories))) : null,
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
                'values' => $series[$index]['values'],
                'categories' => $categories,
                'header_row' => $startRow,
            ], array_keys($series)),
            'first_row' => $startRow + 1,
            'last_row' => $lastRow,
        ];
    }

    private function addChart($sheet, string $title, string $type, string $categoryColumn, array $series, int $topRow, int $height, string $xAxisLabel = '', string $yAxisLabel = '', bool $numericCategories = false, ?float $xAxisMaximum = null, ?float $yAxisMaximum = null, bool $stacked = false): void
    {
        if ($series === []) {
            return;
        }
        $firstRow = $series[0]['header_row'] + 1;
        $lastRow = $firstRow + count($series[0]['values']) - 1;
        $categories = new DataSeriesValues(
            $numericCategories ? DataSeriesValues::DATASERIES_TYPE_NUMBER : DataSeriesValues::DATASERIES_TYPE_STRING,
            "'Chart data'!\${$categoryColumn}\${$firstRow}:\${$categoryColumn}\${$lastRow}",
            null,
            count($series[0]['values'] ?? []),
        );
        $categories->setDataValues($series[0]['categories']);
        $labels = [];
        $values = [];
        $categorySeries = [];
        foreach ($series as $item) {
            $lastRow = $firstRow + count($item['values']) - 1;
            if ($numericCategories) {
                $column = $item['category_column'] ?? $categoryColumn;
                $categorySeries[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER,
                    "'Chart data'!\${$column}\${$firstRow}:\${$column}\${$lastRow}", null, count($item['categories']), $item['categories']);
            }
            $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Chart data'!\${$item['column']}\${$item['header_row']}", null, 1, [$item['label']]);
            $values[] = new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_NUMBER,
                "'Chart data'!\${$item['column']}\${$firstRow}:\${$item['column']}\${$lastRow}",
                null,
                count($item['values']),
                $item['values'],
                null,
                $item['color'],
            );
            if ($type !== DataSeries::TYPE_BARCHART) {
                $values[array_key_last($values)]->setSmoothLine(true)->setPointMarker('circle')->setPointSize(3);
                $values[array_key_last($values)]->setLineWidth(1.2)->setScatterLines(true);
                $values[array_key_last($values)]->setLineColorProperties($item['color']);
            }
        }
        $plot = new DataSeries(
            $type,
            $numericCategories ? null : ($stacked ? DataSeries::GROUPING_STACKED : ($type === DataSeries::TYPE_BARCHART ? DataSeries::GROUPING_CLUSTERED : DataSeries::GROUPING_STANDARD)),
            array_keys($values),
            $labels,
            $numericCategories ? $categorySeries : [$categories],
            $values,
            $type === DataSeries::TYPE_BARCHART && ! $stacked ? DataSeries::DIRECTION_BAR : DataSeries::DIRECTION_COL,
            true,
            $numericCategories ? DataSeries::STYLE_SMOOTHMARKER : null,
        );
        $xAxis = new Axis;
        if ($numericCategories) {
            $xAxis->setAxisOptionsProperties('nextTo', minimum: 0, maximum: $xAxisMaximum);
            $xAxis->setAxisNumberProperties('0.##"h"', true);
        }
        $yAxis = new Axis;
        if ($yAxisMaximum !== null) {
            $yAxis->setAxisOptionsProperties('nextTo', minimum: 0, maximum: $yAxisMaximum);
            $yAxis->setAxisNumberProperties('0"%"');
        }
        $chart = new Chart($title, new Title($title), new Legend(Legend::POSITION_BOTTOM, null, false),
            new PlotArea(null, [$plot]), false, DataSeries::EMPTY_AS_GAP,
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
