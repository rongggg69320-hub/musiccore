<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAuthProvider extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'firebase_uid',
        'email',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
