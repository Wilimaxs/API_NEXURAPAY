<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class product_pascabayar extends Model
{
  use HasFactory;

  protected $fillable = [
    'product_code',
    'name',
    'operator_id',
    'category_id',
    'price',
    'status',
    'description'
  ];
}
