<?php

use App\Models\Role;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('searching an artist username returns their published tracks', function () {
    $role = Role::create(['role_name' => 'user']);

    $artist = User::create([
        'role_id' => $role->id,
        'username' => 'luna',
        'email' => 'luna@example.com',
        'password' => Hash::make('password'),
        'status' => 'active',
    ]);

    Track::create([
        'user_id' => $artist->id,
        'title' => 'Midnight Signal',
        'artist_name' => null,
        'album' => null,
        'audio_file' => 'tracks/midnight-signal.mp3',
        'status' => 'published',
    ]);

    $this->getJson('/api/search?query=luna')
        ->assertOk()
        ->assertJsonPath('artists.0.username', 'luna')
        ->assertJsonPath('tracks.0.title', 'Midnight Signal')
        ->assertJsonPath('tracks.0.username', 'luna');
});

test('empty search uses the same result keys as populated search', function () {
    $this->getJson('/api/search?query=')
        ->assertOk()
        ->assertExactJson([
            'tracks' => [],
            'albums' => [],
            'artists' => [],
        ]);
});

test('public user profile returns that artists published tracks', function () {
    $role = Role::create(['role_name' => 'user']);

    $artist = User::create([
        'role_id' => $role->id,
        'username' => 'ticmeng',
        'email' => 'ticmeng@example.com',
        'password' => Hash::make('password'),
        'status' => 'active',
    ]);

    Track::create([
        'user_id' => $artist->id,
        'title' => 'WAY YOU ARE ft Jenna',
        'artist_name' => null,
        'audio_file' => 'tracks/way-you-are.mp3',
        'status' => 'published',
    ]);

    Track::create([
        'user_id' => $artist->id,
        'title' => 'Draft Song',
        'artist_name' => null,
        'audio_file' => 'tracks/draft-song.mp3',
        'status' => 'processing',
    ]);

    $this->getJson("/api/users/{$artist->id}?limit=10&offset=0")
        ->assertOk()
        ->assertJsonPath('username', 'ticmeng')
        ->assertJsonCount(1, 'tracks')
        ->assertJsonPath('tracks.0.title', 'WAY YOU ARE ft Jenna');
});

test('public tracks endpoint returns published tracks for show all', function () {
    $role = Role::create(['role_name' => 'user']);

    $artist = User::create([
        'role_id' => $role->id,
        'username' => 'publicartist',
        'email' => 'publicartist@example.com',
        'password' => Hash::make('password'),
        'status' => 'active',
    ]);

    Track::create([
        'user_id' => $artist->id,
        'title' => 'Public Track',
        'artist_name' => null,
        'audio_file' => 'tracks/public-track.mp3',
        'status' => 'published',
    ]);

    $this->getJson('/api/tracks/public?limit=10&offset=0')
        ->assertOk()
        ->assertJsonPath('0.title', 'Public Track')
        ->assertJsonPath('0.username', 'publicartist');
});
