<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Job extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'company_id',
        'title',
        'level',
        'location',
        'description',
        'vacancy',
        'experience',
        'salary',
        'date_posted',
        'deadline'
    ];

    protected $casts = [
        'deadline' => 'date',
        'date_posted' => 'date',
    ];

    public function savedByUsers()
    {
        return $this->belongsToMany(User::class, 'saved_jobs')->withTimestamps();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class, 'job_id', 'id');
    }

    public function scopeActive(Builder $query)
    {
        return $query->whereDate('deadline', '>=', today());
    }
}
