<?php
require dirname(__DIR__).'/vendor/autoload.php';
$pdo=new PDO('mysql:host=100.73.14.100;port=3306;dbname=klint;charset=utf8mb4','groupmate',getenv('RESQ_AUDIT_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]);
$schema=json_decode(file_get_contents(__DIR__.'/klint-schema.json'),true);
$tables=['devices','team_bases','rescue_criteria_snapshots','rescue_criteria_line_snapshots','rescue_criteria_contact_baselines','household_mobile_notifications','purok_boundaries','household_geographic_classifications','external_report_requests','situation_report_submissions','official_advisory_notifications','household_password_recovery_otps','household_login_accounts','household_device_owners','resq_orphan_references'];
foreach($tables as $t) $schema[$t]=$pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
file_put_contents(__DIR__.'/klint-schema.json',json_encode($schema,JSON_PRETTY_PRINT));
echo 'Updated schema metadata for '.count($tables).' operational tables.'.PHP_EOL;
