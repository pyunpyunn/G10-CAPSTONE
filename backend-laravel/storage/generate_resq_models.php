<?php
require dirname(__DIR__).'/vendor/autoload.php';
$schema = json_decode(file_get_contents(__DIR__.'/klint-schema.json'), true, 512, JSON_THROW_ON_ERROR);
$models = [
 'NavigationDevice'=>'devices', 'RescueCriteriaSnapshot'=>'rescue_criteria_snapshots', 'RescueCriteriaLineSnapshot'=>'rescue_criteria_line_snapshots', 'HouseholdMobileNotification'=>'household_mobile_notifications', 'PurokBoundary'=>'purok_boundaries', 'HouseholdGeographicClassification'=>'household_geographic_classifications', 'ExternalReportRequest'=>'external_report_requests', 'OfficialAdvisoryNotification'=>'official_advisory_notifications', 'HouseholdPasswordRecoveryOtp'=>'household_password_recovery_otps', 'HouseholdLoginAccount'=>'household_login_accounts', 'HouseholdDeviceOwner'=>'household_device_owners', 'DispatchTrip'=>'dispatch_trips', 'TripPoint'=>'trip_points', 'SyncEvent'=>'sync_events',
 'OfflinePack'=>'offline_packs', 'PasswordResetRequest'=>'password_reset_requests',
 'RememberedDevice'=>'remembered_devices', 'ReportIdempotency'=>'report_idempotency',
 'PropertyDamageLevel'=>'property_damage_levels', 'FieldReportCategory'=>'field_report_categories',
 'ResponderCheckIn'=>'responder_check_ins', 'ResponderCommunicationLog'=>'responder_communication_logs',
 'ResponderFieldReportDetail'=>'responder_field_report_details', 'ResponderFieldReportMember'=>'responder_field_report_members',
 'ResponderFieldReport'=>'responder_field_reports', 'MemberStatusLog'=>'member_status_logs',
 'HqFieldReport'=>'hq_field_reports', 'IncidentArchive'=>'incident_archives',
 'MapTileMetadata'=>'map_tile_metadata', 'MappingHazardLayer'=>'mapping_hazard_layers',
 'RealtimeOutbox'=>'realtime_outbox', 'UserNotification'=>'user_notifications',
 'WebPushDelivery'=>'web_push_deliveries', 'WebPushSubscription'=>'web_push_subscriptions',
 'TrustedHousehold'=>'trusted_households', 'DispatchStatus'=>'dispatch_statuses',
];
$tableClasses = $models;
foreach (glob(dirname(__DIR__).'/app/Models/*.php') as $file) {
 $class=basename($file,'.php'); $model = new ('App\\Models\\'.$class);
 $tableClasses[$class] = $model->getTable();
}
$tableClasses=array_flip($tableClasses);
$relations = App\Services\Shared\ResqDatabaseIntegrity::RELATIONSHIPS;
$relations = array_merge($relations, [
 ['dispatch_trips','dispatch_id','responder_assignments','assignment_id'], ['dispatch_trips','team_id','rescue_teams','team_id'], ['dispatch_trips','device_id','device_tokens','id'],
 ['trip_points','trip_id','dispatch_trips','id'], ['sync_events','device_id','device_tokens','id'],
 ['member_status_logs','member_id','household_members','member_id'], ['member_status_logs','household_id','households','household_id'], ['member_status_logs','disaster_id','disaster_events','event_id'],
 ['member_status_logs','from_status_id','member_statuses','status_id'], ['member_status_logs','to_status_id','member_statuses','status_id'],
 ['responder_check_ins','responder_id','responders','responder_id'], ['responder_check_ins','household_id','households','household_id'], ['responder_check_ins','member_id','household_members','member_id'],
 ['responder_communication_logs','responder_id','responders','responder_id'], ['responder_communication_logs','team_id','rescue_teams','team_id'],
 ['hq_field_reports','linked_report_id','responder_field_reports','report_id'], ['hq_field_reports','household_id','households','household_id'],
 ['incident_archives','disaster_id','disaster_events','event_id'], ['user_notifications','user_id','users','user_id'],
 ['web_push_subscriptions','user_id','users','user_id'], ['web_push_deliveries','subscription_id','web_push_subscriptions','id'],
]);
foreach ($models as $class=>$table) {
 $path=dirname(__DIR__).'/app/Models/'.$class.'.php';
 if (file_exists($path) || !isset($schema[$table])) continue;
 $ddl=$schema[$table];
 preg_match_all('/^  `([^`]+)` ([^\n]+)/m',$ddl,$matches,PREG_SET_ORDER);
 $fields=[]; $casts=[]; $hidden=[]; $incrementing=false;
 foreach($matches as $m) {
   $fields[]=$m[1]; $type=$m[2];
   if (str_contains($type,'AUTO_INCREMENT')) $incrementing=true;
   if (preg_match('/^(datetime|timestamp)/',$type)) $casts[$m[1]]='datetime';
   elseif (str_starts_with($type,'json')) $casts[$m[1]]='array';
   elseif (str_starts_with($type,'tinyint(1)')) $casts[$m[1]]='boolean';
   if (preg_match('/token_hash|code_hash|pin_hash|endpoint|auth_token|p256dh|auth_key/',$m[1])) $hidden[]=$m[1];
 }
 preg_match('/PRIMARY KEY \(`([^`]+)`\)/',$ddl,$pkMatch);
 $pk=$pkMatch[1] ?? App\Services\Shared\ResqDatabaseIntegrity::PRIMARY_KEYS[$table] ?? null;
 if (!$pk) throw new RuntimeException('No key for '.$table);
 preg_match('/`'.preg_quote($pk,'/').'` (\w+)/',$ddl,$keyType);
 $string=in_array($keyType[1],['char','varchar'],true);
 $body="<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Model;\nuse Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;\n\nclass $class extends Model\n{\n    protected \$table = '$table';\n    protected \$primaryKey = '$pk';\n";
 if (!$incrementing) $body.="    public \$incrementing = false;\n";
 if ($string) $body.="    protected \$keyType = 'string';\n";
 if (!(in_array('created_at',$fields,true) && in_array('updated_at',$fields,true))) $body.="    public \$timestamps = false;\n";
 $body.='    protected $fillable = '.var_export($fields,true).";\n";
 if ($hidden) $body.='    protected $hidden = '.var_export($hidden,true).";\n";
 if ($casts) $body.="\n    protected function casts(): array\n    {\n        return ".var_export($casts,true).";\n    }\n";
 foreach ($relations as [$child,$key,$parent,$parentKey]) {
   if ($child!==$table || !isset($tableClasses[$parent]) || !in_array($key,$fields,true)) continue;
   $name=Illuminate\Support\Str::camel(preg_replace('/_id$/','',$key));
   $parentClass=$tableClasses[$parent];
   $body.="\n    public function $name(): BelongsTo\n    {\n        return \$this->belongsTo($parentClass::class, '$key', '$parentKey');\n    }\n";
 }
 $body.="}\n";
 file_put_contents($path,$body);
 echo $class.PHP_EOL;
}
