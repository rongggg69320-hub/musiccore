<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\SupabaseStorage;

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

    protected $appends = ['audio_url', 'cover_url', 'genre_name'];

    public function getAudioUrlAttribute()
    {
        return SupabaseStorage::musicUrl($this->audio_file);
    }

    public function getCoverUrlAttribute()
    {
        return SupabaseStorage::imageUrl($this->cover_image);
    }

    public function getGenreNameAttribute()
    {
        return $this->genre?->name;
    }

    public function genre()
    {
        return $this->belongsTo(Genre::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
