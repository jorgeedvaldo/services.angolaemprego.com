<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'company', 'location', 'country_id', 'description', 'email_or_link', 'image'
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
