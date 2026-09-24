<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'phone',
    'profile_picture',
    'fcm_token',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'phone' => 'string',
        ];
    }

    public function routesNotificationForFcm(): string|array|null
    {
        return $this->fcm_token;
    }

    public function wishlist()
{
    return $this->belongsToMany(Product::class, 'wishlists')->withTimestamps();
}

    // In app/Models/User.php

public function carts()
{
    return $this->hasMany(Cart::class);
}

public function orders()
{
    return $this->hasMany(Order::class);
}
}
