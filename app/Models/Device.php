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

        return $username !== '' ? $username : (string) config('hikvision.username', 'admin');
    }

    public function terminalPassword(): string
    {
        $password = trim((string) $this->password);

        return $password !== '' ? $password : trim((string) config('hikvision.password'));
    }

    public function terminalHost(): string
    {
        $ip = trim((string) preg_replace('#^https?://#', '', (string) $this->ip_address));
        $ip = explode('/', $ip)[0];
        $port = (int) $this->port;
        if ($ip !== '' && $port > 0) {
            return $port === 80 ? $ip : $ip.':'.$port;
        }

        $configured = trim((string) config('hikvision.host'));
        $configPort = is_numeric(config('hikvision.port')) ? (int) config('hikvision.port') : 0;
        if ($this->isHikvision() && $configured !== '' && $configPort > 0) {
            return $configPort === 80 ? $configured : $configured.':'.$configPort;
        }

        return $ip !== '' ? $ip : $configured;
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
