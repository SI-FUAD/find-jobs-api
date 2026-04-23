<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'job_id',
        'user_id',
        'company_id',
        'status',
        'is_shortlisted',
        'applied_at',
        'shortlisted_at',
        'accepted_at',
        'rejected_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function isAccepted()
    {
        return $this->status === 'accepted';
    }

    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    public function isShortlisted()
    {
        return $this->status === 'shortlisted';
    }
}
