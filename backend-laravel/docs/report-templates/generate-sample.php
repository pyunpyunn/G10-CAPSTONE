<?php

// Synthetic print preview only. No application records or credentials are read into the document.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$summary = [
    'schema_version'=>'4.0',
    'included_sections'=>array_keys(\App\Support\SituationReportSections::LABELS),
    'event'=>['event_id'=>'SAMPLE-EVENT','name'=>'SAMPLE — Template verification only','type'=>'Flood','severity'=>'Monitoring','coverage'=>'Barangay Mambaling — all sitios'],
    'report'=>['report_number'=>'SAMPLE 1','report_type'=>'initial','period_start'=>'2026-10-19 08:00:00','period_end'=>'2026-10-19 12:00:00','generated_at'=>'2026-10-19 12:00:00'],
];
$pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML(view('reports.situation',['summary'=>$summary])->render())->setPaper('a4','portrait');
$pdf->render();
$pdf->getDomPDF()->getCanvas()->page_text(460,815,'Page {PAGE_NUM} of {PAGE_COUNT}',null,8);
$pdf->save(__DIR__.'/SAMPLE-BDRRMC-Situation-Report.pdf');
echo "Synthetic sample PDF generated.\n";

