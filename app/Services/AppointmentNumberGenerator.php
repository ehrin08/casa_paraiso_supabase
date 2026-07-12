<?php

namespace App\Services;

use Illuminate\Support\Str;

class AppointmentNumberGenerator
{
    public function next(): string
    {
        return 'APT-'.now()->format('Ymd').'-'.strtoupper((string) Str::ulid());
    }
}
