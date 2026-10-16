<?php

namespace App\Services;

use App\Models\Company;
use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

class NumberSequenceService
{
    public function nextEmployeeCode(Company $company): string
    {
        return DB::transaction(function () use ($company): string {
            $sequence = NumberSequence::query()->firstOrCreate(
                ['company_id' => $company->id, 'sequence_key' => 'employee'],
                ['prefix' => 'EMP', 'padding' => 4, 'next_number' => 1],
            );

            $locked = NumberSequence::query()->whereKey($sequence->id)->lockForUpdate()->firstOrFail();
            $code = $locked->prefix.str_pad((string) $locked->next_number, (int) $locked->padding, '0', STR_PAD_LEFT);
            $locked->increment('next_number');

            return $code;
        });
    }
}
