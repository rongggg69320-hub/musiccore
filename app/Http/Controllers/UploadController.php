<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Track;
use App\Models\Genre;
use App\Models\Album;
use App\Models\User;
use App\Support\SupabaseStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class UploadController extends Controller
{
    /**
     * List genres - Cached for 24 hours
     */
    public function listGenres()
    {
        return Cache::remember('genres_list', 86400, function () {
            return Genre::all(['id', 'name']);
        });
    }

    /**
     * List tracks for authenticated user
     */
    public function index()
    {
        return Track::with($this->trackRelations())->where('user_id', Auth::id())->latest()->get();
    }

    /**
     * Show a single track
     */
    public function show($id)
    {
        return Track::with($this->trackRelations())->findOrFail($id);
    }

    /**
     * Update track metadata
     */
    public function update(Request $request, $id)
    {
        $track = Track::findOrFail($id);
        if ($track->user_id !== Auth::id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->merge(['album_id' => $request->input('album_id') === '' ? null : $request->input('album_id')] );
        $rules = [
            'album_id' => 'nullable|integer|exists:albums,id',
            'album' => 'nullable|string|max:255',
            'genre_id' => 'nullable|integer|exists:genres,id',
            'status' => 'nullable|string|max:255',
            'artist_name' => 'nullable|string|max:255',
            'title' => $request->has('title') ? 'required|string|max:255' : 'sometimes|string|max:255',
        ];

        $request->validate($rules);

        if ($request->hasFile('cover_image')) {
            if ($track->cover_image) Storage::disk('supabase_images')->delete($track->cover_image);
            $track->cover_image = $request->file('cover_image')->store('covers', 'supabase_images');
        }

        if ($request->has('title')) $track->title = $request->input('title');
        if ($request->has('artist_name')) $track->artist_name = $this->sanitizeArtistName($request->input('artist_name'));

        if ($request->has('album_id')) {
            $track->album_id = $request->input('album_id');
            if ($track->album_id) {
                $album = Album::find($track->album_id);
                $track->album = $album ? $album->title : $request->input('album');
            } else {
                $track->album = $request->input('album');
            }
        } elseif ($request->has('album')) {
            $track->album = $request->input('album');
        }

        if ($request->has('genre_id')) $track->genre_id = $request->input('genre_id');
        if ($request->has('status')) $track->status = $request->input('status');

        $track->save();
        Cache::forget('new_releases_tracks');

        return response()->json($track->load($this->trackRelations()));
    }

    /**
     * Delete a track
     */
    public function destroy($id)
    {
        $track = Track::findOrFail($id);
        if ($track->user_id !== Auth::id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($track->audio_file) Storage::disk('supabase_music')->delete($track->audio_file);
        if ($track->cover_image) Storage::disk('supabase_images')->delete($track->cover_image);

        $track->delete();
        Cache::forget('new_releases_tracks');

        return response()->json(['success' => true]);
    }

    /**
     * Handle audio upload
     */
    public function upload(Request $request)
    {
        $request->merge(['album_id' => $request->input('album_id') === '' ? null : $request->input('album_id')] );
        $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:mp3,wav,m4a,ogg,oga|max:51200', // 50MB
            'cover_image' => 'nullable|image|max:10240',
            'album_id' => 'nullable|integer|exists:albums,id',
            'album' => 'nullable|string|max:255',
            'genre_id' => 'nullable|integer|exists:genres,id',
            'artist_name' => 'nullable|string|max:255',
        ]);

        $path = $request->file('file')->store('tracks', 'supabase_music');
        $coverPath = $request->hasFile('cover_image') ? $request->file('cover_image')->store('covers', 'supabase_images') : null;

        $albumId = $request->input('album_id');
        $albumTitle = $albumId ? (Album::find($albumId)->title ?? $request->input('album')) : $request->input('album');

        $track = Track::create([
            'title' => $request->input('title'),
            'user_id' => Auth::id(),
            'artist_name' => $this->sanitizeArtistName($request->input('artist_name')),
            'album_id' => $albumId,
            'album' => $albumTitle,
            'genre_id' => $request->input('genre_id'),
            'audio_file' => $path,
            'cover_image' => $coverPath,
            'status' => 'published',
        ]);

        Cache::forget('new_releases_tracks');

        return response()->json(['success' => true, 'track' => $track->load($this->trackRelations())], 201);
    }

    /**
     * Shuffled tracks for radio
     */
    public function radio()
    {
        $limit = $this->limit(request('limit', 20));

        return Track::with($this->trackRelations())->where('status', 'published')->inRandomOrder()->limit($limit)->get();
    }

    /**
     * New releases - Cached for 10 minutes
     */
    public function newReleases()
    {
        $limit = $this->limit(request('limit', 10));

        return Cache::remember("new_releases_tracks:{$limit}", 600, function () use ($limit) {
            return Track::with($this->trackRelations())->where('status', 'published')->latest()->limit($limit)->get();
        });
    }

    /**
     * Public tracks with pagination
     */
    public function publicTracks(Request $request)
    {
        $limit = $this->limit($request->query('limit', 20));
        $offset = $this->offset($request->query('offset', 0));

        return Track::with($this->trackRelations())->where('status', 'published')->latest()->offset($offset)->limit($limit)->get();
    }

    /**
     * Search across tracks, albums and users.
     */
    public function search(Request $request)
    {
        $q = trim($request->query('q', $request->query('query', '')));
        $limit = $this->limit($request->query('limit', 20));
        $offset = $this->offset($request->query('offset', 0));

        if ($q === '') return response()->json(['tracks' => [], 'albums' => [], 'artists' => []]);

        $tracks = Track::with($this->trackRelations())->where('status', 'published')
            ->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                    ->orWhere('artist_name', 'like', "%{$q}%")
                    ->orWhere('album', 'like', "%{$q}%")
                    ->orWhereHas('user', function ($userQuery) use ($q) {
                        $userQuery->where('username', 'like', "%{$q}%");
                    });
            })->latest()->offset($offset)->limit($limit)->get();

        $albums = Album::with($this->albumRelations())->where('status', 'published')
            ->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")->orWhere('artist_name', 'like', "%{$q}%");
            })->latest()->offset($offset)->limit($limit)->get();

        $artists = User::select(['id', 'username', 'profile_image', 'bio', 'created_at'])
            ->where('username', 'like', "%{$q}%")
            ->latest()
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json(['tracks' => $tracks, 'albums' => $albums, 'artists' => $artists]);
    }

    /**
     * Show public user profile
     */
    public function showUser($id)
    {
        $user = User::findOrFail($id);

        // Use auth('sanctum')->id() to get the authenticated user if token is present,
        // even if the route is public.
        $viewerId = auth('sanctum')->id();

        $user->load(['tracks' => function($q) use ($user, $viewerId) {
            $q->with('genre:id,name');
            if ($viewerId !== $user->id) {
                $q->where('status', 'published');
            }
            $q->latest();
        }, 'albums' => function($q) use ($user, $viewerId) {
            $q->with('genre:id,name');
            if ($viewerId !== $user->id) {
                $q->where('status', 'published');
            }
            $q->latest();
        }]);

        return response()->json($user);
    }

    /**
     * Get tracks by genre
     */
    public function genreTracks($genreId)
    {
        $limit = $this->limit(request('limit', 10));
        $offset = $this->offset(request('offset', 0));
        $random = filter_var(request('random', false), FILTER_VALIDATE_BOOLEAN);

        $query = Track::with($this->trackRelations())->where('genre_id', $genreId)->where('status', 'published');
        if ($random) $query->inRandomOrder();
        else $query->latest();

        return $query->offset($offset)->limit($limit)->get();
    }

    private function sanitizeArtistName($artistName)
    {
        if ($artistName === null) return null;
        $trimmed = trim($artistName);
        if ($trimmed === '' || strtolower($trimmed) === 'unknown artist') return null;
        return $trimmed;
    }

    private function trackRelations(): array
    {
        return ['user:id,username,profile_image', 'genre:id,name'];
    }

    private function albumRelations(): array
    {
        return ['user:id,username,profile_image', 'genre:id,name'];
    }

    private function limit($value, int $default = 20, int $max = 50): int
    {
        $limit = filter_var($value, FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 1) {
            return $default;
        }

        return min($limit, $max);
    }

    private function offset($value): int
    {
        $offset = filter_var($value, FILTER_VALIDATE_INT);

        if ($offset === false || $offset < 0) {
            return 0;
        }

        return $offset;
    }
}
