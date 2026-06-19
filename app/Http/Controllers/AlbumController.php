<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Album;
use App\Models\Track;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AlbumController extends Controller
{
    /**
     * List all albums for the authenticated user.
     */
    public function index()
    {
        return Album::with(['user', 'genre'])->where('user_id', Auth::id())->latest()->get();
    }

    /**
     * Store a new album.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'artist_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|max:10240',
            'status' => 'nullable|in:draft,published,archived',
            'genre_id' => 'nullable|integer|exists:genres,id',
            'track_ids' => 'nullable|array',
            'track_ids.*' => 'integer|exists:tracks,id',
        ]);

        $coverPath = $request->hasFile('cover_image') ? $request->file('cover_image')->store('album_covers', 'supabase_images') : null;

        $album = Album::create([
            'title' => $request->input('title'),
            'user_id' => Auth::id(),
            'artist_name' => $request->input('artist_name'),
            'description' => $request->input('description'),
            'cover_image' => $coverPath,
            'genre_id' => $request->input('genre_id'),
            'status' => $request->input('status', 'draft'),
        ]);

        if ($request->has('track_ids')) {
            Track::whereIn('id', $request->input('track_ids'))
                ->where('user_id', Auth::id())
                ->update(['album_id' => $album->id, 'album' => $album->title]);
        }

        Cache::forget('new_releases_albums');

        return response()->json($album->load(['user', 'genre']), 201);
    }

    /**
     * Show a single album.
     */
    public function show($id)
    {
        $album = Album::with(['tracks.user', 'tracks.genre', 'user', 'genre'])->findOrFail($id);
        if ($album->status !== 'published' && $album->user_id !== Auth::id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($album);
    }

    /**
     * Get tracks for a specific album with pagination.
     */
    public function tracks(Request $request, $id)
    {
        $limit = (int) $request->query('limit', 10);
        $offset = (int) $request->query('offset', 0);

        $album = Album::findOrFail($id);
        if ($album->status !== 'published' && $album->user_id !== Auth::id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return Track::with(['user', 'genre'])
            ->where('album_id', $id)
            ->latest()
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * Discovery albums with pagination
     */
    public function publicAlbums(Request $request)
    {
        $limit = $request->query('limit', 20);
        $offset = $request->query('offset', 0);

        return Album::with(['user', 'genre'])
            ->where('status', 'published')
            ->latest()
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * Update an album
     */
    public function update(Request $request, $id)
    {
        $album = Album::findOrFail($id);
        if ($album->user_id !== Auth::id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'artist_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|max:10240',
            'status' => 'nullable|in:draft,published,archived',
            'genre_id' => 'nullable|integer|exists:genres,id',
            'track_ids' => 'nullable|array',
            'track_ids.*' => 'integer|exists:tracks,id',
        ]);

        if ($request->hasFile('cover_image')) {
            if ($album->cover_image) Storage::disk('supabase_images')->delete($album->cover_image);
            $album->cover_image = $request->file('cover_image')->store('album_covers', 'supabase_images');
        }

        $album->update($request->only(['title', 'artist_name', 'description', 'status', 'genre_id']));

        if ($request->has('track_ids')) {
            Track::where('album_id', $album->id)
                ->whereNotIn('id', $request->input('track_ids'))
                ->update(['album_id' => null, 'album' => null]);

            Track::whereIn('id', $request->input('track_ids'))
                ->where('user_id', Auth::id())
                ->update(['album_id' => $album->id, 'album' => $album->title]);
        }

        Cache::forget('new_releases_albums');

        return response()->json($album->load(['user', 'genre']));
    }

    /**
     * Delete an album.
     */
    public function destroy($id)
    {
        $album = Album::findOrFail($id);
        if ($album->user_id !== Auth::id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($album->cover_image) Storage::disk('supabase_images')->delete($album->cover_image);

        Track::where('album_id', $album->id)->update(['album_id' => null, 'album' => null]);
        $album->delete();

        Cache::forget('new_releases_albums');

        return response()->json(['success' => true]);
    }

    /**
     * Get new released albums - Cached for 10 minutes
     */
    public function newReleases()
    {
        $limit = request('limit', 10);
        return Cache::remember('new_releases_albums', 600, function () use ($limit) {
            return Album::with(['user', 'genre'])
                ->where('status', 'published')
                ->latest()
                ->limit($limit)
                ->get();
        });
    }
}
