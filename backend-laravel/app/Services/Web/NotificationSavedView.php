<?php

namespace App\Services\Web;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;

class NotificationSavedView
{
    public function state(?iterable $rows, ?array $visibleIds = null): array
    {
        if ($rows === null) return ['empty'=>true,'read_ids'=>[],'hidden_ids'=>[],'mark_all_read_time'=>null,'clear_all_time'=>null];
        $visible = $visibleIds === null ? null : array_fill_keys($visibleIds, true);
        $keep = static fn (array $ids): array => $visible === null ? $ids : array_values(array_filter($ids, static fn (string $id): bool => isset($visible[$id])));
        $readIds=[]; $hiddenIds=[]; $markAllReadTime=null; $clearAllTime=null;
        foreach ($rows as $row) {
            $values=$this->decode($row->new_values ?? null); $time=Carbon::parse($row->created_at)->timestamp;
            if ($row->action === 'mark_read') { $ids=$this->idList($values['selected_ids'] ?? []); if (!$ids) $markAllReadTime=max($markAllReadTime ?? 0, $time); else $readIds=array_values(array_unique([...$readIds,...$keep($ids)])); }
            if ($row->action === 'delete_selected') { $ids=$this->idList($values['selected_ids'] ?? []); $hiddenIds=array_values(array_unique([...$hiddenIds,...$keep($ids)])); }
            if ($row->action === 'clear_all') { $ids=$this->idList($values['hidden_ids'] ?? []); $hiddenIds=array_values(array_unique([...$hiddenIds,...$keep($ids)])); if (!$ids) $clearAllTime=max($clearAllTime ?? 0, $time); }
        }
        return ['empty'=>false,'read_ids'=>$readIds,'hidden_ids'=>$hiddenIds,'mark_all_read_time'=>$markAllReadTime,'clear_all_time'=>$clearAllTime];
    }

    public function apply(Collection $items, array $state): Collection
    {
        if ($state['empty']) return $items;
        return $items->filter(function(array $item) use ($state): bool {
            if ($state['clear_all_time'] && $item['sort_time'] <= $state['clear_all_time']) return false;
            return !in_array($item['id'],$state['hidden_ids'],true);
        })->map(function(array $item) use ($state): array {
            if ($state['mark_all_read_time'] && $item['sort_time'] <= $state['mark_all_read_time']) $item['read']=true;
            if (in_array($item['id'],$state['read_ids'],true)) $item['read']=true;
            return $item;
        })->values();
    }

    public function filter(Collection $items,string $status): Collection
    {
        if ($status==='unread') return $items->where('read',false)->values();
        if ($status==='read') return $items->where('read',true)->values();
        return $items;
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || $value==='') return [];
        $decoded=json_decode($value,true);
        return is_array($decoded)?$decoded:[];
    }

    private function idList(mixed $value): array
    {
        if (!is_array($value)) return [];
        return collect($value)->filter(fn(mixed $item):bool=>is_string($item)&&trim($item)!=='')->values()->all();
    }
}







