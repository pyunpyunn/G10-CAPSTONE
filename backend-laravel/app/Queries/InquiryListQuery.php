<?php

namespace App\Queries;

use App\Http\Requests\ListRequest;
use App\Models\LandingInquiry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class InquiryListQuery
{
    public function statusCounts(): Collection
    {
        return LandingInquiry::query()->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')->pluck('total', 'status');
    }

    public function accountRows(): Collection
    {
        if (! Schema::hasTable('users')) return collect();
        return User::query()->select([
            'user_id', 'username', 'login_id', 'name', 'email', 'role_id',
            'is_active', 'created_at', 'updated_at',
        ])->with('role')
            ->when(Schema::hasColumn('users', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->orderByDesc('created_at')->limit(12)->get();
    }

    public function paginate(string $status, string $search, int $perPage): LengthAwarePaginator
    {
        return $this->build($status, $search)
            ->orderByDesc('created_at')
            ->orderByDesc('inquiry_id')
            ->paginate(ListRequest::clampPerPage($perPage));
    }

    private function build(string $status, string $search): Builder
    {
        return LandingInquiry::query()
            ->select([
                'inquiry_id',
                'name',
                'organization',
                'email',
                'message',
                'status',
                'source_page',
                'ip_address',
                'user_agent',
                'responded_at',
                'handled_by_user_id',
                'created_at',
                'updated_at',
            ])
            ->when($status !== '' && $status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('organization', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            });
    }
}


