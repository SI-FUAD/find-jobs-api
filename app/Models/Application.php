<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

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
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function job()
    {
        return $this->belongsTo(Job::class, 'job_id', 'id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function isApplied()
    {
        return $this->status === 'applied';
    }

    public function isShortlisted()
    {
        return $this->status === 'shortlisted';
    }

    public function isAccepted()
    {
        return $this->status === 'accepted';
    }

    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    public function scopeApplied(Builder $query)
    {
        return $query->where('status', 'applied');
    }

    public function scopeShortlisted(Builder $query)
    {
        return $query->where('status', 'shortlisted');
    }

    public function scopeAccepted(Builder $query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeRejected(Builder $query)
    {
        return $query->where('status', 'rejected');
    }
}
