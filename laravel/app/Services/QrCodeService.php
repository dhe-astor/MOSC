<?php

namespace App\Services;

use Illuminate\Support\Str;

class QrCodeService
{
    public static function generateToken(): string
    {
        return 'MSOC-QR-' . strtoupper(Str::random(32));
    }
}
