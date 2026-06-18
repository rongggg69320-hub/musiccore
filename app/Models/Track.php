<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Track extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'user_id',
        'artist_name',
        'album_id',
        'album',
        'genre_id',
        'audio_file',
        'cover_image',
        'status',
    ];

    public function genre()
    {
        return $this->belongsTo(Genre::class);
    }
    
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}