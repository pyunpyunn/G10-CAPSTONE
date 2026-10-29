<?php
require dirname(__DIR__).'/vendor/autoload.php';
$pdo = new PDO('mysql:host=100.73.14.100;port=3306;dbname=klint;charset=utf8mb4', 'groupmate', getenv('RESQ_AUDIT_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]);
$service = new App\Services\Shared\ResqDatabaseIntegrity;
echo 'Preserved orphan references: '.$service->preserveOrphanReferences($pdo).PHP_EOL;
$result=$service->apply($pdo);
file_put_contents(__DIR__.'/klint-integrity-second-pass.json',json_encode($result,JSON_PRETTY_PRINT));
echo json_encode(['statements'=>count($result['sql']),'blocked'=>$result['blocked']],JSON_PRETTY_PRINT).PHP_EOL;
$counts=$pdo->query('SELECT r.role_key, COUNT(*) AS plaintext_count FROM users u JOIN roles r ON r.role_id=u.role_id WHERE u.temp_password IS NOT NULL GROUP BY r.role_key')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['plaintext_by_role'=>$counts],JSON_PRETTY_PRINT).PHP_EOL;
