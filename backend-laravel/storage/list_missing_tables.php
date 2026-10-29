<?php
$schema=json_decode(file_get_contents(__DIR__.'/klint-schema.json'),true);
foreach(glob(dirname(__DIR__).'/database/migrations/2026_10*.php') as $file){
 preg_match_all("/Schema::create\('([^']+)'/",file_get_contents($file),$matches);
 $missing=array_values(array_filter($matches[1],fn($t)=>!isset($schema[$t])));
 if($missing) echo basename($file).': '.implode(', ',$missing).PHP_EOL;
}
