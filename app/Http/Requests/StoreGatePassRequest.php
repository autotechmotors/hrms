<?php

namespace App\Http\Requests;

use App\Models\GatePassSetting;
use App\Services\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGatePassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('gate_passes.create') === true;
    }

    protected function prepareForValidation(): void
    {
        $early = $this->input('leave_type') === 'early_leave';
        $out = $this->normalizeTime((string) $this->input('out_time', ''));
        $clock = $this->durationClock();
        $minutes = $early ? $this->minutesUntilEndOfDay($out) : $this->minutesFromClock($clock);

        $this->merge([
            'out_time' => $out ?: $this->input('out_time'),
            'is_early_leave' => $early,
            'duration_minutes' => $minutes,
            'duration_time' => $minutes ? sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60) : $clock,
        ]);
    }

    public function rules(): array
    {
        $company = app(Workspace::class)->company($this->user());
        $max = (int) (GatePassSetting::forCompany((int) ($company?->id ?: 0))->max_duration_minutes ?? 360);

        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:today'],
            'work_type' => ['required', Rule::in(['personal', 'office'])],
            'leave_type' => ['required', Rule::in(['return', 'early_leave'])],
            'is_early_leave' => ['boolean'],
            'out_time' => ['required', 'date_format:H:i'],
            'duration_time' => [Rule::requiredIf(! $this->boolean('is_early_leave')), 'nullable', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:15', Rule::when(! $this->boolean('is_early_leave'), ['max:'.$max])],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    protected function durationClock(): string
    {
        if ($this->exists('duration_hours') || $this->exists('duration_minutes')) {
            $hours = max(0, (int) $this->input('duration_hours', 0));
            $mins = max(0, min(59, (int) $this->input('duration_minutes', 0)));

            return sprintf('%02d:%02d', $hours, $mins);
        }

        return $this->normalizeTime((string) $this->input('duration_time', ''));
    }

    protected function normalizeTime(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i', $value, $match)) {
            $hour = (int) $match[1];
            $ampm = strtoupper($match[3]);
            $hour = $ampm === 'AM' && $hour === 12 ? 0 : ($ampm === 'PM' && $hour < 12 ? $hour + 12 : $hour);

            return sprintf('%02d:%02d', $hour, (int) $match[2]);
        }

        return preg_match('/^(\d{1,2}):(\d{2})$/', $value, $match)
            ? sprintf('%02d:%02d', $match[1], $match[2])
            : $value;
    }

    protected function minutesFromClock(string $value): ?int
    {
        return preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $match)
            ? ((int) $match[1] * 60) + (int) $match[2]
            : null;
    }

    protected function minutesUntilEndOfDay(string $outTime): int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $outTime, $match)) {
            return 15;
        }

        return max(15, ((23 - (int) $match[1]) * 60) + (59 - (int) $match[2]));
    }
}
