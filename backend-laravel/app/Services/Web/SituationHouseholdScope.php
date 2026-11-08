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
        $availableSitios = app(SituationReportQuery::class)->sitioOptions();
        $sitio = null;
        $purok = null;
        if ($sitioId !== null) {
            $sitio = $availableSitios->first(fn ($s) => (string) $s->sitio_id === (string) $sitioId);
            if (!$sitio) throw ValidationException::withMessages(['sitio_id' => 'Select a registered sitio in this barangay.']);
            $label = $sitio->sitio_name.' · all puroks';
            if ($purokId !== null) {
                $purok = collect($sitio->puroks)->first(fn ($p) => (string) $p->purok_id === (string) $purokId);
                if (!$purok) throw ValidationException::withMessages(['purok_id' => 'Select a purok belonging to the selected sitio.']);
                $label = $sitio->sitio_name.' · '.$purok->purok_name;
            }
        } elseif ($purokId !== null) {
            $purok = $availableSitios->flatMap(fn ($areaSitio) => collect($areaSitio->puroks)
                ->filter(fn ($areaPurok) => (string) $areaPurok->purok_id === (string) $purokId))
                ->first();
            if (!$purok) throw ValidationException::withMessages(['purok_id' => 'Select a registered purok in this barangay.']);
            $label = 'All sitios · '.$purok->purok_name;
        }
        $selectedSitios = $sitio === null ? $availableSitios : collect([$sitio]);
        $areas = $selectedSitios->flatMap(fn ($areaSitio) => collect($areaSitio->puroks)
            ->filter(fn ($purok) => $purokId === null || (string) $purok->purok_id === (string) $purokId)
            ->map(fn ($purok) => [
                'sitio_id' => (string) $areaSitio->sitio_id, 'sitio' => $areaSitio->sitio_name,
                'purok_id' => (string) $purok->purok_id, 'purok' => $purok->purok_name,
            ]))->values()->all();
        if (($summary['schema_version'] ?? '2.0') === \App\Support\SituationReportSections::VERSION && !isset($summary['population_tables'])) $summary['population_tables'] = app(\App\Presenters\SituationReportPopulation::class)->data($summary);
        $summary['household_scope'] = ['sitio_id' => $sitioId, 'purok_id' => $purokId, 'label' => $label, 'areas' => $areas];
        $summary['include_households'] = (bool) ($data['include_households'] ?? true);
        $summary['household']['details'] = array_values(array_filter($summary['household']['details'] ?? [], fn ($h) =>
            ($sitioId === null || (string) $h['sitio_id'] === (string) $sitioId) && ($purokId === null || (string) $h['purok_id'] === (string) $purokId)));
        $inScope = fn ($row) => ($sitioId === null || (string) ($row['sitio_id'] ?? '') === (string) $sitioId)
            && ($purokId === null || (string) ($row['purok_id'] ?? '') === (string) $purokId);
        $summary['household']['puroks'] = array_values(array_filter($summary['household']['puroks'] ?? [], $inScope));
        if (isset($summary['population_tables']['affected'])) {
            $summary['population_tables']['affected'] = array_values(array_filter($summary['population_tables']['affected'], $inScope));
        }
        if (is_array($summary['population_roster'] ?? null)) {
            $summary['population_roster'] = array_values(array_filter($summary['population_roster'], $inScope));
        }
        if (isset($summary['broadcast_status_by_purok'])) {
            $summary['broadcast_status_by_purok'] = array_values(array_filter($summary['broadcast_status_by_purok'], $inScope));
        }
        if (isset($summary['evacuation'])) {
            foreach (['households', 'members'] as $key) {
                $summary['evacuation'][$key] = array_values(array_filter($summary['evacuation'][$key] ?? [], fn ($row) =>
                    ($sitioId === null || (string) $row['sitio_id'] === (string) $sitioId) && ($purokId === null || (string) $row['purok_id'] === (string) $purokId)));
            }
        }
        return $summary;
    }
}
