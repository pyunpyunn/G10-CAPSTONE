<?php
require dirname(__DIR__).'/vendor/autoload.php';
$pdo = new PDO('mysql:host=100.73.14.100;port=3306;dbname=klint;charset=utf8mb4', 'groupmate', getenv('RESQ_AUDIT_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
$service = new App\Services\Shared\ResqDatabaseIntegrity;
$result = in_array('--apply', $argv, true) ? $service->apply($pdo) : $service->plan($pdo);
file_put_contents(__DIR__.'/klint-integrity-'.(in_array('--apply', $argv, true) ? 'applied' : 'plan').'.json', json_encode($result, JSON_PRETTY_PRINT));
echo json_encode(['statements' => count($result['sql']), 'blocked' => $result['blocked']], JSON_PRETTY_PRINT).PHP_EOL;
