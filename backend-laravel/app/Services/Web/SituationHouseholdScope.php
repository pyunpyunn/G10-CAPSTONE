<?php

namespace App\Services\Web;

use App\Queries\SituationReportQuery;
use Illuminate\Validation\ValidationException;

class SituationHouseholdScope
{
    public function apply(array $summary, array $data): array
    {
        $hasDetails = array_key_exists('details', $summary['household'] ?? []);
        $hasSelection = isset($data['sitio_id']) || isset($data['purok_id']) || isset($summary['household_scope']);
        if (!$hasDetails && !$hasSelection && !array_key_exists('include_households', $data)) return $summary;
        $data = array_replace($summary['household_scope'] ?? [], ['include_households' => $summary['include_households'] ?? true], $data);
        $sitioId = ($data['sitio_id'] ?? 'all') === 'all' ? null : $data['sitio_id'];
        $purokId = ($data['purok_id'] ?? 'all') === 'all' ? null : $data['purok_id'];
        if (!$hasDetails && ($sitioId !== null || $purokId !== null)) {
            throw ValidationException::withMessages(['sitio_id' => 'This saved report predates household details. Create a new report to export household lists by location.']);
        }
        $label = 'All sitios · all puroks';
        if ($purokId !== null && $sitioId === null) throw ValidationException::withMessages(['purok_id' => 'Select a sitio before selecting a purok.']);
        if ($sitioId !== null) {
            $sitio = app(SituationReportQuery::class)->sitioOptions()->first(fn ($s) => (string) $s->sitio_id === (string) $sitioId);
            if (!$sitio) throw ValidationException::withMessages(['sitio_id' => 'Select a registered sitio in this barangay.']);
            $label = $sitio->sitio_name.' · all puroks';
            if ($purokId !== null) {
                $purok = collect($sitio->puroks)->first(fn ($p) => (string) $p->purok_id === (string) $purokId);
                if (!$purok) throw ValidationException::withMessages(['purok_id' => 'Select a purok belonging to the selected sitio.']);
                $label = $sitio->sitio_name.' · '.$purok->purok_name;
            }
        }
        $summary['household_scope'] = ['sitio_id' => $sitioId, 'purok_id' => $purokId, 'label' => $label];
        $summary['include_households'] = (bool) ($data['include_households'] ?? true);
        $summary['household']['details'] = array_values(array_filter($summary['household']['details'] ?? [], fn ($h) =>
            ($sitioId === null || (string) $h['sitio_id'] === (string) $sitioId) && ($purokId === null || (string) $h['purok_id'] === (string) $purokId)));
        return $summary;
    }
}
