<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(private readonly Google2FA $google2fa = new Google2FA())
    {
    }

    public function ensureSecret(User $user): void
    {
        if ($user->two_factor_secret) {
            return;
        }

        $user->forceFill([
            'two_factor_secret' => $this->google2fa->generateSecretKey(),
        ])->save();
    }

    public function qrSvg(User $user): string
    {
        $label = rawurlencode(config('app.name').':'.$user->email);
        $issuer = rawurlencode((string) config('app.name'));
        $url = 'otpauth://totp/'.$label.'?secret='.$user->two_factor_secret.'&issuer='.$issuer.'&digits=6&period=30';

        $writer = new Writer(new ImageRenderer(new RendererStyle(180), new SvgImageBackEnd()));

        return $writer->writeString($url);
    }

    public function verify(User $user, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if ($user->two_factor_secret && $this->google2fa->verifyKey($user->two_factor_secret, $code, 1)) {
            return true;
        }

        return $this->consumeRecoveryCode($user, $code);
    }

    /**
     * @return list<string>
     */
    public function confirm(User $user, string $code): array
    {
        if (! $this->google2fa->verifyKey((string) $user->two_factor_secret, preg_replace('/\s+/', '', $code) ?? '', 1)) {
            return [];
        }

        $plain = [];
        $hashes = [];

        for ($i = 0; $i < 8; $i++) {
            $value = Str::upper(Str::random(4).'-'.Str::random(4));
            $plain[] = $value;
            $hashes[] = Hash::make($value);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $hashes,
        ])->save();

        return $plain;
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $hashes = $user->two_factor_recovery_codes ?? [];

        foreach ($hashes as $index => $hash) {
            if (! Hash::check($code, $hash)) {
                continue;
            }

            unset($hashes[$index]);
            $user->forceFill([
                'two_factor_recovery_codes' => array_values($hashes),
            ])->save();

            return true;
        }

        return false;
    }
}
