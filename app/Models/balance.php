<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class balance extends Model
{
    protected $fillable = ['user_id', 'amount'];
    protected $hidden = ['created_at', 'update_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
