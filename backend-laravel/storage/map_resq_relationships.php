<?php
require dirname(__DIR__).'/vendor/autoload.php';
$models=[];
foreach(glob(dirname(__DIR__).'/app/Models/*.php') as $file) {
 $class='App\\Models\\'.basename($file,'.php');
 $model=new $class;
 $models[$model->getTable()]=$class;
}
foreach(App\Services\Shared\ResqDatabaseIntegrity::RELATIONSHIPS as [$table,$key,$parent,$pk]) {
 if(!isset($models[$table],$models[$parent]))continue;
 $class=$models[$table];
 $name=Illuminate\Support\Str::camel(preg_replace('/_id$/','',$key));
 if(method_exists($class,$name))continue;
 $file=dirname(__DIR__).'/app/Models/'.class_basename($class).'.php';
 $code=file_get_contents($file);
 $body="\n    public function $name(): \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo\n    {\n        return \$this->belongsTo(\\".$models[$parent]."::class, '$key', '$pk');\n    }\n";
 $end=strrpos($code,'}');
 $code=substr($code,0,$end).$body.substr($code,$end);
 file_put_contents($file,$code);
}
echo "Updated Eloquent relationships using verified child and parent key mappings.\n";
