<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Experience extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'position',
        'company',
        'role',
        'duration',
        'skills'
    ];

    protected $table = 'experiences';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
