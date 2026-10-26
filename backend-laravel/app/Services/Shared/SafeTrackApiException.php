<?php

namespace App\Services\Shared;

use RuntimeException;

class SafeTrackApiException extends RuntimeException
{
    public function __construct(public readonly int $upstreamStatus)
    {
        parent::__construct('SafeTrack request failed.');
    }
}