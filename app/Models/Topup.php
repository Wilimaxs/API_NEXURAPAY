<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topup extends Model
{
    use HasFactory;

    protected $fillable = [
        'hp',
        'order_id',
        'amount',
        'status',
        'payment_type',
        'midtrans_response',
    ];


    protected $casts = [
        'amount' => 'decimal:2',
        'midtrans_response' => 'array',
    ];
}
