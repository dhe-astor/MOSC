<?php

namespace App\Services;

use App\Models\Certificate;

class CertificateVerificationService
{
    public function generateUniqueCode(): string
    {
        $pool = 'ABCDEFGHJKLMNPQRSTUVWXY3456789'; // Excludes: O, 0, I, 1
        $poolLength = strlen($pool);

        do {
            $code1 = '';
            $code2 = '';
            for ($i = 0; $i < 4; $i++) {
                $code1 .= $pool[random_int(0, $poolLength - 1)];
                $code2 .= $pool[random_int(0, $poolLength - 1)];
            }
            $verificationCode = "MSOC-V-{$code1}-{$code2}";
        } while (Certificate::where('verification_code', $verificationCode)->exists());

        return $verificationCode;
    }

    public function verify(string $code): ?Certificate
    {
        return Certificate::with('church')
            ->where('verification_code', strtoupper(trim($code)))
            ->first();
    }
}
