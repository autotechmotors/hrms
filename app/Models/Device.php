<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use AuditsChanges, BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'model', 'serial', 'register_id', 'ip_address', 'username', 'password', 'port', 'place', 'status', 'last_checked_at'];

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

    public function isHikvision(): bool
    {
        return str_starts_with(strtoupper(trim((string) $this->register_id)), 'HIK');
    }
}
