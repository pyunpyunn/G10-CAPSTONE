<?php

namespace App\Actions;

use App\Services\Mobile\RescuerAccountSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NormalizeResponderCodes
{
    public function __construct(private RescuerAccountSupport $support) {}

    public function run(bool $apply = false): array
    {
        return DB::transaction(function () use ($apply) {
            $rows = DB::table('responders as p')->leftJoin('rescue_teams as t','t.team_id','=','p.team_id')
                ->leftJoin('users as u','u.user_id','=','p.user_id')->leftJoin('roles as r','r.role_id','=','u.role_id')
                ->orderBy('p.responder_id')->lockForUpdate()
                ->get(['p.responder_id','p.responder_code','p.username','p.full_name','p.user_id','u.username as login',
                    't.team_code','r.role_key','r.base_role_key']);
            $field = $rows->filter(fn($row) => $row->team_code && strtoupper($row->team_code) !== 'HQCC'
                && (in_array($row->role_key,['rescuer','responder','tl','tm'],true) || $row->base_role_key === 'rescuer'));
            $next = $this->support->nextAccountIds($field->map(fn($row) => $this->support->normalizeTeamCode($row->team_code))->unique()->all());
            $logins = DB::table('users')->pluck('username');
            if (Schema::hasColumn('users','login_id')) $logins = $logins->merge(DB::table('users')->pluck('login_id'));
            $reserved = $logins->merge($rows->pluck('username'))->merge($rows->pluck('responder_code'))
                ->filter()->mapWithKeys(fn($id)=>[strtoupper($id)=>true])->all();
            $seen = []; $changes = [];
            foreach ($field as $row) {
                $team = $this->support->normalizeTeamCode($row->team_code);
                $old = (string)$row->responder_code;
                if ($this->support->accountIdMatchesTeam($old,$team) && !isset($seen[strtoupper($old)])) {
                    $seen[strtoupper($old)] = true;
                    continue;
                }
                $sequence = $this->support->accountSequence($next[$team],$team);
                do { $code = $team.'-'.str_pad((string)$sequence++,4,'0',STR_PAD_LEFT); } while(isset($reserved[$code]));
                $next[$team] = $team.'-'.str_pad((string)$sequence,4,'0',STR_PAD_LEFT);
                $reserved[$code] = true; $seen[$code] = true;
                $change = ['responder_id'=>$row->responder_id,'name'=>$row->full_name,'team'=>$team,
                    'old_code'=>$old,'new_code'=>$code,'login'=>$row->login,'legacy_login'=>$row->username];
                $changes[] = $change;
                if ($apply) {
                    DB::table('responders')->where('responder_id',$row->responder_id)->update(['responder_code'=>$code,'updated_at'=>now()]);
                    if (Schema::hasTable('audit_logs')) DB::table('audit_logs')->insert([
                        'module'=>'personnel_access','action'=>'normalize_responder_identifier','reference_table'=>'responders',
                        'reference_id'=>(string)$row->responder_id,'old_values'=>json_encode(['responder_code'=>$old]),
                        'new_values'=>json_encode(['responder_code'=>$code]),'created_at'=>now(),
                    ]);
                }
            }
            return $changes;
        });
    }
}
