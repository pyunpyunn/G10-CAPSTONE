<?php

namespace App\Services\Web;

use App\Events\PersonnelAccessChanged;
use Illuminate\Support\Facades\DB;
use Throwable;

class PersonnelAccessNotifier
{
    public function notify(array $userIds): void
    {
        foreach (array_unique(array_filter($userIds)) as $userId) {
            DB::afterCommit(function () use ($userId): void {
                try { event(new PersonnelAccessChanged((string) $userId)); }
                catch (Throwable $error) { report($error); } // Session polling remains available when broadcasting is offline.
            });
        }
    }
}
