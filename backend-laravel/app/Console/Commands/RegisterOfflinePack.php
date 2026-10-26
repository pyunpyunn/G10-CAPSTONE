<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegisterOfflinePack extends Command
{
    protected $signature = 'navigation:register-pack {version? : Pack version, defaults to NAVIGATION_PACK_VERSION}';
    protected $description = 'Verify and register a built offline map pack as the active version';

    public function handle(): int
    {
        $version = (string) ($this->argument('version') ?: config('navigation.pack_version', 'v1'));
        if (preg_match('/^[A-Za-z0-9._-]{1,64}$/', $version) !== 1) {
            $this->error('Pack version contains unsupported characters.');

            return self::FAILURE;
        }

        $root = rtrim((string) config('navigation.pack_root'), DIRECTORY_SEPARATOR);
        $manifestPath = $root.DIRECTORY_SEPARATOR.$version.'.json';
        if (! is_file($manifestPath)) {
            $this->error('Pack manifest is missing from '.$root.'.');

            return self::FAILURE;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $format = $manifest['pack_format'] ?? 'mbtiles_zip';
        if (! in_array($format, ['maplibre_db', 'mbtiles_zip'], true)) {
            $this->error('Unsupported pack_format.');
            return self::FAILURE;
        }
        $packPath = $root.DIRECTORY_SEPARATOR.$version.($format === 'maplibre_db' ? '.db' : '.zip');
        if (! is_file($packPath)) {
            $this->error('Pack artifact is missing.');
            return self::FAILURE;
        }
        $checksum = hash_file('sha256', $packPath);
        if (! is_array($manifest)
            || ($manifest['version'] ?? null) !== $version
            || (int) ($manifest['size_bytes'] ?? 0) !== filesize($packPath)
            || ! is_string($checksum)
            || ! hash_equals(strtolower((string) ($manifest['checksum'] ?? '')), strtolower($checksum))) {
            $this->error('The offline pack checksum or manifest version is invalid.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($manifest, $version, $format): void {
            DB::table('offline_packs')->where('is_active', true)->update(['is_active' => false, 'updated_at' => now()]);
            DB::table('offline_packs')->updateOrInsert(
                ['version' => $version],
                [
                    'bbox' => json_encode($manifest['bbox'], JSON_THROW_ON_ERROR),
                    'pack_format' => $format,
                    'style_url' => $manifest['style_url'],
                    'size_bytes' => (int) $manifest['size_bytes'],
                    'checksum' => strtolower((string) $manifest['checksum']),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }, 3);

        $this->info("Registered offline pack {$version} ({$manifest['size_bytes']} bytes).");

        return self::SUCCESS;
    }
}
