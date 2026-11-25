<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use AuditsChanges, BelongsToCompany;

    public const KIND_ATTENDANCE = 'attendance';

    public const KIND_GATE = 'gate';

    public const ROLE_IN = 'in';

    public const ROLE_OUT = 'out';

    public const ROLE_BOTH = 'both';

    public const ROLE_ENROLL = 'enroll';

    protected $fillable = ['company_id', 'name', 'model', 'serial', 'register_id', 'ip_address', 'lan_ip', 'username', 'password', 'port', 'place', 'gate_kind', 'punch_role', 'status', 'last_checked_at'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
            'password' => 'encrypted',
            'port' => 'integer',
        ];
    }

    public function terminalUsername(): string
    {
        $username = trim((string) $this->username);
        if ($username !== '') {
            return $username;
        }
        if ($this->punch_role === self::ROLE_OUT) {
            $out = trim((string) config('hikvision.terminals.out.username'));
            if ($out !== '') {
                return $out;
            }
        }

        return (string) config('hikvision.username', 'admin');
    }

    public function terminalPassword(): string
    {
        $password = trim((string) $this->password);
        if ($password !== '') {
            return $password;
        }
        if ($this->punch_role === self::ROLE_OUT) {
            $out = trim((string) config('hikvision.terminals.out.password'));
            if ($out !== '') {
                return $out;
            }
        }

        return trim((string) config('hikvision.password'));
    }

    public function terminalHost(): string
    {
        [$host, $port] = $this->dialAddress();
        if ($host === '') {
            return '';
        }

        return $port === 80 ? $host : $host.':'.$port;
    }

    /**
     * Address the server can actually open. A private office IP is kept on the
     * office network. From the live server it is replaced by the public host
     * and the open firewall port.
     *
     * @return array{0: string, 1: int}
     */
    public function dialAddress(): array
    {
        $ip = $this->cleanHost((string) $this->ip_address);
        $port = (int) $this->port;
        $public = $this->publicHost();
        if ($ip !== '' && ! $this->isPrivateAddress($ip)) {
            return [$ip, $port > 0 ? $port : 80];
        }
        if ($public !== '') {
            return [$public, $this->publishedPort($port)];
        }
        if ($ip !== '') {
            return [$ip, $port > 0 ? $port : 80];
        }

        $fallback = $this->cleanHost((string) config('hikvision.host'));
        $fallbackPort = is_numeric(config('hikvision.port')) ? (int) config('hikvision.port') : 80;

        return [$fallback, $port > 0 ? $port : ($fallbackPort > 0 ? $fallbackPort : 80)];
    }

    private function publicHost(): string
    {
        foreach ([config('hikvision.public_host'), config('hikvision.host')] as $candidate) {
            $host = $this->cleanHost((string) $candidate);
            if ($host !== '' && ! $this->isPrivateAddress($host)) {
                return $host;
            }
        }

        return '';
    }

    private function publishedPort(int $devicePort): int
    {
        $inPort = config('hikvision.terminals.in.port') ?: config('hikvision.port');
        $inPort = is_numeric($inPort) ? (int) $inPort : 0;
        $outPort = config('hikvision.terminals.out.port');
        $outPort = is_numeric($outPort) ? (int) $outPort : 0;
        if ($this->punch_role === self::ROLE_OUT && $outPort > 80) {
            if ($devicePort <= 80 || $devicePort === $inPort) {
                return $outPort;
            }

            return $devicePort;
        }
        if ($devicePort > 80) {
            return $devicePort;
        }
        if ($inPort > 0) {
            return $inPort;
        }

        return 80;
    }

    private function cleanHost(string $host): string
    {
        $host = trim((string) preg_replace('#^https?://#', '', $host));
        $host = explode('/', $host)[0];
        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        return trim($host);
    }

    private function isPrivateAddress(string $host): bool
    {
        if ($host === '' || $host === 'localhost') {
            return true;
        }
        if (! filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    public function users(): HasMany
    {
        return $this->hasMany(DeviceUser::class);
    }

    public function terminalUsers(): HasMany
    {
        return $this->hasMany(DeviceTerminalUser::class);
    }

    /** Register ID starting HIK → Hikvision ISAPI. */
    public function isHikvision(): bool
    {
        return str_starts_with(strtoupper(trim((string) $this->register_id)), 'HIK');
    }

    public function brandLabel(): string
    {
        return $this->isHikvision() ? 'Hikvision' : 'Terminal';
    }

    public function isGate(): bool
    {
        return $this->gate_kind === self::KIND_GATE;
    }

    public function isAttendance(): bool
    {
        return ! $this->isGate();
    }

    public function readsIn(): bool
    {
        return $this->punch_role === self::ROLE_IN || $this->punch_role === self::ROLE_BOTH || $this->punch_role === null || $this->punch_role === '';
    }

    public function readsOut(): bool
    {
        return $this->isGate() || $this->punch_role === self::ROLE_OUT;
    }

    public function recordsAttendance(): bool
    {
        return $this->punch_role !== self::ROLE_ENROLL;
    }

    public function roleLabel(): string
    {
        if ($this->isGate()) {
            return 'Gate OUT (pass only)';
        }

        return match ($this->punch_role) {
            self::ROLE_IN => 'IN attendance',
            self::ROLE_OUT => 'OUT attendance',
            self::ROLE_ENROLL => 'Enrollment only',
            default => 'Attendance IN / OUT',
        };
    }
}
