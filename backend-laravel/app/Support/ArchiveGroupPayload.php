<?php

namespace App\Support;

class ArchiveGroupPayload
{
    public function decode(?string $note): array
    {
        $value = json_decode((string) $note, true);
        return is_array($value) ? $value : [];
    }

    public function records(?string $note): array
    {
        return collect($this->decode($note)['records'] ?? [])
            ->map(fn (mixed $record): array => is_array($record) ? $record : [])
            ->filter(fn (array $record): bool => trim((string) ($record['id'] ?? '')) !== '')
            ->values()->all();
    }

    public function encode(string $category, array $records): string
    {
        return json_encode(['category' => $category, 'records' => $records], JSON_UNESCAPED_SLASHES);
    }
}
