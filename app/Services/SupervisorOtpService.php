<?php

namespace App\Services;

use App\Models\SupervisorOtp;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

class SupervisorOtpService
{
    public function generate(User $supervisor): array
    {
        if (!$supervisor->hasAnyRole(['supervisor', 'admin'])) {
            throw new \RuntimeException(
                'Hanya supervisor/admin yang boleh generate OTP.'
            );
        }

        SupervisorOtp::query()
            ->where('supervisor_user_id', $supervisor->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->update(['revoked_at' => now()]);

        $code = (string) random_int(100000, 999999);

        $otp = SupervisorOtp::create([
            'supervisor_user_id' => $supervisor->id,
            'code_hash' => $this->hash($code),
            'code_encrypted' => Crypt::encryptString($code),
            'generated_at' => now(),
            'expires_at' => now()->addMinutes(
                (int) config('warehouse_check.otp_minutes', 120)
            ),
        ]);

        return compact('otp', 'code');
    }

    public function activeFor(User $supervisor): ?array
    {
        $otp = SupervisorOtp::query()
            ->where('supervisor_user_id', $supervisor->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (!$otp) {
            return null;
        }

        return [
            'otp' => $otp,
            'code' => Crypt::decryptString($otp->code_encrypted),
        ];
    }

    public function validateCode(string $code): SupervisorOtp
    {
        $otp = SupervisorOtp::query()
            ->where('code_hash', $this->hash($code))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (!$otp) {
            throw new \RuntimeException(
                'OTP tidak valid, expired, atau sudah di-revoke.'
            );
        }

        $supervisor = User::query()->find($otp->supervisor_user_id);

        if (
            !$supervisor
            || !$supervisor->is_active
            || !$supervisor->hasAnyRole(['supervisor', 'admin'])
        ) {
            throw new \RuntimeException(
                'Supervisor pemilik OTP tidak aktif.'
            );
        }

        return $otp;
    }

    public function revokeActive(User $supervisor): void
    {
        SupervisorOtp::query()
            ->where('supervisor_user_id', $supervisor->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->update(['revoked_at' => now()]);
    }

    private function hash(string $code): string
    {
        return hash_hmac(
            'sha256',
            trim($code),
            (string) config('app.key')
        );
    }
}
