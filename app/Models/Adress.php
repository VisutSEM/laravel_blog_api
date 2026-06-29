<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Adress extends Model
{
    /** @use HasFactory<\Database\Factories\AdressFactory> */
    use HasFactory;
    protected $fillable = [
        'user_id',
        'street',
        'city',
        'state',
        'latitude',
        'longitude',
    ];
}
