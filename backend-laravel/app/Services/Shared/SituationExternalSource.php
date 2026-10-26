<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\Http;

/** Reuses existing report APIs; never invents new remote endpoints. */
class SituationExternalSource
{
    public function fetch(string $source, string $eventId): array
    {
        if ($source==='safetrack') return app(SafeTrackReportClient::class)->snapshot();
        $client=$source==='evatrack' ? app(EvaTrackReportClient::class) : app(TrackingAidReportClient::class);
        $result=$client->generate(['report_type'=>config('situation_sync.'.$source.'_report_type','general'),'format'=>'csv']);
        if ($result->getStatusCode()!==200) throw new \RuntimeException('Report is unavailable or awaiting upstream review.');
        $report=$result->getData(true); $url=$report['download_url'];
        $base=$source==='evatrack' ? config('evatrack.reports_url') : config('trackingaid_reports.reports_url');
        $origin=fn ($v)=>[strtolower((string)parse_url($v,PHP_URL_SCHEME)),strtolower((string)parse_url($v,PHP_URL_HOST)),parse_url($v,PHP_URL_PORT) ?: (parse_url($v,PHP_URL_SCHEME)==='https' ? 443 : 80)];
        if ($origin($url)!==$origin($base) || parse_url($url,PHP_URL_USER) || parse_url($url,PHP_URL_PASS)) throw new \RuntimeException('Report download is outside the configured server.');
        if (\Carbon\Carbon::parse($report['expires_at'])->isPast()) throw new \RuntimeException('Report download has expired.');
        // Signed download URL only; never forward API credentials to a download.
        $response=Http::timeout(30)->withoutRedirecting()->withOptions(['stream'=>true])->get($url);
        if (!$response->successful()) throw new \RuntimeException('Report download failed.');
        $stream=$response->toPsrResponse()->getBody(); $body='';
        while (!$stream->eof()) {
            $body.=$stream->read(8192);
            if (strlen($body)>5*1024*1024) throw new \RuntimeException('Report exceeds the 5 MB sync limit.');
        }
        $file=fopen('php://temp','r+');
        try {
            fwrite($file,$body); rewind($file);
            $headers=fgetcsv($file,0,',','"','');
            if (!$headers) throw new \RuntimeException('Report CSV is empty.');
            $headers=array_map(fn ($h)=>trim(preg_replace('/[^a-z0-9]+/','_',strtolower(preg_replace('/^\xEF\xBB\xBF/','',(string)$h))), '_'),$headers);
            if (count($headers)!==count(array_unique($headers))) throw new \RuntimeException('Report CSV has duplicate column names.');
            $rows=[];
            while (($cells=fgetcsv($file,0,',','"',''))!==false) {
                if ($cells===[null]) continue;
                if (count($cells)!==count($headers)) throw new \RuntimeException('Report CSV has inconsistent rows.');
                $row=array_combine($headers,$cells);
                if (!empty($row['event_id']) && (string)$row['event_id']!==$eventId) continue;
                $rows[]=$row;
                if (count($rows)>10000) throw new \RuntimeException('Report CSV exceeds the sync row limit.');
            }
        } finally { fclose($file); }
        if ($source==='evatrack') {
            if (!array_intersect(['center_id','evacuation_center_id','site_id'],$headers)) throw new \RuntimeException('CSV needs center_id and center status columns for SitRep sync.');
            $sites=[];
            foreach ($rows as $r) {
                $id=$r['center_id'] ?? $r['evacuation_center_id'] ?? $r['site_id'];
                if ($id==='') throw new \RuntimeException('An evacuation center is missing its ID.');
                $capacity=$this->number($r['capacity'] ?? null); $occupancy=$this->number($r['occupancy'] ?? $r['current_occupancy'] ?? null);
                $sites[(string)$id]=['site_id'=>(string)$id,'name'=>$r['center_name'] ?? $r['name'] ?? null,'status'=>$r['status'] ?? null,
                    'capacity'=>$capacity,'occupancy'=>$occupancy,'vacancy'=>$capacity!==null && $occupancy!==null ? max(0,$capacity-$occupancy) : null,
                    'assigned_members'=>null,'sitio_id'=>$r['sitio_id'] ?? null,'event_id'=>$r['event_id'] ?? null];
            }
            return ['sites'=>array_values($sites)];
        }
        if (!in_array('record_type',$headers,true) || !array_intersect(['item','item_name','resource_name'],$headers)) throw new \RuntimeException('CSV needs record_type (resource/request), item and quantity columns for SitRep sync.');
        $inventory=[]; $requests=[];
        foreach ($rows as $r) {
            $row=['item'=>$r['item'] ?? $r['item_name'] ?? $r['resource_name'],'quantity'=>$this->number($r['quantity'] ?? null),
                'sitio_id'=>$r['sitio_id'] ?? null,'unit'=>$r['unit'] ?? null,'status'=>$r['status'] ?? null,'source'=>'TrackingAid'];
            if ($r['record_type']==='resource') $inventory[]=$row;
            elseif ($r['record_type']==='request') {
                if (empty($r['event_id'])) throw new \RuntimeException('Resource request rows must include event_id.');
                $requests[]=$row;
            } else throw new \RuntimeException('Unrecognized TrackingAid record_type.');
        }
        return ['inventory'=>$inventory,'requests'=>$requests];
    }

    private function number(mixed $value): int|float|null
    {
        if ($value===null || in_array(strtolower(trim((string)$value)),['','not recorded','n/a','-'],true)) return null;
        if (!is_numeric($value) || (float)$value<0) throw new \RuntimeException('Report has an invalid numeric count.');
        return $value+0;
    }
}
