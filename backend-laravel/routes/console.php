<?php

use App\Services\Shared\WeatherSnapshotService;
use App\Services\Shared\StatusReminderScheduler;
use App\Queries\RescueCriteriaHistoryQuery;
use App\Queries\RescueDispatchQuery;
use App\Support\QueryProfileRanking;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('weather:refresh', function () {
    $weather = app(WeatherSnapshotService::class);

    try {
        $result = $weather->saveLatestSnapshot(null);
    } catch (\Throwable $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    $this->info($result['message']);

    return 0;
})->purpose('Fetch Open-Meteo weather data and save it to weather_logs');

Artisan::command('status-reminders:schedule', function (StatusReminderScheduler $scheduler) {
    $this->info('Scheduled '.$scheduler->scheduleDue().' member status check-ins.');
})->purpose('Queue check-ins for unreported members of the active disaster event');

Artisan::command('rescue-criteria:capture {--rebase-contact : Recalculate existing contact baselines after a definition change}', function (RescueDispatchQuery $dispatch, RescueCriteriaHistoryQuery $criteria) {
    $event = $dispatch->activeEvent();
    if (! $event) {
        $this->info('No active event to measure.');
        return;
    }
    $count = $criteria->refresh((string) $event->event_id, $event->started_at, null, (bool) $this->option('rebase-contact'));
    $this->info('Updated '.$count.' three-hour rescue criteria buckets for '.$event->event_id.'.');
})->purpose('Store a measured rescue criteria point for the active event');

Artisan::command('profiles:rank {--path= : Laravel log path}', function (QueryProfileRanking $ranking) {
    $path = $this->option('path') ?: storage_path('logs/laravel.log');
    $rows = $ranking->fromLog($path);
    if ($rows === []) {
        $this->warn('No local API query profiles found. Enable LOCAL_QUERY_PROFILE and exercise the affected pages first.');
        return 0;
    }
    $this->table(['Route', 'Requests', 'Avg response ms', 'Avg DB ms', 'Avg queries'],
        array_map(fn (array $row): array => array_values($row), $rows));
    return 0;
})->purpose('Rank the five slowest locally profiled API routes');

Artisan::command('households:provision-logins {--password=marshmallows : Temporary password to hash for household accounts}', function () {
    if (! Schema::hasTable('households') || ! Schema::hasTable('users') || ! Schema::hasTable('roles')) {
        $this->error('Required tables are missing: households, users, or roles.');

        return 1;
    }

    $roleId = DB::table('roles')->where('role_key', 'household_resident')->value('role_id');

    if (! $roleId) {
        $this->error('The household_resident role was not found.');

        return 1;
    }

    $userColumns = array_flip(Schema::getColumnListing('users'));
    $passwordHash = Hash::make((string) $this->option('password'));
    $now = now();
    $created = 0;
    $updated = 0;

    DB::table('households')
        ->when(Schema::hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
        ->orderBy('household_id')
        ->each(function (object $household) use (&$created, &$updated, $userColumns, $roleId, $passwordHash, $now): void {
            $users = DB::table('users')
                ->where('household_id', $household->household_id)
                ->when(isset($userColumns['deleted_at']), fn ($query) => $query->whereNull('deleted_at'))
                ->get();

            $login = $household->household_code
                ?: ($household->household_number ?: $household->household_id);

            $values = [
                'user_id' => 'USR-HH-'.Str::upper(Str::slug((string) $household->household_id, '-')),
                'first_name' => 'Household',
                'last_name' => $household->household_name ?: 'Resident',
                'name' => $household->household_name ?: 'Household Resident',
                'username' => $login,
                'email' => $household->email,
                'password' => $passwordHash,
                'role_id' => $roleId,
                'contact_number' => $household->contact_number,
                'household_id' => $household->household_id,
                'is_active' => 1,
                'must_change_password' => 1,
                'temp_password' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $values = array_intersect_key($values, $userColumns);
            $updateValues = array_intersect_key([
                'password' => $passwordHash,
                'role_id' => $roleId,
                'is_active' => 1,
                'must_change_password' => 1,
                'temp_password' => null,
                'updated_at' => $now,
            ], $userColumns);

            if ($users->isNotEmpty()) {
                foreach ($users as $user) {
                    DB::table('users')->where('user_id', $user->user_id)->update($updateValues);
                    $updated++;
                }
            } else {
                DB::table('users')->insert($values);
                $created++;
            }
        });

    $this->info("Household logins provisioned. Created: $created, updated: $updated.");
    $this->warn('The temporary password was hashed and is not stored as plaintext. Require each household to change it after first login.');

    return 0;
})->purpose('Create or reset linked household user accounts with a temporary hashed password');

Schedule::command('weather:refresh')
    ->everyThreeHours()
    ->withoutOverlapping();

Schedule::command('status-reminders:schedule')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('rescue-criteria:capture')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('queue:work trackingaid_outbox --queue=trackingaid --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('queue:work operations_outbox --queue=broadcasts,operations --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();




Schedule::command('queue:work reports --queue=reports --stop-when-empty --max-time=50 --timeout=600')
    ->everyMinute()->withoutOverlapping();

Schedule::call(function () {
    $disk = \Illuminate\Support\Facades\Storage::disk('local');
    foreach (['reports', 'report-jobs'] as $directory) {
        foreach ($disk->files($directory) as $file) {
            if ($disk->lastModified($file) < now()->subDay()->timestamp) $disk->delete($file);
        }
    }
})->hourly()->name('reports:cleanup')->withoutOverlapping();
