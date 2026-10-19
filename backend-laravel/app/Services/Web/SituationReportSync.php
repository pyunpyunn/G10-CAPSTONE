<?php

namespace App\Services\Web;

use App\Services\Shared\SituationExternalSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Http\Exceptions\HttpResponseException;

class SituationReportSync
{
    public function run(string $eventId): array
    {
        $lock=Cache::lock('sitrep-sync:'.hash('sha256',$eventId),240);
        if (!$lock->get()) throw new HttpResponseException(response()->json(['message'=>'Reports are already syncing for this event. Try again shortly.'],409));
        try {
            $payload=[]; $sources=[];
            foreach (['safetrack'=>'SafeTrack','evatrack'=>'EvaTrack','trackingaid'=>'TrackingAid'] as $key=>$name) {
                try {
                    $payload[$key]=app(SituationExternalSource::class)->fetch($key,$eventId);
                    $count=array_sum(array_map('count',$payload[$key]));
                    $sources[$key]=['name'=>$name,'status'=>'ready','records'=>$count,'checked_at'=>now()->toIso8601String()];
                } catch (\Throwable $e) {
                    // No remote body, configured address or credentials are exposed.
                    $message='Could not sync the latest report. Check the connection, credentials and report columns.';
                    if (str_contains($e->getMessage(),'not configured')) $message='Connection is not configured.';
                    if (str_contains($e->getMessage(),'CSV needs')) $message='The report CSV does not provide the columns needed for SitRep sync.';
                    $sources[$key]=['name'=>$name,'status'=>'failed','message'=>$message,'records'=>0,'checked_at'=>now()->toIso8601String()];
                }
            }
            $id=(string)Str::uuid();
            $result=['sync_id'=>$id,'event_id'=>$eventId,'checked_at'=>now()->toIso8601String(),
                'ready'=>count(array_filter($sources,fn ($s)=>$s['status']==='ready'))===3,'sources'=>$sources,'payload'=>$payload];
            Cache::put('sitrep-sync:'.$id,Crypt::encryptString(json_encode($result,JSON_THROW_ON_ERROR)),now()->addMinutes(5));
            return $result;
        } finally { $lock->release(); }
    }

    public function requireReady(string $eventId, ?string $syncId = null): ?array
    {
        if (!config('situation_sync.enabled',true)) return null;
        $result=null;
        if ($syncId && Str::isUuid($syncId) && ($encrypted=Cache::get('sitrep-sync:'.$syncId))) {
            try { $cached=json_decode(Crypt::decryptString($encrypted),true,512,JSON_THROW_ON_ERROR); }
            catch (\Throwable) { $cached=null; }
            if ($cached && $cached['event_id']===$eventId && \Carbon\Carbon::parse($cached['checked_at'])->diffInSeconds(now(),true)<=config('situation_sync.fresh_seconds',60)) $result=$cached;
        }
        $result ??= $this->run($eventId);
        if (!$result['ready']) throw new HttpResponseException(response()->json([
            'message'=>'Sync all three external reports successfully before generating a new SitRep.',
            'errors'=>['external_sync'=>['Latest external data is unavailable. Retry the failed sources.']],
            'data'=>['sync'=>$this->status($result)],
        ],409));
        return $result;
    }

    public function status(array $result): array { return array_diff_key($result,['payload'=>true]); }

    public function apply(array $summary, ?array $sync): array
    {
        if (!$sync) return $summary;
        $data=$sync['payload'];
        $sitio=$summary['event']['sitio_id'] ?? null;
        $inScope=fn ($row)=>$sitio===null || (string)($row['sitio_id'] ?? '')===(string)$sitio;
        if (isset($data['safetrack'])) $summary=$this->census($summary,$data['safetrack']);
        if (isset($data['evatrack'])) {
            $summary['evacuation']['sites']=array_values(array_filter($data['evatrack']['sites'],$inScope));
        }
        if (isset($data['trackingaid'])) {
            $summary['inventory']['rows']=array_values(array_filter($data['trackingaid']['inventory'],$inScope));
            $summary['resources']['rows']=array_values(array_filter($data['trackingaid']['requests'],$inScope));
        }
        $summary['population_tables']=app(\App\Presenters\SituationReportPopulation::class)->data($summary);
        $summary['report']['period_end']=\Carbon\Carbon::parse($sync['checked_at'])->format('M d, Y g:i A');
        $summary['external_sync']=$this->status($sync);
        return $summary;
    }

    private function census(array $s, array $data): array
    {
        $households=[]; $members=[];
        foreach ($data['households'] as $h) if (isset($h['household_id'])) $households[(string)$h['household_id']]=$h;
        foreach ($data['members'] as $m) if (isset($m['member_id'])) $members[(string)$m['member_id']]=$m;
        $enrich=function ($m) use ($members) {
            $remote=$members[(string)($m['member_id'] ?? '')] ?? null;
            if (!$remote) return array_replace($m,array_fill_keys(['age','sex','is_pwd','is_pregnant','is_lactating'],null));
            $age=$remote['age'] ?? null;
            if ($age===null && !empty($remote['birth_date'])) {
                try { $age=(int)\Carbon\Carbon::parse($remote['birth_date'])->age; } catch (\Throwable) {}
            }
            $flag=fn ($key)=>isset($remote[$key]) ? filter_var($remote[$key],FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE) : null;
            return array_replace($m,['name'=>$remote['full_name'] ?? trim(implode(' ',array_filter([$remote['first_name'] ?? '',$remote['last_name'] ?? '']))) ?: ($m['name'] ?? null),
                'age'=>$age,'sex'=>$remote['gender'] ?? $remote['sex'] ?? null,'is_pwd'=>$flag('is_pwd'),
                'is_pregnant'=>$flag('is_pregnant'),'is_lactating'=>$flag('is_lactating')]);
        };
        foreach ($s['household']['details'] ?? [] as $i=>$h) {
            $remote=$households[(string)$h['household_id']] ?? [];
            $s['household']['details'][$i]['registered_members']=$remote['member_count'] ?? null;
            $s['household']['details'][$i]['members']=array_map($enrich,$h['members'] ?? []);
        }
        $s['evacuation']['members']=array_map($enrich,$s['evacuation']['members'] ?? []);
        $affected=[];
        foreach ([...($s['household']['details'] ?? []),...($s['evacuation']['households'] ?? [])] as $h) $affected[(string)$h['household_id']]=$h;
        foreach ($s['household']['puroks'] ?? [] as $i=>$p) {
            $sameArea=fn ($h)=>isset($h['purok_id'],$p['purok_id']) ? (string)$h['purok_id']===(string)$p['purok_id']
                : ($h['sitio'] ?? null)===($p['sitio'] ?? null) && ($h['purok'] ?? null)===($p['purok'] ?? null);
            $local=array_filter($affected,$sameArea);
            $counts=array_map(fn ($h)=>$households[(string)$h['household_id']]['member_count'] ?? null,$local);
            $s['household']['puroks'][$i]['affected_persons']=count($local)===($p['affected'] ?? null) && !in_array(null,$counts,true) ? array_sum($counts) : null;
            // Keep local event family counts. A census is never treated as an event impact assessment.
        }
        return $s;
    }
}
