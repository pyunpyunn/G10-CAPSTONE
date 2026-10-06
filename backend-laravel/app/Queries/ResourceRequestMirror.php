<?php

namespace App\Queries;

use App\Models\ResourceRequest;

class ResourceRequestMirror
{
    private const CATALOG = [
        ['label' => 'Food packs', 'keywords' => ['food', 'pack', 'meal', 'rice']],
        ['label' => 'Water containers', 'keywords' => ['water', 'container']],
        ['label' => 'Medical kits', 'keywords' => ['medical', 'medic', 'first aid', 'kit']],
        ['label' => 'Life jackets', 'keywords' => ['life jacket', 'jacket', 'rescue equipment']],
        ['label' => 'Evacuation tents', 'keywords' => ['tent', 'shelter', 'camp']],
        ['label' => 'Rescue vehicle', 'keywords' => ['vehicle', 'transport', 'truck', 'ambulance']],
        ['label' => 'Medical responders', 'keywords' => ['medical responder', 'responder', 'personnel', 'medic personnel']],
        ['label' => 'Transport volunteers', 'keywords' => ['volunteer', 'driver', 'transport']],
    ];

    public function summarize(): array
    {
        $totals = array_fill(0, count(self::CATALOG), ['count' => 0, 'quantity' => 0, 'unit' => 'unit(s)']);

        foreach (ResourceRequest::query()
            ->whereNotIn('validation_status', ['returned', 'cancelled', 'fulfilled'])
            ->lazyById(500, 'request_id') as $request) {
            $text = strtolower(trim(($request->resource_type ?? '').' '.($request->item_name ?? '').' '.($request->description ?? '')));

            foreach (self::CATALOG as $index => $item) {
                foreach ($item['keywords'] as $keyword) {
                    if (! str_contains($text, $keyword)) {
                        continue;
                    }

                    if ($totals[$index]['count'] === 0) {
                        $totals[$index]['unit'] = $request->unit ?: 'unit(s)';
                    }
                    $totals[$index]['count']++;
                    $totals[$index]['quantity'] += (int) ($request->quantity ?? 0);
                    break;
                }
            }
        }

        return array_map(function (array $item, array $total): array {
            $count = $total['count'];

            return [
                'label' => $item['label'],
                'source' => 'TrackingAid mirror',
                'status' => $count ? "$count open request(s)" : 'No open request',
                'detail' => $count
                    ? $total['quantity'].' '.$total['unit'].' requested in RESQPERATION'
                    : 'Availability must be confirmed in TrackingAid',
            ];
        }, self::CATALOG, $totals);
    }
}
