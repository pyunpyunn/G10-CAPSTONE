<?php

namespace App\Data;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class InquiryListResult
{
    public function __construct(
        public readonly ?LengthAwarePaginator $inquiries,
        public readonly Collection $statusCounts,
        public readonly Collection $accounts,
        public readonly bool $tableReady,
    ) {}
}
