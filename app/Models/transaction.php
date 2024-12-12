<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class transaction extends Model
{
    protected $fillable = [
        'no_hp',
        'reff',
        'custno',
        'product_code',
        'hjual',
        'adm',
        'fr_balancejual',
        'last_balancejual',
    ];
}
