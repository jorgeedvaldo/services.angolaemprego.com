<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Link extends Model
{
    use HasFactory;

    use HasFactory;
    protected $fillable = [
        'url', 'country_id', 'published'
    ];
}
