<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use App\Support\SupabaseStorage;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'role_id',
        'username',
        'email',
        'password',
        'is_password_set',
        'profile_image',
        'bio',
        'social_provider',
        'social_id',
        'google_id',
        'facebook_id',
        'firebase_uid',
        'is_verified',
        'last_login',
        'status',
    ];

    protected $appends = ['profile_image_url', 'profile_pic_url', 'name', 'connected_providers', 'connected_provider'];

    public function getProfileImageUrlAttribute()
    {
        $profileImage = $this->profile_image;
        if ($profileImage && filter_var($profileImage, FILTER_VALIDATE_URL)) {
            return $profileImage;
        }
        return SupabaseStorage::imageUrl($profileImage);
    }

    public function getProfilePicUrlAttribute()
    {
        return $this->profile_image_url;
    }

    public function getNameAttribute()
    {
        return $this->username;
    }

    public function getConnectedProvidersAttribute()
    {
        $columns = collect([
            'email' => $this->is_password_set,
            'google' => $this->google_id,
            'facebook' => $this->facebook_id,
        ])->filter()->keys();

        if ($this->relationLoaded('authProviders')) {
            $columns = $columns->merge($this->authProviders->pluck('provider'));
        }

        return $columns->unique()->values()->all();
    }

    public function getConnectedProviderAttribute()
    {
        return $this->connected_providers[0] ?? null;
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'last_login' => 'datetime',
    ];

    /**
     * Ensure the email is stored in lowercase.
     */
    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = $value !== null ? strtolower($value) : null;
    }

    /**
     * Ensure the username is stored in lowercase.
     */
    public function setUsernameAttribute($value)
    {
        $this->attributes['username'] = $value !== null ? strtolower($value) : null;
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function tracks()
    {
        return $this->hasMany(Track::class);
    }

    public function albums()
    {
        return $this->hasMany(Album::class);
    }

    public function authProviders()
    {
        return $this->hasMany(UserAuthProvider::class);
    }
}
