<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class callback extends Model
{
    protected $fillable = [
        'trx_id',
        'api_trxid',
        'via',
        'code',
        'produk',
        'target',
        'mtrpln',
        'note',
        'token',
        'harga',
        'saldo_before_trx',
        'saldo_after_trx',
        'status',
        'id_user',
        'nama',
        'periode',
        'jumlah_tagihan',
        'admin',
        'jumlah_bayar',
    ];
}
