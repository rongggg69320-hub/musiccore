<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\SupabaseStorage;

class Album extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'user_id',
        'artist_name',
        'description',
        'cover_image',
        'status',
        'genre_id',
    ];

    protected $appends = ['cover_url', 'genre_name'];

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
        return $this->belongsTo(User::class);
    }

    public function tracks()
    {
        return $this->hasMany(Track::class);
    }
}
