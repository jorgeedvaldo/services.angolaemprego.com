<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tracked_job_id',
        'status', //'pending', 'sent', 'failed'
        'error_message',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trackedJob()
    {
        return $this->belongsTo(TrackedJob::class);
    }
}
