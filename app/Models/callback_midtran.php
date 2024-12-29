<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class callback_midtran extends Model
{
    protected $table = 'callback_midtrans';

    protected $fillable = [
        'order_id',
        'transaction_status',
        'status_code',
        'gross_amount',
        'payment_type',
        'transaction_id',
        'fraud_status',
        'raw_response'
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'raw_response' => 'array'
    ];
}
