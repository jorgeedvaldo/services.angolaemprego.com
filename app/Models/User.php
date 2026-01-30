<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'cv_path',
        'subscription_start',
        'subscription_end',
        'subscription_status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'subscription_start' => 'datetime',
        'subscription_end' => 'datetime',
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_user')->withTimestamps();
    }

    public function getCvUrlAttribute()
    {
        return $this->cv_path ? 'https://angolaemprego.com/storage/' . $this->cv_path : null;
    }

    public function hasActiveSubscription()
    {
        return $this->subscription_status === 'active' && 
               $this->subscription_end && 
               $this->subscription_end->isFuture();
    }
}
