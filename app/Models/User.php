<?php

namespace App\Models;


use Laravel\Sanctum\HasApiTokens;
use App\Models\Balance;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'password',
        'name',
        'address',
        'hp',
        'key_reseller',
        'no_rekening',
        'ktp_image',
        'selfi_image',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    protected static function booted()
    {
        static::created(function ($user) {
            Balance::create([
                'user_id' => $user->id,
                'amount' => 0
            ]);
        });
    }

    public function balance()
    {
        return $this->hasOne(Balance::class);
    }
}
