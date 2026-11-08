<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Support\RequestSchema as Schema;
use RuntimeException;
use Throwable;

class PrivatizeHouseholdProfilePhotos extends Command
{
    protected $signature = 'household-profile-photos:privatize {--dry-run : Report required changes without moving or deleting files}';
    protected $description = 'Move referenced household profile photos to private storage and remove public copies';

    public function handle(): int
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'profile_photo')) {
            $this->error('The users.profile_photo column is unavailable.');
            return self::FAILURE;
        }

        $local = Storage::disk('local');
        $public = Storage::disk('public');
        $references = DB::table('users')->whereNotNull('profile_photo')->where('profile_photo', '<>', '')
            ->pluck('profile_photo')->filter(fn ($path): bool => is_string($path) && $this->isManagedPath($path))
            ->unique()->values();
        $moved = 0;
        $removed = 0;
        $errors = 0;
        $dryRun = (bool) $this->option('dry-run');

        foreach ($references as $path) {
            try {
                if (! $local->exists($path) && ! $public->exists($path)) {
                    throw new RuntimeException('No public or private photo file exists for a referenced profile.');
                }
                if (! $local->exists($path) && $public->exists($path)) {
                    if ($dryRun) {
                        $moved++;
                    } else {
                        $source = $public->readStream($path);
                        if (! is_resource($source)) throw new RuntimeException('Could not read the public photo.');
                        try {
                            $copied = $local->writeStream($path, $source);
                        } finally {
                            fclose($source);
                        }
                        if (! $copied || ! $local->exists($path)) throw new RuntimeException('Could not verify the private photo copy.');
                        $moved++;
                    }
                }

                if ($public->exists($path)) {
                    if (! $dryRun && ! $public->delete($path)) throw new RuntimeException('Could not remove the public photo.');
                    $removed++;
                }
                if (! $local->exists($path) && ! $dryRun) {
                    throw new RuntimeException('No public or private photo file exists for a referenced profile.');
                }
            } catch (Throwable $error) {
                $errors++;
                $this->error($path.': '.$error->getMessage());
            }
        }

        foreach ($public->allFiles('household-profile-photos') as $path) {
            if (! $this->isManagedPath($path) || $references->contains($path)) continue;
            if ($dryRun) {
                $removed++;
                continue;
            }
            if ($public->delete($path)) $removed++;
            else {
                $errors++;
                $this->error($path.': could not remove an unreferenced public photo.');
            }
        }

        $this->info(($dryRun ? 'Dry run: ' : '')."{$moved} photos to privatize; {$removed} public copies to remove; {$errors} errors.");
        return $errors ? self::FAILURE : self::SUCCESS;
    }

    private function isManagedPath(string $path): bool
    {
        return preg_match('~^household-profile-photos/[a-f0-9]{64}/[a-f0-9]{64}/[a-f0-9-]{36}\.(jpg|png|webp)$~i', $path) === 1;
    }
}
