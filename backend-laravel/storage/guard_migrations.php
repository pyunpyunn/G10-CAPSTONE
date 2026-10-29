<?php
$files=[
'2026_10_04_000006_create_rescue_criteria_snapshots_table.php',
'2026_10_05_000001_create_rescue_criteria_line_snapshots.php',
'2026_10_05_000002_create_rescue_criteria_contact_baselines.php',
'2026_10_08_000002_create_household_mobile_notifications_table.php',
'2026_10_15_000001_create_household_geographic_classification_tables.php',
'2026_10_17_000001_create_external_report_requests_table.php',
'2026_10_18_000021_create_situation_report_submissions.php',
'2026_10_18_000031_create_navigation_tables.php',
'2026_10_21_000001_create_web_push_tables.php',
];
foreach($files as $file) {
 $path=dirname(__DIR__).'/database/migrations/'.$file;
 $code=file_get_contents($path);
 $code=preg_replace_callback('/^        Schema::create\(\x27([^\x27]+)\x27.*?^        \}\);/ms',function($m) {
  return "        if (! Schema::hasTable('".$m[1]."')) {\n".preg_replace('/^/m','    ',$m[0])."\n        }";
 },$code);
 file_put_contents($path,$code);
}
