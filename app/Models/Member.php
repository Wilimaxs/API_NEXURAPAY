<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class member extends Authenticatable
{

    use HasApiTokens, Notifiable;
    protected $fillable = [
        'email',
        'password',
        'name',
        'phone',
        'address'
    ];

    protected $hidden = [
        'password',

    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
