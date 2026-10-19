<?php

namespace App\Services;

use App\Models\Company;
use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

class NumberSequenceService
{
    public const EMPLOYEE_CODE_LENGTH = 6;

    public function nextEmployeeCode(Company $company): string
    {
        return DB::transaction(function () use ($company): string {
            $prefix = $this->employeeCodePrefix($company);
            $padding = self::EMPLOYEE_CODE_LENGTH - strlen($prefix);

            $sequence = NumberSequence::query()->firstOrCreate(
                ['company_id' => $company->id, 'sequence_key' => 'employee'],
                ['prefix' => $prefix, 'padding' => $padding, 'next_number' => 1],
            );

            $locked = NumberSequence::query()->whereKey($sequence->id)->lockForUpdate()->firstOrFail();

            $code = $prefix.str_pad((string) $locked->next_number, $padding, '0', STR_PAD_LEFT);

            $locked->forceFill([
                'prefix' => $prefix,
                'padding' => $padding,
                'next_number' => $locked->next_number + 1,
            ])->save();

            return $code;
        });
    }

    /**
     * Company-based prefix so Autotech becomes AT0001 (always 6 chars total).
     */
    public function employeeCodePrefix(Company $company): string
    {
        $source = preg_replace('/[^A-Za-z0-9]/', '', (string) ($company->code ?: $company->name)) ?: 'EM';
        $prefix = strtoupper(substr($source, 0, 2));

        return $prefix !== '' ? $prefix : 'EM';
    }
}
