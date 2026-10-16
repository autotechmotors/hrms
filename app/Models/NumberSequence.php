<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberSequence extends Model
{
    protected $fillable = ['company_id', 'sequence_key', 'prefix', 'padding', 'next_number'];
}
