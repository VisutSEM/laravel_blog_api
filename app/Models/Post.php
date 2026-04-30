<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['title', 'body', 'image_url'])]
class Post extends Model
{
    //
}
